I am going to build a Laravel JSON API endpoint that allows an authenticated coach to view their clients grouped by churn risk.

The endpoint will be:

GET /api/coach/analytics/churn-risk

It will return:

summary — total number of the coach's clients in each risk level
at_risk — clients with high or medium churn risk
active — clients with none risk

The risk calculation is based on how many days have passed since the client's most recent activity.

The correct thresholds are from the Requirements section, not the older background section:

Risk level	Rule
high	inactive for 60 or more days
medium	inactive for 30 to 59 days
none	inactive for less than 30 days

The old thresholds from the background section, 45 days and 20 days, are historical only and will not be used.

Data model

This project starts from a fresh Laravel app, so I will create the supporting tables needed for this endpoint.

users table update

The brief says the endpoint requires auth:sanctum with a coach role, but it does not define how roles should be implemented.

For this project, I will keep the role system simple and add a role column to the users table.

Columns added
Column	Type	Notes
role	string	Stores user role, for example coach or client
Decision

I will use a simple role column instead of adding a permissions package such as Spatie Permissions. This keeps the project focused on the analytics endpoint and is enough for the acceptance criteria.

clients table

This table stores the coach's client roster.

Columns
Column	Type	Constraints / Notes
id	bigIncrements	Primary key
coach_id	foreignId	References users.id
name	string(100)	Required
email	string(150)	Required
timezone	string(60)	Required, for example Pacific/Auckland
joined_at	timestamp	Required
created_at	timestamp	Laravel timestamp
updated_at	timestamp	Laravel timestamp
Constraints
coach_id references id on users
name limited to 100 characters
email limited to 150 characters
timezone limited to 60 characters
Email uniqueness decision

The brief does not say whether client emails must be unique.

For this endpoint, email uniqueness is not essential because the endpoint only reads existing clients. I will not rely on email uniqueness for the analytics logic.

If client creation was part of this task, I would consider a unique constraint on:

coach_id + email

That would allow different coaches to have clients with the same email while preventing duplicates inside one coach's roster.

client_activity_logs table

This table stores activity events for clients.

Columns
Column	Type	Constraints / Notes
id	bigIncrements	Primary key
client_id	foreignId	References clients.id
activity_type	enum/string	check_in, message_sent, workout_logged
logged_at	timestamp	When the activity happened
created_at	timestamp	Laravel timestamp
updated_at	timestamp	Laravel timestamp
Constraints
client_id references id on clients
client_id should cascade on delete
activity_type must be one of:
check_in
message_sent
workout_logged
Models
User

The existing Laravel User model will be updated to support coach ownership.

Relationships
public function clients()
{
    return $this->hasMany(Client::class, 'coach_id');
}
Role handling

A user with:

role = coach

can access the churn-risk endpoint.

A user with another role should receive 403 Forbidden.

Client

The Client model represents a coach's client.

Fillable fields
protected $fillable = [
    'coach_id',
    'name',
    'email',
    'timezone',
    'joined_at',
];
Casts
protected $casts = [
    'joined_at' => 'datetime',
];
Relationships
public function coach()
{
    return $this->belongsTo(User::class, 'coach_id');
}

public function activityLogs()
{
    return $this->hasMany(ClientActivityLog::class);
}
ClientActivityLog

The ClientActivityLog model represents one activity event for a client.

Fillable fields
protected $fillable = [
    'client_id',
    'activity_type',
    'logged_at',
];
Casts
protected $casts = [
    'logged_at' => 'datetime',
];
Relationships
public function client()
{
    return $this->belongsTo(Client::class);
}
Endpoint and route

The route will be added in routes/api.php.

Route::middleware(['auth:sanctum'])->get(
    '/coach/analytics/churn-risk',
    [ChurnRiskController::class, 'index']
);

I will also protect the endpoint so only coaches can use it.

This can be done with a small middleware such as:

EnsureUserIsCoach

or with a controller check:

abort_unless($request->user()->role === 'coach', 403);

My preferred approach is middleware because it keeps the controller focused on the analytics logic.

Final route idea:

Route::middleware(['auth:sanctum', 'coach'])->get(
    '/coach/analytics/churn-risk',
    [ChurnRiskController::class, 'index']
);
Request inputs

The endpoint accepts two optional query parameters.

risk_level
?risk_level=high|medium|none

This filters the returned client rows to a single risk level.

Valid values:

high
medium
none

Invalid values should return a validation error.

sort
?sort=days_inactive|name

Valid values:

days_inactive
name

Default:

days_inactive

The default sort will be days_inactive descending so the most inactive clients appear first.

For sort=name, clients will be sorted alphabetically ascending by name.

Response shape

The endpoint will return JSON in this shape:

