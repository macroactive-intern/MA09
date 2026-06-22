Step 1

    Project set up
                1. Start new Laravel project
                2. connect to Github repo
                                                                                                    10 mins

----------------------------------------------------------------------------------------------------------------

Step 2

    Documentation
                1. Write out the Understand.md
                2. Write out the Time Estimate.md
                3. Add the Ai Time estimate to the Estimate.md
                4. Write out the Aproach.md
                                                                                                        120 mins

----------------------------------------------------------------------------------------------------------------

Step 3

    Finish Project set up
                1. Install dependencies
                2. Install Sanctum
                3. Install Pest
                4. Confirm API/auth setup
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 4

    Create Feature tests

                1. Create test file
                2. Test unauthenticated users
                        - Request without token.
                        - Expect 401.

                3. Test non-coach user
                        - Auth as user with non-coach role.
                        - Expect 403.

                4. Test grouped response
                    Seed:
                        - One high-risk client.
                        - One medium-risk client.
                        - One active client.
                    Assert:
                        - summary.high
                        - summary.medium
                        - summary.none
                        - at_risk
                        - active

                5. Test high-risk example from brief
                    Freeze time:
                        2026-06-16

                    Seed client last active:
                        2026-04-01
                
                6. Test no activity logs
                7. Test exact boundary: 30 days
                8. Test exact boundary: 60 days
                9. Test risk_level=high
                10. Test risk_level=none
                11. Test sorting by days inactive
                12. Test sorting by name
                13. Test query count
                                                                                                    80 mins

----------------------------------------------------------------------------------------------------------------

Step 5

    Database work

                1. Update users table
                        - Add:
                            role
                        - Possible values:
                            coach
                            client
                
                2. Create clients table
                        Columns:
                                id
                                coach_id
                                name
                                email
                                timezone
                                joined_at
                                created_at
                                updated_at
                        
                        Constraints:
                                coach_id references users.id
                                name max 100
                                email max 150
                                timezone max 60
                
                3. Create client_activity_logs table
                        Columns:
                                id
                                client_id
                                activity_type
                                logged_at
                                created_at
                                updated_at
                        Allowed activity_type values:
                                check_in
                                message_sent
                                workout_logged
                        Constraints:
                                client_id references clients.id
                                Cascade delete may be useful if a client is deleted.
                                                                                                    25 mins

----------------------------------------------------------------------------------------------------------------

Step 6

    Models

                1. User
                        Add role support.
                        Add relationship:
                                        clients()
                
                2. Client
                        Fillable fields:
                                coach_id
                                name
                                email
                                timezone
                                joined_at
                        Cast:
                                joined_at as datetime
                        Relationships:
                                coach()
                                activityLogs()
                
                3. ClientActivityLog
                        Fillable fields:
                                client_id
                                activity_type
                                logged_at
                        Cast:
                                logged_at as datetime
                        Relationship:
                                client()
                                                                                                    40 mins

----------------------------------------------------------------------------------------------------------------

Step 7

    Route

                1. Add API route
                2. Add coach-only protection
                                                                                                    30 mins

----------------------------------------------------------------------------------------------------------------

Step 8

    Controller

                1. Create controller
                2. Validate query params
                3. Fetch clients with latest activity
                4. Calculate days_inactive
                5. Calculate risk level
                6. Build summary
                7. Apply optional filter
                8. Group response
                9. Sort response
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 9

    Run Tests
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 10

    Fix any failing tests
                                                                                                    25 mins

----------------------------------------------------------------------------------------------------------------

Step 11

    Manual test
                                                                                                    45 mins

----------------------------------------------------------------------------------------------------------------

Step 12 

    BEFORE-AFTER.md
                                                                                                    30 mins
----------------------------------------------------------------------------------------------------------------

                                                                                                    8.25 hrs

---------------------------------------------------------------------------------------------------------------- 

AI Estimate
Step	Task	Manual Estimate	AI Estimate
1	Project setup	10 mins	15 mins
2	Documentation	120 mins	110 mins
3	Finish project setup	20 mins	25 mins
4	Create feature tests	80 mins	100 mins
5	Database work	25 mins	30 mins
6	Models	40 mins	35 mins
7	Route	30 mins	25 mins
8	Controller	45 mins	75 mins
9	Run tests	20 mins	20 mins
10	Fix failing tests	25 mins	45 mins
11	Manual test	45 mins	40 mins
12	BEFORE-AFTER.md	30 mins	30 mins
AI Total
550 minutes
9 hours 10 minutes
Reconciliation

My original estimate was around 8 hours 10 minutes. The AI estimate is higher at 9 hours 10 minutes.

The main reason for the difference is that this task has a few parts that may take longer than they first look:

The endpoint must stay under 2 database queries, so the query design needs care.
Clients with no activity logs must fall back to joined_at.
summary must be calculated across the full dataset, even when the response is filtered.
Date calculations need to be dynamic and testable with frozen time.
Timezone handling needs to be documented and implemented consistently.
The query-count test may take extra debugging if the implementation accidentally causes an extra query.