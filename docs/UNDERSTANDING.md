What is the task asking me to build?

This task is asking me to build a Laravel JSON API endpoint that lets an authenticated coach see which of their clients may be at risk of churning based on recent activity.

    The endpoint is:

            GET /api/coach/analytics/churn-risk

It should return the coach’s clients grouped into:

            at_risk — clients with medium or high churn risk
            active — clients with none risk

Each client needs a calculated churn risk level based on how many days they have been inactive.

The exact risk thresholds to use are from the Requirements section:

            high: inactive for 60 or more days
            medium: inactive for 30 to 59 days
            none: inactive for less than 30 days

--------------------------------------------------------------------------------------------------------------------------------------------

What does "inactive" mean in this implementation?

A client that has been inactive based on the number of days since their most recent activity log.

The activity types that count as activity are all valid values in client_activity_logs.activity_type:

        check_in
        message_sent
        workout_logged

A client with no activity logs at all is considered inactive from their joined_at date. This means joined_at becomes their fallback last_activity_at for risk calculation.

--------------------------------------------------------------------------------------------------------------------------------------------

What inputs does it take?

    The endpoint requires an authenticated coach using Sanctum:

            GET /api/coach/analytics/churn-risk 
            Authorization: Bearer <token>

    It supports these optional query parameters:

            ?risk_level=high|medium|none

    This filters the returned client rows to one risk tier.

            ?sort=days_inactive|name

    The default sort is:

            days_inactive desc

--------------------------------------------------------------------------------------------------------------------------------------------

What does it return?

    The endpoint returns JSON in this shape:

    ```json
    { 
        "summary": 
            {
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
    ```

The summary object must count all of the coach’s clients by risk level across the whole dataset. It should not change when ?risk_level= is used.

Calculation example

    Today is:

        2026-06-16

    Client A’s last activity was:

        2026-04-01

    Calculation:

        April 1 to June 16 = 76 days inactive

    Risk threshold check:

        76 days >= 60 days

    Therefore:

        {
        "days_inactive": 76,
        "risk_level": "high"
        }

    This client is high risk, not medium.

--------------------------------------------------------------------------------------------------------------------------------------------

Timezone decision

The risk calculation should use the authenticated coach’s timezone if the app stores one for coaches. 

I am not planning to calculate risk separately in each client’s timezone, even though the clients table has a timezone column. The reason is that this endpoint is for the coach’s analytics view, and the coach needs one consistent definition of “today” and “30 days ago” across their whole roster.

This avoids confusing situations where two clients with the same UTC last_activity_at could appear in different risk tiers because they live in different timezones.

        Edge case:  

                It is midnight in Auckland. A client last checked in “30 days ago Auckland time” but only 29 days ago UTC. Are they at risk?

        The code should calculate the day boundary using the coach/app timezone, not raw UTC hour differences. So if it is 30 calendar days ago in Auckland time, the client should count as 30 days inactive and become medium risk.

--------------------------------------------------------------------------------------------------------------------------------------------

The background thresholds conflict with the requirement thresholds

I will ignore the historical values and tuse the Requirements section values

high risk at 60+ days
medium risk at 30–59 days
none below 30 days

----------------------------------------------------------------


The exact meaning of “activity”

        The activity log supports:

            check_in
            message_sent
            workout_logged

    All listed activity types count because they are all client engagement signals.

----------------------------------------------------------------

Timezone handling is intentionally ambiguous

I will use the coach/app timezone for consistency across the coach’s dashboard.

----------------------------------------------------------------

Risk filtering behavior needs a clear decision

        It does not explicitly say what happens for:

        ?risk_level=none

                - risk_level=high returns only high-risk clients in at_risk
                - risk_level=medium returns only medium-risk clients in at_risk
                - risk_level=none returns only active clients in active
                - summary always stays unfiltered

----------------------------------------------------------------

Sorting “within each group” needs interpretation

        The endpoint has two top-level client groups:
                                                    - at_risk
                                                    - active

    Clients should be sorted by days_inactive descending, so the most inactive clients appear first.

----------------------------------------------------------------

“At most 2 queries” limits the implementation

    The endpoint must use at most 2 database queries regardless of how many clients the coach has.

    That means I should avoid N+1 queries such as loading each client and then querying their latest activity separately.

    The implementation should fetch clients and their latest activity using an aggregate/subquery or eager loading strategy that keeps the query count at 2 or fewer.

----------------------------------------------------------------

The coach role is not fully defined

The brief says the endpoint requires auth:sanctum with a coach role, but the project starts empty.

It does not say whether roles should be implemented with:
                                                        A role column on users
                                                        A policy
                                                        Middleware
                                                        A package like Spatie permissions

use a simple role column on the users table.

----------------------------------------------------------------

It is unclear whether clients need unique emails per coach

For this analytics endpoint, uniqueness is not essential unless client creation is also being tested. I would probably add a unique constraint on (coach_id, email) if this project includes client creation, but this endpoint can work without relying on that.