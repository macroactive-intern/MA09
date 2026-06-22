<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChurnRiskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'risk_level' => ['nullable', 'string', Rule::in(['high', 'medium', 'none'])],
            'sort'       => ['nullable', 'string', Rule::in(['days_inactive', 'name'])],
        ]);

        $sort            = $request->input('sort', 'days_inactive');
        $filterRiskLevel = $request->input('risk_level');
        $appTimezone     = config('app.timezone', 'UTC');
        $today           = Carbon::now($appTimezone)->startOfDay();

        // Single aggregate query — avoids N+1 regardless of client count
        $rows = DB::table('clients')
            ->select([
                'clients.id as client_id',
                'clients.name',
                'clients.joined_at',
                DB::raw('COALESCE(MAX(client_activity_logs.logged_at), clients.joined_at) AS last_activity_at'),
            ])
            ->leftJoin('client_activity_logs', 'client_activity_logs.client_id', '=', 'clients.id')
            ->where('clients.coach_id', $request->user()->id)
            ->groupBy('clients.id', 'clients.name', 'clients.joined_at')
            ->get();

        // Calculate risk for every client in memory
        $clients = $rows->map(function ($row) use ($today, $appTimezone) {
            $lastActivityDay = Carbon::parse($row->last_activity_at)
                ->setTimezone($appTimezone)
                ->startOfDay();

            // abs() guards against Carbon 3 returning a negative diff when the
            // argument is earlier than the receiver (diffInDays sign convention).
            $daysInactive = (int) abs($today->diffInDays($lastActivityDay));

            $riskLevel = match (true) {
                $daysInactive >= 60 => 'high',
                $daysInactive >= 30 => 'medium',
                default             => 'none',
            };

            return [
                'client_id'        => $row->client_id,
                'name'             => $row->name,
                'last_activity_at' => Carbon::parse($row->last_activity_at)->toISOString(),
                'days_inactive'    => $daysInactive,
                'risk_level'       => $riskLevel,
            ];
        });

        // Summary is always built from the full unfiltered dataset
        $summary = [
            'high'   => $clients->where('risk_level', 'high')->count(),
            'medium' => $clients->where('risk_level', 'medium')->count(),
            'none'   => $clients->where('risk_level', 'none')->count(),
        ];

        // Apply optional risk_level filter before grouping
        $filtered = $filterRiskLevel !== null
            ? $clients->where('risk_level', $filterRiskLevel)->values()
            : $clients;

        $atRisk = $filtered->whereIn('risk_level', ['high', 'medium'])->values();
        $active = $filtered->where('risk_level', 'none')->values();

        // Sort each group independently
        if ($sort === 'name') {
            $atRisk = $atRisk->sortBy('name')->values();
            $active = $active->sortBy('name')->values();
        } else {
            $atRisk = $atRisk->sortByDesc('days_inactive')->values();
            $active = $active->sortByDesc('days_inactive')->values();
        }

        return response()->json([
            'summary' => $summary,
            'at_risk' => $atRisk,
            'active'  => $active,
        ]);
    }
}
