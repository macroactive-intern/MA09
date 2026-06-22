<?php

use App\Http\Controllers\ChurnRiskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'coach'])->get(
    '/coach/analytics/churn-risk',
    [ChurnRiskController::class, 'index']
);
