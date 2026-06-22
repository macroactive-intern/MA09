
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   FAIL  Tests\Feature\ChurnRiskAnalyticsTest
  ✓ it returns 401 for unauthenticated requests                                                              0.27s  
  ✓ it returns 403 for authenticated non-coach users                                                         0.03s  
  ⨯ it returns clients grouped into at_risk and active with correct summary counts                           0.02s  
  ⨯ it calculates 76 days inactive as high risk for the brief example                                        0.01s  
  ⨯ it uses joined_at as the fallback last_activity_at when a client has no activity logs                    0.01s  
  ⨯ it classifies exactly 30 days inactive as medium risk                                                    0.02s  
  ⨯ it classifies exactly 60 days inactive as high risk                                                      0.01s  
  ⨯ it filters at_risk to high-risk only when risk_level=high and leaves summary unfiltered                  0.02s  
  ⨯ it returns only active clients when risk_level=none and at_risk is empty                                 0.01s  
  ⨯ it sorts at_risk by days_inactive descending by default                                                  0.01s  
  ⨯ it sorts clients by name ascending when sort=name                                                        0.01s  
  ✓ it uses at most 2 database queries for a coach with many clients                                         0.02s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                            0.05s  
  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it returns clients grouped into at_risk and active with correc…   
  Failed asserting that an array has the key 'summary'.

  at tests\Feature\ChurnRiskAnalyticsTest.php:81
     77▕ 
     78▕     $response = $this->actingAs($coach)
     79▕         ->getJson('/api/coach/analytics/churn-risk')
     80▕         ->assertStatus(200)
  ➜  81▕         ->assertJsonStructure([
     82▕             'summary' => ['high', 'medium', 'none'],
     83▕             'at_risk',
     84▕             'active',
     85▕         ])

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it calculates 76 days inactive as hig…  InvalidExpectationValue   
  Invalid expectation value type. Expected [countable|iterable].

  at tests\Feature\ChurnRiskAnalyticsTest.php:125
    121▕         ->getJson('/api/coach/analytics/churn-risk')
    122▕         ->assertStatus(200);
    123▕ 
    124▕     $atRisk = $response->json('at_risk');
  ➜ 125▕     expect($atRisk)->toHaveCount(1);
    126▕     expect($atRisk[0]['days_inactive'])->toBe(76);
    127▕     expect($atRisk[0]['risk_level'])->toBe('high');
    128▕ });
    129▕

  1   tests\Feature\ChurnRiskAnalyticsTest.php:125

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it uses joined_at as the fallback las…  InvalidExpectationValue   
  Invalid expectation value type. Expected [countable|iterable].

  at tests\Feature\ChurnRiskAnalyticsTest.php:152
    148▕         ->getJson('/api/coach/analytics/churn-risk')
    149▕         ->assertStatus(200);
    150▕ 
    151▕     $atRisk = $response->json('at_risk');
  ➜ 152▕     expect($atRisk)->toHaveCount(1);
    153▕     expect($atRisk[0]['days_inactive'])->toBe(45);
    154▕     expect($atRisk[0]['risk_level'])->toBe('medium');
    155▕ });
    156▕

  1   tests\Feature\ChurnRiskAnalyticsTest.php:152

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it classifies exactly 30 days inactiv…  InvalidExpectationValue   
  Invalid expectation value type. Expected [countable|iterable].

  at tests\Feature\ChurnRiskAnalyticsTest.php:184
    180▕         ->getJson('/api/coach/analytics/churn-risk')
    181▕         ->assertStatus(200);
    182▕ 
    183▕     $atRisk = $response->json('at_risk');
  ➜ 184▕     expect($atRisk)->toHaveCount(1);
    185▕     expect($atRisk[0]['days_inactive'])->toBe(30);
    186▕     expect($atRisk[0]['risk_level'])->toBe('medium');
    187▕ });
    188▕

  1   tests\Feature\ChurnRiskAnalyticsTest.php:184

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it classifies exactly 60 days inactiv…  InvalidExpectationValue   
  Invalid expectation value type. Expected [countable|iterable].

  at tests\Feature\ChurnRiskAnalyticsTest.php:216
    212▕         ->getJson('/api/coach/analytics/churn-risk')
    213▕         ->assertStatus(200);
    214▕ 
    215▕     $atRisk = $response->json('at_risk');
  ➜ 216▕     expect($atRisk)->toHaveCount(1);
    217▕     expect($atRisk[0]['days_inactive'])->toBe(60);
    218▕     expect($atRisk[0]['risk_level'])->toBe('high');
    219▕ });
    220▕

  1   tests\Feature\ChurnRiskAnalyticsTest.php:216

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it filters at_risk to high-risk only when risk_level=high and…    
  Failed asserting that null is identical to 1.

  at tests\Feature\ChurnRiskAnalyticsTest.php:272
    268▕ 
    269▕     $response = $this->actingAs($coach)
    270▕         ->getJson('/api/coach/analytics/churn-risk?risk_level=high')
    271▕         ->assertStatus(200)
  ➜ 272▕         ->assertJsonPath('summary.high', 1)
    273▕         ->assertJsonPath('summary.medium', 1)
    274▕         ->assertJsonPath('summary.none', 1);
    275▕ 
    276▕     $atRisk = $response->json('at_risk');

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it returns only active clients when r…  InvalidExpectationValue   
  Invalid expectation value type. Expected [countable|iterable].

  at tests\Feature\ChurnRiskAnalyticsTest.php:321
    317▕     $response = $this->actingAs($coach)
    318▕         ->getJson('/api/coach/analytics/churn-risk?risk_level=none')
    319▕         ->assertStatus(200);
    320▕ 
  ➜ 321▕     expect($response->json('at_risk'))->toHaveCount(0);
    322▕     expect($response->json('active'))->toHaveCount(1);
    323▕     expect($response->json('active.0.risk_level'))->toBe('none');
    324▕ });
    325▕

  1   tests\Feature\ChurnRiskAnalyticsTest.php:321

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it sorts at_risk by days_inactive des…  InvalidExpectationValue   
  Invalid expectation value type. Expected [countable|iterable].

  at tests\Feature\ChurnRiskAnalyticsTest.php:366
    362▕         ->getJson('/api/coach/analytics/churn-risk')
    363▕         ->assertStatus(200);
    364▕ 
    365▕     $atRisk = $response->json('at_risk');
  ➜ 366▕     expect($atRisk)->toHaveCount(2);
    367▕     expect($atRisk[0]['days_inactive'])->toBeGreaterThan($atRisk[1]['days_inactive']);
    368▕ });
    369▕ 
    370▕ // -------------------------------------------------------------------------

  1   tests\Feature\ChurnRiskAnalyticsTest.php:366

  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ChurnRiskAnalyticsTest > it sorts clients by name ascending wh…  InvalidExpectationValue   
  Invalid expectation value type. Expected [countable|iterable].

  at tests\Feature\ChurnRiskAnalyticsTest.php:410
    406▕         ->getJson('/api/coach/analytics/churn-risk?sort=name')
    407▕         ->assertStatus(200);
    408▕ 
    409▕     $atRisk = $response->json('at_risk');
  ➜ 410▕     expect($atRisk)->toHaveCount(2);
    411▕     expect($atRisk[0]['name'])->toBe('Alice');
    412▕     expect($atRisk[1]['name'])->toBe('Charlie');
    413▕ });
    414▕

  1   tests\Feature\ChurnRiskAnalyticsTest.php:410


  Tests:    9 failed, 5 passed (17 assertions)
  Duration: 0.70s