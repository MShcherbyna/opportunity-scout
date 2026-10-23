# Run: threads-connection-and-keyword-search

- Phase: done
- Plan: .ai/docs/plans/2026-10-23-0034--threads-connection-and-keyword-search.md · Spec: .ai/docs/specs/threads/SPEC-01-threads-connection-and-keyword-search.md
- Options: mode=multi-agent tests=tdd max-fix=3 triage=ask security=on
- Approved "Requires approval" items: new folders (app/Enums, app/Services/Threads, app/Actions/Threads, app/Jobs, app/Events, app/Console/Commands, app/Http/Resources, app/Http/Controllers/Threads, app/Http/Controllers/Legal, app/Http/Requests/Threads, tests/Feature/Threads); routes/meta-callbacks.php; docker-compose queue + scheduler services
- Start commit: 0c0bf07669ac02d05dfabd151f379fe85208803d
- Snapshots (tree SHAs): base=0d729b95a05e9f50e81f354e90035250b6df3813 · after-implement=db3736fc35f1af062863a09837a9f3d04fb0efb0 · fix-1-before=f20902bfe079c9741595b7b67bb4acb91ff4b9fb · fix-1=9eb841f79980eb29e09f3e537545e101acef5bd5 · fix-2-before=4b32c6c4cd31f2c638d3404c664c367014f62d79 · fix-2=525b0fdc0a58c61806b181e6efa83ebaefb44ec3 · fix-3-before=2f5ecbc781a5446a31b8136c9f6f90e33c0bddb2 · fix-3=62a5c0481e2a5062c38ce1138512dd546ef0e35b · fix-4-before=0f3678b0337ee5af450c5986e6c1ad498a4a4104 · fix-4=72cab4f17df1b575b535b11a05dceab3a3e145e6 · fix-5-before=22138d0fff30afd91d7ca51052d2f8a3fa3f5306 · fix-5=859e6e78d563b8ad5fe41c317054106b1adb0997
- Fix iteration: 5 (user approved PV fixes)

## Checks

