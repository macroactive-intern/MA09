<?php

use App\Models\Client;
use App\Models\ClientActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(function () {
    Carbon::setTestNow();
});

// -------------------------------------------------------------------------
// Auth
// -------------------------------------------------------------------------

it('returns 401 for unauthenticated requests', function () {
    $this->getJson('/api/coach/analytics/churn-risk')
        ->assertStatus(401);
});

it('returns 403 for authenticated non-coach users', function () {
    $user = User::factory()->create(['role' => 'client']);

    $this->actingAs($user)
        ->getJson('/api/coach/analytics/churn-risk')
        ->assertStatus(403);
});

// -------------------------------------------------------------------------
// Grouped response and summary
// -------------------------------------------------------------------------

it('returns clients grouped into at_risk and active with correct summary counts', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    $highClient = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Alice High',
        'email'    => 'alice@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(100),
    ]);
    ClientActivityLog::create([
        'client_id'     => $highClient->id,
        'activity_type' => 'check_in',
        'logged_at'     => Carbon::parse('2026-04-01', 'Pacific/Auckland'),
    ]);

    $mediumClient = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Bob Medium',
        'email'    => 'bob@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(60),
    ]);
    ClientActivityLog::create([
        'client_id'     => $mediumClient->id,
        'activity_type' => 'message_sent',
        'logged_at'     => now()->subDays(45),
    ]);

    $activeClient = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Carol Active',
        'email'    => 'carol@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(20),
    ]);
    ClientActivityLog::create([
        'client_id'     => $activeClient->id,
        'activity_type' => 'workout_logged',
        'logged_at'     => now()->subDays(10),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk')
        ->assertStatus(200)
        ->assertJsonStructure([
            'summary' => ['high', 'medium', 'none'],
            'at_risk',
            'active',
        ])
        ->assertJsonPath('summary.high', 1)
        ->assertJsonPath('summary.medium', 1)
        ->assertJsonPath('summary.none', 1);

    expect($response->json('at_risk'))->toHaveCount(2);
    expect($response->json('active'))->toHaveCount(1);

    $riskLevels = collect($response->json('at_risk'))->pluck('risk_level')->sort()->values()->all();
    expect($riskLevels)->toBe(['high', 'medium']);
    expect($response->json('active.0.risk_level'))->toBe('none');
});

// -------------------------------------------------------------------------
// Brief example: 2026-04-01 → 76 days inactive → high
// -------------------------------------------------------------------------

it('calculates 76 days inactive as high risk for the brief example', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    $client = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Alice Smith',
        'email'    => 'alice.smith@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => Carbon::parse('2026-01-01', 'Pacific/Auckland'),
    ]);
    ClientActivityLog::create([
        'client_id'     => $client->id,
        'activity_type' => 'check_in',
        'logged_at'     => Carbon::parse('2026-04-01 09:00:00', 'UTC'),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk')
        ->assertStatus(200);

    $atRisk = $response->json('at_risk');
    expect($atRisk)->toHaveCount(1);
    expect($atRisk[0]['days_inactive'])->toBe(76);
    expect($atRisk[0]['risk_level'])->toBe('high');
});

// -------------------------------------------------------------------------
// No activity logs → joined_at fallback
// -------------------------------------------------------------------------

it('uses joined_at as the fallback last_activity_at when a client has no activity logs', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    Client::create([
        'coach_id' => $coach->id,
        'name'     => 'No Activity',
        'email'    => 'noactivity@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(45),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk')
        ->assertStatus(200);

    $atRisk = $response->json('at_risk');
    expect($atRisk)->toHaveCount(1);
    expect($atRisk[0]['days_inactive'])->toBe(45);
    expect($atRisk[0]['risk_level'])->toBe('medium');
});

// -------------------------------------------------------------------------
// Boundary: exactly 30 days → medium
// -------------------------------------------------------------------------

it('classifies exactly 30 days inactive as medium risk', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    $client = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Thirty Days',
        'email'    => 'thirty@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(60),
    ]);
    ClientActivityLog::create([
        'client_id'     => $client->id,
        'activity_type' => 'check_in',
        'logged_at'     => now()->subDays(30),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk')
        ->assertStatus(200);

    $atRisk = $response->json('at_risk');
    expect($atRisk)->toHaveCount(1);
    expect($atRisk[0]['days_inactive'])->toBe(30);
    expect($atRisk[0]['risk_level'])->toBe('medium');
});

// -------------------------------------------------------------------------
// Boundary: exactly 60 days → high
// -------------------------------------------------------------------------

it('classifies exactly 60 days inactive as high risk', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    $client = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Sixty Days',
        'email'    => 'sixty@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(90),
    ]);
    ClientActivityLog::create([
        'client_id'     => $client->id,
        'activity_type' => 'workout_logged',
        'logged_at'     => now()->subDays(60),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk')
        ->assertStatus(200);

    $atRisk = $response->json('at_risk');
    expect($atRisk)->toHaveCount(1);
    expect($atRisk[0]['days_inactive'])->toBe(60);
    expect($atRisk[0]['risk_level'])->toBe('high');
});