{
  "summary": {
    "high": 1,
    "medium": 2,
    "none": 14
  },
  "at_risk": [
    {
      "client_id": 12,
      "name": "Alice Smith",
      "last_activity_at": "2026-04-01T09:00:00Z",
      "days_inactive": 76,
      "risk_level": "high"
    }
  ],
  "active": [
    {
      "client_id": 7,
      "name": "Bob Jones",
      "last_activity_at": "2026-06-12T14:00:00Z",
      "days_inactive": 3,
      "risk_level": "none"
    }
  ]
}
Inactive definition

A client is inactive based on the number of days since their most recent activity log.

All supported activity types count as activity:

check_in
message_sent
workout_logged

I am counting all of these because the brief describes the table as activity tracking, and all three values show that the client is still engaging with the product.

A client with no activity logs is treated as inactive from their joined_at date.

So the fallback rule is:

last_activity_at = latest activity logged_at, or joined_at if no activity exists
Query design

The endpoint must use at most 2 database queries regardless of how many clients the coach has.

That means I must avoid this bad approach:

Query clients
For each client, query latest activity

That would cause an N+1 query problem.

Instead, I will fetch the coach's clients and their latest activity using one main query with an aggregate subquery.

Conceptually:

SELECT
    clients.id,
    clients.name,
    clients.joined_at,
    COALESCE(MAX(client_activity_logs.logged_at), clients.joined_at) AS last_activity_at
FROM clients
LEFT JOIN client_activity_logs
    ON client_activity_logs.client_id = clients.id
WHERE clients.coach_id = ?
GROUP BY clients.id

The important part is:

COALESCE(MAX(client_activity_logs.logged_at), clients.joined_at)

This means:

if the client has activity logs, use their latest logged_at
if the client has no activity logs, use joined_at

This query should return enough data to calculate:

last_activity_at
days_inactive
risk_level
summary
at_risk
active

The summary will be calculated in memory from this same dataset, so it does not need a third query.

Query count decision

The acceptance criteria says the endpoint must use at most 2 queries.

My target is 1 main query for the client risk dataset.

There may also be an auth/user query depending on how the request is authenticated during tests. That is why the acceptance criteria allows up to 2 queries.

The endpoint logic itself will not query once per client.

For a coach with 50 clients, the endpoint should still use at most 2 queries.

Risk calculation

After fetching each client with a last_activity_at, I will calculate days_inactive.

The risk level rules are:

if ($daysInactive >= 60) {
    $riskLevel = 'high';
} elseif ($daysInactive >= 30) {
    $riskLevel = 'medium';
} else {
    $riskLevel = 'none';
}

Examples:

Days inactive	Risk level
0	none
3	none
29	none
30	medium
59	medium
60	high
76	high
Worked example from brief

Today:

2026-06-16

Client's last activity:

2026-04-01

Calculation:

April 1 to June 16 = 76 days

Risk check:

76 >= 60

Result:

{
  "days_inactive": 76,
  "risk_level": "high"
}

This client is high risk, not medium.

Timezone decision

The brief asks whose timezone should be used when calculating “30 days ago”.

The clients table has a timezone column, but this endpoint is for the coach's analytics dashboard.

I will calculate inactivity using the authenticated coach's timezone if the app stores one for coaches.

Because the brief does not define a coach timezone field, I will fall back to the application timezone. For this project, I will use:

Pacific/Auckland

I am not going to calculate each client's risk separately using each client's timezone.

Reason:

The endpoint is for the coach's roster view.
The coach needs one consistent definition of “today”.
Using each client's timezone could make the dashboard confusing.
Two clients with the same UTC activity time could appear in different risk tiers only because they live in different timezones.

So the implementation decision is:

Calculate calendar-day inactivity using the coach/app timezone, not raw UTC hour differences.

Example edge case:

It is midnight in Auckland.
A client last checked in 30 days ago Auckland time.
In UTC, that may only be 29 days ago.

In this implementation, that client counts as 30 calendar days inactive and is medium risk.

Summary computation

The summary object must count all of the coach's clients across the full dataset.

It must not change when risk_level filtering is used.

So the order will be:

Fetch all clients for the authenticated coach.
Calculate days_inactive and risk_level for every client.
Build the full summary from all calculated clients.
Apply optional risk_level filtering to the response arrays.
Group clients into at_risk and active.
Sort each group.
Return JSON.

Example:

If the full dataset has:

high = 1
medium = 2
none = 14

Then this request:

GET /api/coach/analytics/churn-risk?risk_level=high

should still return:

"summary": {
  "high": 1,
  "medium": 2,
  "none": 14
}

But the at_risk array should only contain high-risk clients.

Filtering behavior