| When            | Overall         | Failures                                                                                                     |
| --------------- | --------------- | ------------------------------------------------------------------------------------------------------------ |
| fix-5           | green           | tests 364/364; phpstan, pint, npm check, types green                                                         |
| fix-4           | green           | tests 360/360; phpstan, pint, npm check, types green                                                         |
| fix-3           | green           | tests 359/359; phpstan, pint, npm check, types green                                                         |
| fix-2           | green           | tests 357/357; phpstan, pint, npm check, types green                                                         |
| fix-1           | green           | tests 351/351; phpstan, pint, npm check, types green                                                         |
| after CR-1 fix  | red (DT-1 only) | tests 327/328 (DT-1); phpstan 0 errors; pint, types green; npm check green after ledger reformat             |
| after-implement | red             | tests 327/328 (only DT-1); PHPStan crashed at 128M (CR-1); pint, npm check, types green                      |
| baseline        | red             | `npm run check`: formatting only in 7 untracked .ai/docs/*.md files; tests 48/48, phpstan, pint, types green |

## Findings

| ID        | Source                | Severity | Location                                                                             | Summary                                                                                                                                      | Decision         | Status   | Iteration |
| --------- | --------------------- | -------- | ------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- | -------- | --------- |
| PV-AC-32  | plan-verifier         | PARTIAL  | app/Console/Commands/RefreshThreadsTokens.php:59-63                                  | RateLimited refresh errors fall to default logTransient (warning, no Threads code) instead of error log with code                            | fix              | verified | 5         |
| PV-DC-log | plan-verifier         | PARTIAL  | MetaCallbackController.php:30; DeleteThreadsDataForMeta.php:49                       | Matched callbacks log only matched_connections, plan says {user_id, matched_connections} (V-2 evidence)                                      | fix              | verified | 5         |
| AR-16     | architecture-reviewer | LOW      | ThreadsConnection.php:134; ConnectThreadsAccount.php:57; ThreadsCallbackTest.php:284 | No test pins that reconnect writes a new connected_at (AR-12 guarantee depends on it)                                                        | fix (tests)      | verified | 4         |
| AR-17     | architecture-reviewer | LOW      | RefreshThreadsTokens.php:48-50; RefreshThreadsTokensCommandTest.php:206              | Summary disconnect count relies on refresh() side effect; positive case untested                                                             | fix (tests)      | verified | 4         |
| AR-14     | architecture-reviewer | LOW      | app/Models/ThreadsConnection.php:128-176; ExecuteThreadsSearchRun.php:89,143         | Mid-run token refresh ends run as stopped_disconnected (token version used as identity); fix: compare connected_at                           | fix              | verified | 3         |
| AR-15     | architecture-reviewer | LOW      | app/Console/Commands/RefreshThreadsTokens.php:46-50                                  | Refresh summary overcounts disconnected after no-op decrypt CAS                                                                              | fix              | verified | 3         |
| AR-11     | architecture-reviewer | LOW      | app/Actions/Threads/ExecuteThreadsSearchRun.php:143-158                              | Posts transaction FK S-lock then keyword UPDATE can deadlock with keyword edit/delete; take keyword X lock first                             | defer            | deferred | 1         |
| AR-12     | architecture-reviewer | LOW      | app/Actions/Threads/ExecuteThreadsSearchRun.php:144-180                              | Mid-run checks only look at status; disconnect+reconnect during a run keeps old token                                                        | fix              | verified | 2         |
| AR-13     | architecture-reviewer | LOW      | ThreadsConnection.php:88-109; DisconnectThreadsAccountsForMeta.php:26                | Disconnected attribute set duplicated 3x                                                                                                     | fix              | verified | 2         |
| SEC-1     | security review       | LOW      | app/Services/Threads/SignedRequestParser.php                                         | Empty THREADS_APP_SECRET would let signed_request be forged; reject when secret empty                                                        | fix              | verified | 1         |
| AR-1      | architecture-reviewer | HIGH     | app/Actions/Threads/ExecuteThreadsSearchRun.php:61-97                                | Running search ignores disconnect/uninstall/data deletion mid-run (AC-12, AC-21)                                                             | fix (proposed)   | verified | 1         |
| AR-2      | architecture-reviewer | MEDIUM   | app/Console/Commands/RefreshThreadsTokens.php:54-70; ExecuteThreadsSearchRun.php:99  | Stale-model writes on ThreadsConnection can restore deleted token or wipe a new one                                                          | fix (proposed)   | verified | 2         |
| AR-3      | architecture-reviewer | MEDIUM   | app/Console/Commands/DispatchThreadsSearchRuns.php:30-51                             | Dispatcher lacks user-row lock; scheduled+manual run can both be active (AC-47)                                                              | fix (proposed)   | verified | 1         |
| AR-4      | architecture-reviewer | MEDIUM   | routes/console.php:13                                                                | withoutOverlapping default 24h mutex on every-minute dispatcher                                                                              | fix (proposed)   | verified | 1         |
| AR-5      | architecture-reviewer | MEDIUM   | app/Services/Threads/ThreadsClient.php:148                                           | HTTP timeouts 20s/10s vs planned 10s/5s; 20 keywords exceed job timeout                                                                      | fix (proposed)   | verified | 1         |
| AR-6      | architecture-reviewer | LOW      | app/Http/Middleware/HandleInertiaRequests.php:41                                     | auth.user serializes loaded threadsConnection relation                                                                                       | defer (proposed) | verified | 1         |
| AR-7      | architecture-reviewer | LOW      | app/Actions/Threads/StartManualSearchRun.php:24                                      | Refusal reason recomputed outside lock                                                                                                       | defer (proposed) | verified | 1         |
| AR-8      | architecture-reviewer | LOW      | app/Http/Controllers/Threads/MetaCallbackController.php:30                           | Uninstall workflow in controller, no action/lock                                                                                             | defer (proposed) | verified | 1         |
| AR-9      | architecture-reviewer | LOW      | DispatchThreadsSearchRuns/SearchStatus/...                                           | Interval fallback and due rule duplicated                                                                                                    | defer (proposed) | verified | 1         |
| AR-10     | architecture-reviewer | LOW      | app/Http/Controllers/Threads/ThreadsKeywordController.php:21                         | Double submit → unique violation 500 instead of validation error                                                                             | fix              | verified | 1         |
| CR-1      | check-runner          | —        | composer.json types:check                                                            | PHPStan crashed at 128M host memory limit                                                                                                    | fix              | fixed    | 0         |
| DT-1      | implementer WP-2      | —        | tests/Feature/Threads/ThreadsCallbackTest.php:147                                    | Asserts whole session lacks error_description value; Laravel stores full GET URL in `_previous.url`; controller never stores/displays error* | ask              | verified | 1         |

## Log

- 00:43 gate — user approved plan and section 7 items; run started (note: before the run, orchestrator edited plan lines 213/903 to accept legacy tz aliases via ALL_WITH_BC per user, and recorded approval in section 7)
- 00:47 check-runner baseline — red only on vp formatting of .ai/docs markdown; asking user
- 00:45 gate — user: continue with known docs-formatting baseline failure (not a regression)
- 00:49 implementer WP-0 — done: 6 migrations (migrated), 7 enums, 5 models + User, factories with states, stubs (501 for writes, LogicException in services), routes incl. meta-callbacks outside web group, config/queue retry_after 360, wayfinder generated; suite 48/48, pint/phpstan/types green. Needs: WP-1 markDisconnected/usableAccessToken; WP-3 scopeActive, request rules, resource. Insight: make:enum/make:trait ignore subfolders.
- 01:15 test-writer WP-T red — 24 files in tests/Feature/Threads + tests/Concerns/InteractsWithThreads.php; 3 existing tests extended. Suite 328: 60 green, 268 red (expected; 26 errors = LogicException stubs, usableAccessToken, RunThreadsSearch::failed). No blocking Step 0 needs. Tests rely on: session key threads_oauth_state, public RunThreadsSearch::failed(Throwable), dispatchSync($runId), info-level summary logs only. ~25 frontend-only IDs → manual M-UI checks.
- 01:15 launching WP-1 and WP-4 in parallel
- 01:17 implementer WP-1 — done: ThreadsClient (error classification 190>rate-limit>5xx>other, ConnectionException rethrown without previous/URL, body redaction), ThreadsConnection::markDisconnected(reason enum)/usableAccessToken(); 26 tests green; pint/phpstan green. Note: plan line 375 says markDisconnected(User) — actual signature takes ThreadsDisconnectReason.
- 01:17 launching WP-2 and WP-3 in parallel (WP-4 still running)
- 01:19 implementer WP-2 — partial: Steps 2–5 implemented, 77/78 green; DT-1 disputed. ConnectThreadsAccount returns ?ThreadsConnection (null → T-5). Delete callback synchronous in one transaction.
- 01:19 implementer WP-4 — done: Steps 13–19, types/check/build green, not run in browser; deleted TagInput, usePosts, data/posts etc. Ran `vp check --fix` which may have reformatted .ai/docs markdown (formatting only). Prop contract forwarded to WP-3.
- 01:22 implementer WP-3 — done: Steps 6–12; 290/291 Threads+updated tests green (only DT-1 fails); prop names match WP-4; pint clean. Review notes: dispatch not atomic (relies on withoutOverlapping), manual run dispatch after commit, mid-run keyword FK failure → partial.
- 01:23 implementer WP-5 — done: docker-compose queue (--timeout=330, deviation from plan's 300 to nest 300<330<360) + scheduler, README sections, wayfinder regenerated, build OK. Manual list M-UI-1..21 and V-1..V-3 ready.
- 01:24 check-runner after implement — red: CR-1 PHPStan memory crash; DT-1 known. Sending CR-1 to implementer fix (round 1/2).
- 01:24 implementer fix CR-1 — composer types:check now --memory-limit=1G (host CLI 128M); fixed real PHPStan error in UpdateThreadsKeywordRequest (findOrFail → whereKey()->firstOrFail()); phpstan 0 errors, Keyword tests 56 green. Re-running check-runner.
- check-runner rerun — only DT-1 red (ledger formatting fixed by orchestrator via vp). Snapshot after-implement=db3736fc35f1af062863a09837a9f3d04fb0efb0. Phase → review. test-writer 'after' skipped: red run left no automatable spec gaps.
- 01:31 architecture-reviewer — 0 CRITICAL / 1 HIGH / 4 MEDIUM / 5 LOW (AR-1..AR-10). HIGH AR-1: running search ignores disconnect/uninstall/deletion mid-run. Starting security review.
- security review — no HIGH/MEDIUM findings (≥0.7). Low note: empty THREADS_APP_SECRET would make signed_request forgeable (config issue). Triage: waiting for user.
- gate triage — user: fix AR-1..AR-10 and SEC-1 (all), DT-1 → narrow test. Iteration 1 started.
- 01:41 implementer fix-1 — all 11 fixed (AR-1..AR-10, SEC-1); new actions StartScheduledSearchRun, DisconnectThreadsAccountsForMeta; CAS methods on ThreadsConnection; 301/302 targeted tests green (DT-1 only); pint, phpstan green. For test-writer: AR-1,2,3,6,8,10, SEC-1.
- 01:44 test-writer update fix-1 — DT-1 narrowed (excludes _previous); new tests for AR-1,2,3,6,8,10, SEC-1; Threads+Settings 320 passed. Running check-runner.
- check-runner fix-1 — green 351/351. Snapshot fix-1. Narrow re-review (architecture + security for SEC-1).
- 01:46 security re-review fix-1 — SEC-1 resolved (SignedRequestParser.php:51-55); no new HIGH/MEDIUM findings. Waiting for architecture verification.
- architecture verification fix-1 — 10/11 resolved; AR-2 partially (2 non-CAS stale writes left). New LOW AR-11..AR-13. Triage: waiting for user.
- gate triage — user: AR-2 residual fix, AR-12 fix, AR-13 fix, AR-11 defer. Iteration 2 started.
- 01:55 implementer fix-2 — AR-2 residual, AR-12 (stillHoldsLoadedToken, top-level sharedLock read, @phpstan-impure), AR-13 fixed; 303 Threads tests green; pint/phpstan green. Note: mid-run token refresh now ends run StoppedDisconnected (possible false stop) — for re-review.
- 01:57 test-writer update fix-2 — tests for AR-2 (refresh Expired/decrypt branches, stale model) and AR-12 (reconnect mid-run); 78 passed in 3 files. Running check-runner.
- check-runner fix-2 — green 357/357. Snapshot fix-2. Narrow architecture re-review.
- architecture verification fix-2 — AR-2, AR-12, AR-13 resolved. New LOW AR-14, AR-15. Triage: waiting for user.
- gate triage — user: fix AR-14, AR-15. Iteration 3 (last) started.
- 02:02 implementer fix-3 — AR-14 (isStillSameConnection by connected_at), AR-15 fixed; 307/309 Threads (2 tests need update for changed semantics); pint/phpstan green.
- 02:03 test-writer update fix-3 — updated 3 tests for connected_at semantics; new tests AR-14 (refresh mid-run completes), AR-15 (summary count 0); Threads 311 green.
- check-runner fix-3 — green 359/359. Snapshot fix-3. Narrow architecture re-review.
- architecture verification fix-3 — AR-14, AR-15 resolved; AR-12 guarantee holds. New LOW AR-16, AR-17 (test gaps). max-fix 3/3 reached. Asking user.
- gate — user granted one extra iteration for AR-16, AR-17 (tests + PHPDoc).
- implementer fix-4 — PHPDoc invariant on ConnectThreadsAccount::handle, MySQL-only note on isStillSameConnection. test-writer fix-4 — AR-16 (connected_at on reconnect, same & different account), AR-17 (summary disconnected=1); 33 green. Snapshot fix-4; running check-runner + narrow re-review.
- architecture verification fix-4 — AR-16, AR-17 resolved; no new findings. Review loop exits (only AR-11 deferred). Waiting for check-runner.
- check-runner fix-4 — green 360/360. Phase → verify. Launching plan-verifier.
- plan-verifier — PASS WITH GAPS: no NOT MET; PARTIAL AC-32 and plan DC log fields; 24 client-only IDs NOT VERIFIABLE (M-UI-1..21), V-1..V-3 manual. Asking user.
- gate — user: fix both PV gaps; do NOT mark spec implemented. Iteration 5 started.
- 02:19 implementer fix-5 — PV-AC-32 (explicit match, RateLimited/Other → error log with code), PV-DC-log (user_id in matched callback info logs); 54 targeted green; pint/phpstan green. Note: delete info log now holds Threads user id (plan requires it; NFR-11 covers deletion records only).
- test-writer fix-5 — tests for AC-32 rate-limit error log and callback info logs; 44 green. Snapshot fix-5. Running check-runner.
- check-runner fix-5 — green 364/364. Re-running plan-verifier.
- plan-verifier re-run — PASS WITH GAPS (only NOT VERIFIABLE: M-UI-1..21, V-1..V-3); both PV items MET; no regressions. Spec stays approved (user). Recording insights.
- engineering-insights — 5 backend + 1 frontend entries appended (pure additions). Run done; nothing committed.