// -------------------------------------------------------------------------
// Filtering: risk_level=high
// -------------------------------------------------------------------------

it('filters at_risk to high-risk only when risk_level=high and leaves summary unfiltered', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    $high = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'High Risk',
        'email'    => 'high@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(90),
    ]);
    ClientActivityLog::create([
        'client_id'     => $high->id,
        'activity_type' => 'check_in',
        'logged_at'     => now()->subDays(76),
    ]);

    $medium = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Medium Risk',
        'email'    => 'medium@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(60),
    ]);
    ClientActivityLog::create([
        'client_id'     => $medium->id,
        'activity_type' => 'message_sent',
        'logged_at'     => now()->subDays(45),
    ]);

    $active = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Active User',
        'email'    => 'active@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(20),
    ]);
    ClientActivityLog::create([
        'client_id'     => $active->id,
        'activity_type' => 'workout_logged',
        'logged_at'     => now()->subDays(5),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk?risk_level=high')
        ->assertStatus(200)
        ->assertJsonPath('summary.high', 1)
        ->assertJsonPath('summary.medium', 1)
        ->assertJsonPath('summary.none', 1);

    $atRisk = $response->json('at_risk');
    expect($atRisk)->toHaveCount(1);
    expect($atRisk[0]['risk_level'])->toBe('high');
    expect($response->json('active'))->toHaveCount(0);
});

// -------------------------------------------------------------------------
// Filtering: risk_level=none
// -------------------------------------------------------------------------

it('returns only active clients when risk_level=none and at_risk is empty', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    $high = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'High Risk',
        'email'    => 'high@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(90),
    ]);
    ClientActivityLog::create([
        'client_id'     => $high->id,
        'activity_type' => 'check_in',
        'logged_at'     => now()->subDays(76),
    ]);

    $active = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Active User',
        'email'    => 'active@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(20),
    ]);
    ClientActivityLog::create([
        'client_id'     => $active->id,
        'activity_type' => 'workout_logged',
        'logged_at'     => now()->subDays(5),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk?risk_level=none')
        ->assertStatus(200);

    expect($response->json('at_risk'))->toHaveCount(0);
    expect($response->json('active'))->toHaveCount(1);
    expect($response->json('active.0.risk_level'))->toBe('none');
});

// -------------------------------------------------------------------------
// Sorting: days_inactive descending (default)
// -------------------------------------------------------------------------

it('sorts at_risk by days_inactive descending by default', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    $clientA = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Alpha',
        'email'    => 'alpha@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(120),
    ]);
    ClientActivityLog::create([
        'client_id'     => $clientA->id,
        'activity_type' => 'check_in',
        'logged_at'     => now()->subDays(90),
    ]);

    $clientB = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Beta',
        'email'    => 'beta@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(100),
    ]);
    ClientActivityLog::create([
        'client_id'     => $clientB->id,
        'activity_type' => 'workout_logged',
        'logged_at'     => now()->subDays(65),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk')
        ->assertStatus(200);

    $atRisk = $response->json('at_risk');
    expect($atRisk)->toHaveCount(2);
    expect($atRisk[0]['days_inactive'])->toBeGreaterThan($atRisk[1]['days_inactive']);
});

// -------------------------------------------------------------------------
// Sorting: name ascending
// -------------------------------------------------------------------------

it('sorts clients by name ascending when sort=name', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    $charlie = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Charlie',
        'email'    => 'charlie@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(120),
    ]);
    ClientActivityLog::create([
        'client_id'     => $charlie->id,
        'activity_type' => 'check_in',
        'logged_at'     => now()->subDays(90),
    ]);

    $alice = Client::create([
        'coach_id' => $coach->id,
        'name'     => 'Alice',
        'email'    => 'alice2@example.com',
        'timezone' => 'Pacific/Auckland',
        'joined_at' => now()->subDays(100),
    ]);
    ClientActivityLog::create([
        'client_id'     => $alice->id,
        'activity_type' => 'workout_logged',
        'logged_at'     => now()->subDays(65),
    ]);

    $response = $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk?sort=name')
        ->assertStatus(200);

    $atRisk = $response->json('at_risk');
    expect($atRisk)->toHaveCount(2);
    expect($atRisk[0]['name'])->toBe('Alice');
    expect($atRisk[1]['name'])->toBe('Charlie');
});

// -------------------------------------------------------------------------
// Query count
// -------------------------------------------------------------------------

it('uses at most 2 database queries for a coach with many clients', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));

    $coach = User::factory()->create(['role' => 'coach']);

    for ($i = 1; $i <= 10; $i++) {
        $client = Client::create([
            'coach_id' => $coach->id,
            'name'     => "Client {$i}",
            'email'    => "client{$i}@example.com",
            'timezone' => 'Pacific/Auckland',
            'joined_at' => now()->subDays(10 + $i),
        ]);
        ClientActivityLog::create([
            'client_id'     => $client->id,
            'activity_type' => 'check_in',
            'logged_at'     => now()->subDays($i * 5),
        ]);
    }

    DB::enableQueryLog();

    $this->actingAs($coach)
        ->getJson('/api/coach/analytics/churn-risk');

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(2);
});