The brief explicitly explains risk_level=high, but does not fully explain risk_level=none.

I will handle filters like this:

Query	Result
no filter	at_risk contains high and medium clients, active contains none clients
risk_level=high	at_risk contains only high-risk clients, active is empty
risk_level=medium	at_risk contains only medium-risk clients, active is empty
risk_level=none	active contains only none-risk clients, at_risk is empty

In all cases, summary remains unfiltered.

Sorting behavior

The brief says sorting happens within each group.

The response has two groups:

at_risk
active

So sorting will be applied separately to each array.

Default:

sort=days_inactive

This means:

days_inactive descending

For:

sort=name

This means:

name ascending
Libraries and packages
Laravel

Laravel is the required framework for the project.

I will use Laravel for:

routing
controllers
middleware
migrations
Eloquent models
validation
testing helpers
Laravel Sanctum

Sanctum is required by the brief.

I will use it for API authentication.

The endpoint will require:

auth:sanctum
SQLite

SQLite is required by the setup instructions.

I will configure .env for SQLite and use SQLite for local development and tests.

Pest

The user workflow includes writing feature tests first. I will use Pest if it is installed for the project.

Pest will be used for feature tests covering:

auth
role protection
grouping
risk calculations
no activity fallback
filters
sorting
query count
Carbon

Laravel already uses Carbon for date handling.

I will use Carbon for:

freezing the current date in tests
calculating calendar day differences
timezone-aware date comparisons

Example test date:

Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00', 'Pacific/Auckland'));
Feature test plan

I will write feature tests before implementing the endpoint.

The main test file will be:

tests/Feature/ChurnRiskAnalyticsTest.php

Tests to include:

Unauthenticated users receive 401.
Authenticated non-coach users receive 403.
Coach receives clients grouped into at_risk and active.
Summary counts match actual risk levels.
A client last active on 2026-04-01 with today as 2026-06-16 has:
days_inactive = 76
risk_level = high
Client with no activity logs uses joined_at.
Client exactly 30 days inactive is medium.
Client exactly 60 days inactive is high.
risk_level=high filters returned clients but not the summary.
risk_level=none returns active clients only.
Default sorting uses days_inactive descending.
sort=name sorts alphabetically.
Query count stays at 2 or fewer for many clients.
Edge cases
Client has no activity logs

Problem:

The client has no rows in client_activity_logs.

Handling:

Use clients.joined_at as their last_activity_at.

Client is exactly 30 days inactive

Problem:

Boundary values can be easy to get wrong.

Handling:

A client inactive for exactly 30 days is medium.

Client is exactly 60 days inactive

Problem:

High-risk boundary could accidentally be treated as medium.

Handling:

A client inactive for exactly 60 days is high.

Old thresholds are mentioned in the brief

Problem:

The background section says the old system used 45 and 20 day thresholds.

Handling:

Ignore the old values. Use the Requirements section:

high: 60+ days
medium: 30–59 days
none: under 30 days
risk_level filtering should not affect summary

Problem:

It would be easy to filter first and then calculate the summary incorrectly.

Handling:

Calculate summary before filtering the returned arrays.

risk_level=none

Problem:

The brief gives a clear example for risk_level=high, but not for risk_level=none.

Handling:

Treat risk_level=none as a request to return active clients only.

Sorting within groups

Problem:

The brief says sort within each group, not sort the whole response as one list.

Handling:

Sort at_risk and active separately.

Timezone boundary around midnight

Problem:

A client may be 30 days inactive in Auckland calendar days but only 29 days inactive by UTC hour difference.

Handling:

Use coach/app timezone calendar-day difference. For this project, the fallback timezone is Pacific/Auckland.

Query count

Problem:

A simple relationship loop could accidentally create N+1 queries.

Handling:

Use one aggregate query or subquery to load all clients with their latest activity. Calculate summary and grouping in memory.

Coach ownership

Problem:

A coach should not see another coach's clients.

Handling:

The query must always include:

where clients.coach_id = authenticated user id
Invalid query parameters

Problem:

The endpoint only supports specific values.

Handling:

Invalid risk_level or sort values should return a validation error, likely 422.

Final implementation order

I will build the project in this order:

Finish documentation files:
UNDERSTANDING.md
ESTIMATE.md
APPROACH.md
Set up Laravel, SQLite, Sanctum, and Pest.
Add migrations:
role column on users
clients
client_activity_logs
Add models and relationships.
Add coach middleware or controller role check.
Write feature tests first.
Add route.
Add controller.
Implement query and risk calculation.
Implement summary, filtering, grouping, and sorting.
Run tests.
Fix failing tests.
Manually test the endpoint.
Add BEFORE-AFTER.md with terminal output.