# Implementation Plan: Threads account connection and keyword search

- Created: 2026-10-23 00:34
- Status: draft
- Requirements source: `.ai/docs/specs/threads/SPEC-01-threads-connection-and-keyword-search.md` (SPEC-01, approved 2026-10-23)
- Execution mode: multi-agent
- Test mode: tdd

## 1. Requirements and acceptance criteria

Restated from SPEC-01; the spec text is authoritative and is not repeated word for word here. Exact UI copy (T-n, N-n, E-n, ST-n, M-n, R-n, B-1, DLG-1) lives in the spec section "UI states"; implementers copy it from there verbatim. Every ID below is traced in section 6.

Connecting Threads (OAuth) — source: spec IDs

- [ ] AC-1 "Connect Threads" redirects to the authorization URL with client id, redirect URI, `response_type=code`, scope, fresh `state`
- [ ] AC-102 Button disabled with spinner while redirecting
- [ ] AC-2 `state` ≥ 128 bits, bound to session, single use
- [ ] AC-3 Missing / mismatched / reused `state` → no API call, no change, toast T-3
- [ ] AC-4 `error` parameter → no change, toast T-2
- [ ] AC-5 Valid callback → code exchange, long-lived exchange, `/me` (`id`, `username`, `name`), save `connected`
- [ ] AC-67 Account `connected` to another user → discard token, no change, toast T-5
- [ ] AC-6 Code exchange fails → no change, toast T-4
- [ ] AC-7 Long-lived exchange or `/me` fails → discard short token, no change, toast T-4
- [ ] AC-8 Short-lived token never written to DB, session, cache, job payloads, logs
- [ ] AC-9 Expiry = exchange time + `expires_in`; issue time = exchange time
- [ ] AC-10 Redirect to Threads tab, toast T-1, show `@username`, name, connected date, expiry date

Connection state — source: spec IDs

- [ ] AC-94 "Disconnect" shows DLG-1 first
- [ ] AC-11 Confirm → delete token, `disconnected` reason `user`, toast T-6
- [ ] AC-91 After a user disconnect, show N-5
- [ ] AC-12 No Threads request while `disconnected` or absent
- [ ] AC-13 Reconnect updates the same row (token, ids, username, name, expiry), `connected`, reason cleared, keywords and posts kept
- [ ] AC-14 Keywords and posts kept on any disconnect
- [ ] AC-15 Disconnected state on Threads tab with R-1..R-4 and "Reconnect Threads"
- [ ] AC-68 Banner B-1 on every signed-in page while `disconnected`
- [ ] AC-93 Warning N-6 while `connected` and expiring within 3 days
- [ ] AC-16 Error 190 on any call → delete token, `disconnected` reason `token_invalid`
- [ ] AC-17 Undecryptable token → same as AC-16

Meta callbacks — source: spec IDs

- [ ] AC-18 Valid uninstall → matching connections lose token, `disconnected` reason `uninstalled`
- [ ] AC-19 Valid uninstall, no match → no change, log unmatched id, HTTP 200
- [ ] AC-20 Missing / malformed / wrong algorithm / bad signature → HTTP 400, no change, warning without body
- [ ] AC-21 Valid delete → delete connections, their users' posts and runs; keep keywords and accounts; record request with unique alphanumeric code; HTTP 200 JSON `{url, confirmation_code}`
- [ ] AC-22 Valid delete, no match → log, record `completed` request, same JSON
- [ ] AC-23 Deletion finished → request `completed` with completion time
- [ ] AC-24 Known code → status block N-8
- [ ] AC-25 Unknown / malformed code → N-9
- [ ] AC-99 Data-deletion page shows "Open Account → Profile."

Token refresh — source: spec IDs

- [ ] AC-26 `threads:refresh-tokens` daily, without overlapping, on one server
- [ ] AC-27 Selects `connected`, expiring within 7 days, not expired, issued ≥ 24 h ago
- [ ] AC-28 Synchronous refresh; save token, expiry = now + `expires_in`, issue time = now
- [ ] AC-29 One failure doesn't stop the rest
- [ ] AC-30 Error 190 → delete token, `token_invalid`
- [ ] AC-31 Network / timeout / 5xx → warning with connection id, unchanged
- [ ] AC-32 Other error → error log with connection id and Threads code, unchanged
- [ ] AC-33 Already expired → delete token, `disconnected` reason `expired`

Keywords and search settings — source: spec IDs

- [ ] AC-34 Trim, collapse whitespace, lower case, per user
- [ ] AC-106 Enter saves immediately
- [ ] AC-35 Empty / control characters / > 100 chars → validation message under the field
- [ ] AC-36 Duplicate after normalisation → "This keyword already exists."
- [ ] AC-37 At cap → "You can have up to {cap} keywords with a {interval}-minute search interval."
- AC-38 removed by the spec (merged into AC-37) — nothing to implement
- [ ] AC-96 At cap → input disabled, N-3
- [ ] AC-39 Rename applies AC-34..AC-36 and resets the window (AC-49)
- [ ] AC-40 Deleted keyword excluded from runs that start later
- [ ] AC-103 Deleting keeps posts with their keyword text
- [ ] AC-41 Another user's keyword → 404, no change
- [ ] AC-42 Notice N-2 with planned queries, budget, keyword count, runs per day
- [ ] AC-43 Keyword changes accepted without a connection
- [ ] AC-69 Notice N-1 without a connection
- [ ] AC-70 Save interval (5/15/30/60, default 15) and lookback (0/1/3, default 1), toast T-8
- [ ] AC-44 Interval whose cap < keyword count → "A {interval}-minute interval allows up to {cap} keywords. Remove keywords first."
- [ ] AC-89 Hint N-4
- [ ] AC-95 Hover / focus shows last search time and count, or "Not searched yet"
- [ ] AC-92 Zero results for ≥ 7 days → hint N-7
- [ ] AC-100 No "Posts per run", no "Regions / languages"
- [ ] AC-90 Own-posts-only flag on → N-10 on Posts and Scraping tab

Search runs — source: spec IDs

- [ ] AC-45 Scheduled run every N minutes while connected, ≥ 1 keyword, not paused
- [ ] AC-46 Runs only in queued jobs
- [ ] AC-47 No second run while one is queued or running
- [ ] AC-48 One `keyword_search` per keyword with the listed parameters, no paging
- [ ] AC-49 Add / rename sets window start = change time − lookback
- [ ] AC-50 Successful query → window start = `until` − 10 min
- [ ] AC-51 Failed query → window unchanged
- [ ] AC-52 Store new posts unfiltered with all listed fields
- [ ] AC-53 Duplicate post id for the user → skip, stored post unchanged
- [ ] AC-54 Empty array → zero results recorded, continue
- [ ] AC-55 Error 190 → end run
- [ ] AC-56 Network / timeout / 5xx → warning with keyword id, continue
- [ ] AC-57 Other error → error log with keyword id and code, continue
- [ ] AC-58 Rate-limit error (4, 17, 32, 341, 613, HTTP 429, message contains "limit") → end run, `paused_quota`, pause 1 h
- [ ] AC-74 Log the full error body without token once per pause
- [ ] AC-73 No run starts while paused
- [ ] AC-59 No request when ≥ 2,000 sent in the last 24 h
- [ ] AC-60 Record trigger, start/end, queries sent, new posts, outcome
- [ ] AC-78 Dispatch `ThreadsPostsStored` (user id, new post ids) when ≥ 1 new post
- [ ] AC-104 No notifications

Manual run — source: spec IDs

- [ ] AC-71 "Run scout" queues a manual run, toast T-10
- [ ] AC-72 Refusal reasons M-1..M-6 next to the disabled button
- [ ] AC-111 While a run is active, partial reload of `searchStatus` + posts every 10 s
- [ ] AC-112 Stop polling when no run is active
- [ ] AC-113 Toast T-11 with the run's new-post count when polling sees the run end

Posts list and detail — source: spec IDs

- [ ] AC-61 Own posts only, by post time desc, 10 per page
- [ ] AC-62 Excerpt or "(no text)", `@username`, keyword, relative post time with absolute on hover, found time, "Open in Threads" new tab
- [ ] AC-82 No STATUS/SCORE column, relevance filter, "Highest score", AI subtitle, "Remote · contract opportunity"
- [ ] AC-63 Search term matches text or username
- [ ] AC-83 Keyword filter matches stored keyword text exactly
- [ ] AC-97 `search`, `keyword`, `page` in the URL
- [ ] AC-64 Page beyond last → last page
- [ ] AC-98 Table skeleton during search / filter / page visits
- [ ] AC-65 / AC-85 / AC-86 / AC-87 Empty states E-1 / E-2 / E-3 / E-4
- [ ] AC-88 Status line ST-1..ST-6
- [ ] AC-66 `threads:prune-posts` deletes posts found > 90 days ago
- [ ] AC-79 Post detail shows text, `@username`, keyword, post time, "Open in Threads"
- [ ] AC-80 Unknown or foreign post → 404
- [ ] AC-81 No relevance chip, no "Why AI marked it relevant"
- [ ] AC-110 No "Mark as relevant" (rows, Dashboard, detail), no "Suggested response"

Dashboard — source: spec IDs

- [ ] AC-75 "Threads searches" widget: used last 24 h, remaining (≥ 0), budget 2,000, from run records
- [ ] AC-76 Explanation N-11
- [ ] AC-77 Total found, found last 7 days, found today, per-day series, 5 latest by found time under "Latest posts"
- [ ] AC-109 Frontend writes the IANA zone to the `timezone` cookie on each signed-in page load
- [ ] AC-107 Valid cookie → "today" and series in that zone
- [ ] AC-108 Missing / invalid cookie → UTC
- [ ] AC-105 "Relevant posts" card, relevant series and AI alert stay mock

Non-functional, module contracts, untrusted inputs — source: spec IDs

- [ ] NFR-1 Tokens encrypted at rest (`encrypted` cast)
- [ ] NFR-2 No tokens, codes, secret or raw `signed_request` in logs, exception reports, job payloads, Inertia props
- [ ] NFR-3 Threads settings only from `config/services.php` → env
- [ ] NFR-4 Constant-time comparison of `state` and signatures
- [ ] NFR-5 One client class on the Laravel HTTP client
- [ ] NFR-6 Data visible only to its owner
- [ ] NFR-7 Status, banner reason, "Run scout" disabled reason as text
- [ ] NFR-8 One log entry per refresh run (refreshed / failed / disconnected)
- [ ] NFR-9 One log entry per search run
- [ ] NFR-10 One log entry per prune run
- [ ] NFR-11 No Threads user id or username in deletion records
- [ ] NFR-12 HTTP 429 above: 10/min/user connect+callback, 60/min/IP Meta callbacks, 30/min/user keyword writes, 10/min/user manual runs
- [ ] MC-1 .. MC-10 Module contracts as specified (Threads tab, Scraping tab, posts, account cascade, Meta callbacks, data-deletion page, scheduler, dashboard, event, shared prop)
- [ ] UI-1 .. UI-12 Untrusted-input rules as specified

User decisions recorded for this plan — source: answers to the Requirements review (2026-10-23)

- [ ] Keyword rename by clicking a chip: inline edit, Enter saves, Esc cancels — answer to question 1
- [ ] Only Enter adds a keyword; no comma-add, blur-add or Backspace-delete; delete only with the chip's × — answer to question 2
- [ ] "Suggested response template" field hidden on the Scraping tab — answer to question 3
- [ ] Dashboard: "+{n} in the last 7 days" as the change line of "Posts collected"; no change line on "New today" — answer to question 4
- [ ] `/posts` search applies as the user types, 300 ms debounce, one Inertia visit per change, URL updated — answer to question 5
- [ ] `queue` and `scheduler` services in `docker-compose.yml`, README updated — answer to question 6
- [ ] `trustProxies(at: '*')` in `bootstrap/app.php` kept unchanged — answer to question 7
- [ ] Delete callback runs synchronously in one transaction; request is `completed` immediately (accepted recommendation 1)
- [ ] `THREADS_OWN_POSTS_ONLY` defaults to `false` — answer to recommendation 2 (rejected as proposed)
- [ ] Sort select on `/posts` kept with only "Newest first" — answer to recommendation 3 (rejected as proposed)
- [ ] `threads:prune-posts` also deletes `threads_search_runs` older than 7 days and logs that count (accepted recommendation 4)
- [ ] `threads:dispatch-search-runs` every minute (`withoutOverlapping`, `onOneServer`); job `$tries = 1`, `$timeout = 300`, `failed()` closes the run as `partial`; `DB_QUEUE_RETRY_AFTER=360`; run stale 30 min after `queued_at` or 10 min after `started_at` (accepted recommendation 5)

## 2. Context

- Modules and files in scope:
    - `routes/web.php` — mock routes for dashboard, posts, settings and data-deletion become controller routes
    - `routes/console.php` — schedule entries
    - `bootstrap/app.php` — `encryptCookies(except: [...])`, routing `then:` for the Meta callback routes; contains the uncommitted `trustProxies(at: '*')` that stays as is
    - `app/Http/Middleware/HandleInertiaRequests.php` — shared `threadsConnection` prop
    - `app/Models/User.php` — new columns and relations
    - `app/Providers/AppServiceProvider.php` — named rate limiters
    - `config/services.php`, `config/queue.php`, `.env.example`
    - `docker-compose.yml`, `README.md`
    - Frontend: `resources/js/pages/{Dashboard,Posts,PostDetail}.vue`, `pages/scout-settings/{Threads,Scraping}.vue`, `pages/legal/DataDeletion.vue`, `components/settings/{ThreadsConnectionCard,ScrapingSettingsForm}.vue`, `components/TagInput.vue`, `components/posts/{PostsTable,PostsFilters}.vue`, `components/RunScoutButton.vue`, `components/ActivityChart.vue`, `components/MetricCard.vue`, `layouts/app/AppSidebarLayout.vue`, `app.ts`, `types/threads.ts`, `types/global.d.ts`, mock composables `composables/{usePosts,useScoutRun,useThreadsConnection,useScrapingSettings,useDashboardStats}.ts`, mock data `data/{posts,settings,dashboard}.ts`
- Relevant insights:
    - 2026-09-18 — `Route::inertia` can't forward route params; use controller routes (`.ai/insights/backend.md`)
    - 2026-10-21 — `docker-compose.yml` sets `APP_ENV=local`; `phpunit.xml` `<server>` fix; run tests inside the container (`.ai/insights/backend.md`)
    - 2026-10-21 — after page changes run `npm run build` before feature tests, or Vite manifest errors appear (`.ai/insights/backend.md`)
    - 2026-10-21 — agent Bash guards split on `|`; use `grep -e` (`.ai/insights/backend.md`)
    - 2026-09-18 — Wayfinder groups by first name segment; last segment must be camelCase; run `php artisan wayfinder:generate --with-form` (`.ai/insights/frontend.md`)
    - 2026-09-18 — `defineOptions({ layout: {...} })` merges props into the layout chosen in `app.ts` (`.ai/insights/frontend.md`)
    - 2026-09-18 — never put literal `{{ }}` inside template strings (`.ai/insights/frontend.md`)
    - 2026-10-21 — `/settings/*` is scout-only; account pages live under `/account/*` (`.ai/insights/frontend.md`)
- Existing code to reuse:
    - `Inertia::flash('toast', ['type' => ..., 'message' => ...])` pattern (`app/Http/Controllers/Settings/ProfileController.php:41`) and `resources/js/lib/flashToast.ts`
    - `app/Concerns/ProfileValidationRules.php` — pattern for shared validation rule traits
    - UI kit `resources/js/components/ui/*` (dialog, tooltip, alert, badge, select, spinner, table, skeleton)
    - `EmptyState.vue`, `TableSkeleton.vue`, `Pagination.vue`, `MetricCard.vue`, `ActivityChart.vue`, `Heading.vue`
    - `setCookie` approach in `composables/useAppearance.ts` (path `/`, `SameSite=Lax`)
    - Inertia v3 `usePoll` with `autoStart: false`, `start`/`stop` (inertia docs v3, "Polling")
    - `Inertia::location()` for RT-2 (409 + `X-Inertia-Location` for Inertia visits)
- Versions: Laravel 13.32, PHP 8.4, inertia-laravel 3.3.4, @inertiajs/vue3 3.7.1, Wayfinder 0.1.21, PHPUnit 12.5, MySQL 8.4 (tests use the `testing` MySQL database; `phpunit.xml` uses `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `SESSION_DRIVER=array`).
- No `.ai/rules` directory exists.
- Technical assumptions:
    - The Graph API accepts `access_token` as a query parameter on every TA-n call (confirmed by hand for TA-2..TA-4 and TA-6, S-2; TA-5 unverified → V-3).
    - `DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC)` (IANA list including backward-compatible aliases) is "the IANA identifier list" of UI-12, so legacy aliases such as `Europe/Kiev` are accepted (user decision 2026-10-23); unknown or overlong values still fall back to UTC (EC-48).
    - MySQL `CONVERT_TZ` is not usable (no time zone tables in the `mysql:8.4` image); per-day bucketing uses UTC boundaries computed in PHP.

## 3. Decisions and constraints

Architecture

- New code layout (Laravel-conventional folders created by `make:*`):
    - `app/Enums/` — `ThreadsConnectionStatus`, `ThreadsDisconnectReason`, `ThreadsSearchRunTrigger`, `ThreadsSearchRunOutcome`, `ThreadsDataDeletionStatus`, `SearchInterval` (5, 15, 30, 60 with `runsPerDay()` and `keywordCap()`), `FirstSearchLookback` (0, 1, 3)
    - `app/Models/` — `ThreadsConnection`, `ThreadsKeyword`, `ThreadsPost`, `ThreadsSearchRun`, `ThreadsDataDeletionRequest`
    - `app/Services/Threads/` — `ThreadsClient`, `ThreadsApiException`, `ThreadsErrorKind` (enum-like, but kept next to the client), `SignedRequestParser`, `InvalidSignedRequest`, `SearchBudget`, `SearchStatus`, `ClientTimezone`
    - `app/Actions/Threads/` — `ConnectThreadsAccount`, `ExecuteThreadsSearchRun`, `StartManualSearchRun`, `DeleteThreadsDataForMeta`
    - `app/Jobs/RunThreadsSearch`, `app/Events/ThreadsPostsStored`
    - `app/Console/Commands/` — `RefreshThreadsTokens` (`threads:refresh-tokens`), `DispatchThreadsSearchRuns` (`threads:dispatch-search-runs`), `PruneThreadsPosts` (`threads:prune-posts`)
    - `app/Http/Controllers/Threads/` — `ThreadsConnectionController` (RT-1..RT-4), `MetaCallbackController` (RT-5, RT-6), `ThreadsKeywordController` (RT-9..RT-11), `ScrapingSettingsController` (RT-8, RT-12), `PostController` (RT-13, RT-14), `SearchRunController` (RT-15), `DashboardController` (RT-16); `app/Http/Controllers/Legal/DataDeletionController` (RT-7)
    - `app/Http/Requests/Threads/` — `StoreThreadsKeywordRequest`, `UpdateThreadsKeywordRequest`, `UpdateSearchSettingsRequest`, `PostIndexRequest`; `app/Concerns/ThreadsKeywordValidationRules.php` shared by the two keyword requests
    - `app/Http/Resources/ThreadsPostResource` — the spec's Post shape
- Meta callbacks (RT-5, RT-6) live in a new route file `routes/meta-callbacks.php`, registered from `bootstrap/app.php` `withRouting(then: ...)` **without** the `web` group: no session, no cookies, no CSRF (spec: "no `auth`, no session, CSRF check excluded"). Throttled by the named limiter `meta-callbacks` (60/min by IP).
- Named rate limiters in `AppServiceProvider` (NFR-12): `threads-oauth` (10/min by user id, shared by RT-2 and RT-3), `meta-callbacks` (60/min by IP), `threads-keywords` (30/min by user id, shared by RT-9..RT-11), `search-runs` (10/min by user id).
- `ThreadsClient` (NFR-5): one class, Laravel `Http` client, `timeout(10)`, `connectTimeout(5)`, base URLs from `config('services.threads.*')`. Never calls `->throw()`; inspects the response and throws `ThreadsApiException` with `kind` (`TokenInvalid`, `RateLimited`, `Transient`, `Other`), `httpStatus`, `threadsCode`, and a token-free `sanitizedBody`. Classification order: 190 → rate limit (codes 4, 17, 32, 341, 613, HTTP 429, message containing "limit" case-insensitive) → 5xx / `ConnectionException` → other. Both error formats are recognised (`{"error": {...}}` and TA-2's `{error_type, code, error_message}`). Exceptions never chain the original Guzzle/cURL exception (its message contains the URL with `access_token`) and never include the URL (NFR-2, UI-11). `sanitizedBody` replaces any token value and any `access_token=...` substring with `[redacted]`.
- `ThreadsConnection` model: `access_token` uses the `encrypted` cast and is `#[Hidden]` (so it never reaches Inertia props even when the relation is serialized with `auth.user`). Methods: `markDisconnected(ThreadsDisconnectReason $reason)` (token null, status, reason, `disconnected_at`, `search_paused_until` null) and `usableAccessToken(): ?string` (catches `DecryptException` → `markDisconnected(TokenInvalid)` → null; AC-17). Every caller that needs a token goes through `usableAccessToken()`; every caller that receives `TokenInvalid` calls `markDisconnected(TokenInvalid)` (AC-16).
- OAuth `state`: `Str::random(40)` (≈238 bits) in session key `threads_oauth_state`; the callback `pull()`s it first (single use) and compares with `hash_equals` (AC-2, AC-3, NFR-4). Order in the callback: state check (T-3) → `error` parameter (T-2) → code exchange (T-4) → long-lived + `/me` (T-4) → other-user check (T-5) → save (T-1). Callback query input is validated by hand in the controller (a Form Request redirect would expose parameters and break the toast flow); invalid shapes map to T-3 (state) or T-4 (code). `error*` values are never displayed (UI-1).
- AC-67 race safety: the save runs in a DB transaction that `lockForUpdate()`s the rows with that `threads_user_id`.
- Uninstall (AC-18) changes only rows with status `connected`; repeated callbacks change nothing (EC-15). Every valid callback logs at info level `{user_id, matched_connections}` — this is the token-free evidence V-2 needs; unmatched ids log a warning (AC-19, AC-22).
- Delete callback (AC-21..AC-23, accepted recommendation 1): one transaction deletes posts and runs of the matched users, deletes the matched connections, creates the deletion request with a `Str::random(16)` code retried on collision, and sets it `completed` with `completed_at`. Matches all rows with that `threads_user_id`, including `disconnected` rows of earlier users (user confirmed the spec as written).
- Signed request (MC-5, AC-20, UI-2): reject length > 8,192 before decoding; split on `.`; base64url-decode both parts; JSON payload must have `algorithm` equal (case-insensitive) to `HMAC-SHA256` and a non-empty `user_id`; `hash_equals(hash_hmac('sha256', $encodedPayload, $secret, true), $signature)`. Any failure → 400 with a warning that has no body.
- Search scheduling (accepted recommendation 5): `threads:dispatch-search-runs` every minute (`withoutOverlapping()`, `onOneServer()`). It first closes stale runs (outcome `partial`, `finished_at` now), then picks users with a `connected` connection, ≥ 1 keyword, not paused, no active run, and due (`last scheduled queued_at + interval ≤ now`, or no scheduled run yet); creates a `scheduled` run row and dispatches `RunThreadsSearch($runId)`.
- Active run definition (AC-47, EC-46): `finished_at IS NULL AND ((started_at IS NULL AND queued_at > now − 30 min) OR (started_at > now − 10 min))`, as a `ThreadsSearchRun::scopeActive`. `runState` = `queued` when the active run has no `started_at`, `running` otherwise.
- `RunThreadsSearch` job: payload only the run id (NFR-2, AC-8); `$tries = 1`; `$timeout = 300`; `failed()` reloads the run and, if not finished, closes it as `partial`. `config/queue.php` database `retry_after` default becomes 360 and `.env.example` gets `DB_QUEUE_RETRY_AFTER=360`.
- `ExecuteThreadsSearchRun` algorithm: exit if the run is finished or no longer active; set `started_at`; `until` = `started_at`; no usable token / not connected → `stopped_disconnected`; paused → `paused_quota` with 0 queries (AC-73 guard); keywords loaded once at start (AC-40). Per keyword: if rolling count ≥ 2,000 → `budget_reached`, stop (AC-59); `since` = max(window start, 1688540400) and < `until`; increment and save `queries_sent` before each request (live rolling count); handle result per AC-50..AC-58. Outcome: break outcome, else `partial` if any keyword failed, else `completed`. Then `finished_at`, `posts_stored`, event (AC-78), one info log (NFR-9).
- Post storage: skip items without `id` or parseable `timestamp` (UI-6, warning with keyword id); permalink kept only for `https` on `threads.com`, `www.threads.com`, `threads.net`, `www.threads.net` (UI-5); skip ids already stored for the user (AC-53); insert each new post and treat a unique-constraint violation as a duplicate (race safety); collect new ids.
- `zero_results_since`: set to now on an empty result when null; cleared by a non-empty result; reset to null on rename (data model note). AC-92's frontend rule: hint when `zeroResultsSince ≤ now − 7 days`.
- Rolling count (AC-59, AC-72, AC-75): `SearchBudget::queriesUsedLast24Hours(User)` = sum of `queries_sent` of the user's runs with `started_at ≥ now − 24 h`. Keyword cap: `min(20, intdiv(2000, intdiv(1440, interval)))`.
- Manual run (AC-71, AC-72): `StartManualSearchRun` locks the user row (`lockForUpdate`) in a transaction, checks M-1..M-6 in that order, creates a `manual` run and dispatches the job. Refusals return `redirect()->back()` with an error toast whose `{time}` is formatted `H:i` in the `ClientTimezone` zone (UTC fallback).
- `SearchStatus::for(User)` builds the spec's `searchStatus` object: `nextRunAt` = last scheduled `queued_at` + interval (or now when none) only when connected, has keywords and not paused; `manualRunBlockedReason` / `manualRunAvailableAt` from the same checks as `StartManualSearchRun`.
- Partial reloads (spec note under routes): every prop of RT-13 and RT-16 is passed as a closure so `only: ['searchStatus', 'posts']` / `['searchStatus', 'latestPosts']` computes only those.
- `/posts` (AC-61..AC-64, UI-8, UI-10): `PostIndexRequest` validates `search` and `keyword` (nullable string ≤ 100 → 422), `page` integer ≥ 1. `LIKE` term escapes `\`, `%`, `_`; matches `text` or `username`. Order `posted_at desc, id desc`. Page clamped to the last page before paginating (AC-64). Filter values passed back as `filters`.
- Dashboard (AC-75, AC-77, AC-107, AC-108, UI-12): `ClientTimezone::fromRequest()` reads the `timezone` cookie, accepts it only if ≤ 64 chars and `in_array(..., DateTimeZone::listIdentifiers(), true)`, else `UTC`, never throws. Per-day series: 30 day boundaries computed in that zone, converted to UTC, one `SELECT SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) ...` query. "Today" uses the same boundary.
- Keyword normalisation (AC-34, AC-35, UI-3): the raw value is rejected when it contains `\p{Cc}` characters; then trimmed, inner whitespace collapsed (`/\s+/u` → one space), lower-cased with `mb_strtolower`; empty after normalisation → required error; `max:100`; uniqueness via `Rule::unique('threads_keywords', 'keyword')->where('user_id', ...)` (`ignore` on update); cap via a closure rule (store only). Both keyword columns use `utf8mb4_bin` collation (spec: byte-for-byte).
- Keyword and post route parameters are resolved through the user's relation (`$request->user()->threadsKeywords()->findOrFail($id)`), giving 404 for other users (AC-41, AC-80, NFR-6).
- Disconnected banner and notices are computed on the frontend from the shared `threadsConnection` prop and page props; the server sends only the spec's prop contracts (no extra props).
- Frontend status line precedence (AC-88): ST-4 disconnected → ST-6 run active → ST-2 paused → ST-3 last outcome `budget_reached` → ST-5 last outcome `partial` → ST-1.
- N-5 (AC-91) is shown while the connection is `disconnected` with reason `user`.
- N-6 (AC-93) is computed on the frontend from `tokenExpiresAt` (≤ 3 days ahead).
- Polling (AC-111..AC-113): composable `useSearchRunPolling(only)` wraps `usePoll(10000, { only }, { autoStart: false, mode: 'rest' })`; starts when `runState !== 'idle'`, stops when it becomes `idle`; when it observes the transition to idle (or a new `lastRunId`) it shows T-11 with `lastRunPostsStored`.
- Timezone cookie (AC-109): `initializeTimezoneCookie()` in `app.ts` writes `timezone=<Intl zone>; path=/; max-age=1 year; SameSite=Lax` on every full page load; `timezone` added to `encryptCookies(except: ...)`.
- `ownPostsOnly` comes from `config('services.threads.own_posts_only')`, env `THREADS_OWN_POSTS_ONLY`, default `false`.
- Config `services.threads`: `client_id` (`THREADS_APP_ID`), `client_secret` (`THREADS_APP_SECRET`), `redirect_uri` (`THREADS_REDIRECT_URI`, default `APP_URL` + `/auth/threads/callback`), `authorize_url` (`THREADS_AUTHORIZE_URL`, default `https://threads.net/oauth/authorize`), `graph_url` (`THREADS_GRAPH_URL`, default `https://graph.threads.net`), `own_posts_only`.
- Mock cleanup: mock-only composables and data that lose all consumers are deleted (`usePosts.ts`, `useScoutRun.ts`, `useThreadsConnection.ts`, `useScrapingSettings.ts`, `data/posts.ts`, mock parts of `data/settings.ts`). `useDashboardStats.ts` / `data/dashboard.ts` keep only the mock relevant-posts value and series (AC-105). AI components (`RelevanceScore.vue`, `PostStatusBadge.vue`, `MarkRelevantButton.vue`) stay in the repo unused for the later AI spec; the AI alert keeps using `useAiSettings`.

Clarifications from the user (2026-10-23)

- Rename UX → chip click opens inline edit; Enter saves (PATCH), Esc cancels.
- Keyword input keys → only Enter adds; comma-add, blur-add and Backspace-delete removed; delete only via ×.
- "Suggested response template" → hidden.
- Dashboard "found in last 7 days" → change line "+{n} in the last 7 days" on "Posts collected"; "New today" has no change line.
- `/posts` search → as-you-type with 300 ms debounce, one Inertia visit per change, URL updated.
- Docker → add `queue` and `scheduler` services, update README.
- `trustProxies(at: '*')` → kept unchanged; per-IP throttle bypass accepted as a risk against NFR-12.
- Delete callback scope → confirmed as written (earlier disconnected users with the same Threads account lose their posts and runs too).

Recommendations

- R1 synchronous delete callback in one transaction — accepted
- R2 `THREADS_OWN_POSTS_ONLY` default `true` — rejected (flag kept, default `false`)
- R3 remove the `/posts` sort select — rejected (select kept with only "Newest first")
- R4 prune `threads_search_runs` older than 7 days in `threads:prune-posts` — accepted
- R5 dispatcher command, job limits, `DB_QUEUE_RETRY_AFTER=360`, stale-run thresholds — accepted

Out of scope

- App Review, publishing, AI filtering, notifications, response template, pagination of `keyword_search`, re-checking stored posts, revoking at Meta on disconnect (spec non-goals).
- Changing `trustProxies(at: '*')` (user decision).
- A JS test runner (no new dependency); frontend-only criteria get manual checks (Step 20).

## 4. Steps

### Step 0: Skeleton for tests [backend]

- Requirements: technical: tests for every spec ID must compile and run; existing tests must keep their baseline
- Files:
    - create migrations (`php artisan make:migration --no-interaction`, in this order): `add_threads_search_settings_to_users_table`, `create_threads_connections_table`, `create_threads_keywords_table`, `create_threads_posts_table`, `create_threads_search_runs_table`, `create_threads_data_deletion_requests_table` — columns, types, defaults, collations (`utf8mb4_bin` on both `keyword` columns), FKs (cascade / set null) and indexes exactly as the spec's "Data model"
    - create enums in `app/Enums/` (see section 3)
    - create models with factories (`php artisan make:model -f --no-interaction`): `ThreadsConnection` (casts: `access_token` `encrypted`, datetimes, enums; `#[Hidden(['access_token'])]`), `ThreadsKeyword`, `ThreadsPost`, `ThreadsSearchRun`, `ThreadsDataDeletionRequest`; relations per spec "Relationships"; `User` gets `threadsConnection()`, `threadsKeywords()`, `threadsPosts()`, `threadsSearchRuns()`, the two new columns in `#[Fillable]` and integer casts
    - factory states: `ThreadsConnectionFactory` `disconnected(ThreadsDisconnectReason)`, `expiresAt(...)`, `issuedAt(...)`, `paused(...)`; `ThreadsKeywordFactory` `searched(...)`, `zeroResultsSince(...)`; `ThreadsPostFactory` `withoutText()`, `foundAt(...)`; `ThreadsSearchRunFactory` `queued()`, `running()`, `finished(ThreadsSearchRunOutcome)`, `manual()`, `scheduled()`, `startedAt(...)`; `ThreadsDataDeletionRequestFactory` `pending()`, `completed()`
    - modify `config/services.php` (`threads` block), `config/queue.php` (database `retry_after` default 360), `.env.example` (`THREADS_*` keys with `THREADS_OWN_POSTS_ONLY=false`, `DB_QUEUE_RETRY_AFTER=360`)
    - modify `routes/web.php`: all RT routes with names and middleware from the spec (named limiters instead of `throttle:N,1`); create `routes/meta-callbacks.php` (RT-5, RT-6); modify `bootstrap/app.php`: `then:` registration of `routes/meta-callbacks.php` with `throttle:meta-callbacks`, add `timezone` to `encryptCookies(except: [...])`; do **not** touch the `trustProxies` line
    - modify `app/Providers/AppServiceProvider.php`: the four named rate limiters
    - create controllers (section 3). POST/PATCH/DELETE methods and RT-2/RT-3 bodies are `abort(501)`. GET page methods (RT-1, RT-7, RT-8, RT-13, RT-14, RT-16) render the **existing** component with no new props (`RT-14` keeps passing `postId`), so the existing page tests stay green
    - create Form Requests (classes with `authorize(): true`, empty `rules()`), `ThreadsPostResource` (empty `toArray` returning `[]`)
    - create service and action classes with their public method signatures, bodies `throw new \LogicException('Not implemented.');`: `ThreadsClient` (`exchangeCode`, `exchangeForLongLivedToken`, `me`, `refreshToken`, `keywordSearch`), `ThreadsApiException`, `SignedRequestParser`, `InvalidSignedRequest`, `SearchBudget`, `SearchStatus`, `ClientTimezone`, the four actions
    - create `app/Events/ThreadsPostsStored` (public readonly `int $userId`, `array $postIds`), `app/Jobs/RunThreadsSearch` (constructor `int $searchRunId`, `$tries`, `$timeout`, empty `handle`)
    - create the three commands (`php artisan make:command --no-interaction`) with their signatures; `handle()` returns `self::SUCCESS` and does nothing
    - modify `routes/console.php`: `threads:refresh-tokens` and `threads:prune-posts` `->daily()->withoutOverlapping()->onOneServer()`; `threads:dispatch-search-runs` `->everyMinute()->withoutOverlapping()->onOneServer()`
- Do: only declarations, schema, routing and factories; no behaviour any AC describes (the rate limiter definitions are the only exception, because the routes can't resolve without them).
- Skills: `laravel-best-practices` (migrations, routing, config, eloquent, scheduling), `wayfinder-development`
- Generate: `php artisan migrate --no-interaction` (inside the container), `php artisan wayfinder:generate --with-form`
- Tests: none: red tests are written by test-writer after this step
- Verify: `php artisan route:list --except-vendor --path=auth/threads`, `php artisan route:list --except-vendor --path=settings`, `.claude/scripts/run-tests.sh` limited to `tests/Feature/DashboardTest.php tests/Feature/LegalPagesTest.php tests/Feature/Settings/SettingsUrlSplitTest.php`, `vendor/bin/pint --dirty --format agent`

### Red tests — test-writer

- Writes: every test named in Steps 1–12 and in section 6, from SPEC-01, in `tests/Feature/Threads/` (one class per file named below); uses `Http::preventStrayRequests()` with exact-endpoint `Http::fake`, `Queue::fake([RunThreadsSearch::class])`, `Event::fake([ThreadsPostsStored::class])`, `Notification::fake()`, `Log` spies, `$this->travelTo(...)`. Signed requests are built in a test helper with the configured test secret.
- Also updates existing tests:
    - `tests/Feature/DashboardTest.php::test_authenticated_users_can_visit_the_dashboard` — assert component `Dashboard` and the RT-16 prop keys
    - `tests/Feature/LegalPagesTest.php::test_guest_can_view_the_data_deletion_instructions` — assert `deletionRequest` null and `codeNotFound` false without a code
    - `tests/Feature/Settings/SettingsUrlSplitTest.php::test_scraping_settings_page_renders_under_settings_url` — assert the RT-8 prop keys (`keywords`, `searchSettings`, `searchBudget`, `ownPostsOnly`)
- Done when: every new test is red for a missing behaviour (501, `LogicException`, missing prop), not for a compile error; tests not touched keep their baseline.

### Step 1: ThreadsClient and connection token helpers [backend]

- Requirements: NFR-5, NFR-2, NFR-3, UI-11, AC-16, AC-17, NFR-1; technical: error classification used by Steps 2, 5, 8
- Files: modify `app/Services/Threads/ThreadsClient.php`, `app/Services/Threads/ThreadsApiException.php`, `app/Services/Threads/ThreadsErrorKind.php` (create), `app/Models/ThreadsConnection.php`
- Do: implement TA-2..TA-6 calls and their return shapes (array shapes in PHPDoc); error classification and redaction as in section 3; `keywordSearch` sends exactly `q`, `search_type=RECENT`, `limit=100`, `since`, `until`, `fields=id,text,permalink,timestamp,username`, `access_token`, and returns `data` only (no paging); `markDisconnected()` and `usableAccessToken()` on `ThreadsConnection`.
- Skills: `laravel-best-practices` (http-client, security, error-handling)
- Generate: none
- Tests:
    - `tests/Feature/Threads/ThreadsClientTest.php::test_keyword_search_sends_the_documented_parameters_without_paging`
    - `::test_error_190_is_classified_as_token_invalid`
    - `::test_rate_limit_codes_http_429_and_limit_messages_are_classified_as_rate_limited` (data provider: 4, 17, 32, 341, 613, 429, "Application request limit reached")
    - `::test_server_errors_and_connection_failures_are_classified_as_transient`
    - `::test_other_errors_are_classified_as_other_with_the_threads_code`
    - `::test_code_exchange_error_format_is_recognised`
    - `::test_exception_message_and_body_never_contain_the_access_token`
    - `tests/Feature/Threads/ThreadsConnectionTokenTest.php::test_access_token_is_encrypted_at_rest`
    - `::test_access_token_is_hidden_from_serialization`
    - `::test_undecryptable_token_disconnects_with_reason_token_invalid`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ThreadsClientTest.php tests/Feature/Threads/ThreadsConnectionTokenTest.php`

### Step 2: OAuth connect and callback [backend]

- Requirements: AC-1, AC-2, AC-3, AC-4, AC-5, AC-6, AC-7, AC-8, AC-9, AC-10, AC-13, AC-67, UI-1, NFR-4, NFR-12, MC-1
- Files: modify `app/Http/Controllers/Threads/ThreadsConnectionController.php` (`connect`, `callback`), `app/Actions/Threads/ConnectThreadsAccount.php`
- Do: RT-2 builds the authorization URL from config, stores `state`, returns `Inertia::location($url)`. RT-3 follows the order and rules in section 3; `ConnectThreadsAccount` performs TA-2 → TA-3 → TA-4, keeps the short-lived token in a local variable only, applies AC-67 under a row lock, then `updateOrCreate` on `user_id` (AC-13: same row; clears reason, `disconnected_at`, `search_paused_until`; sets `connected_at`, `token_issued_at`, `token_expires_at`). Toasts via `Inertia::flash`.
- Skills: `laravel-best-practices` (security, http-client, eloquent), `inertia-vue-development` (server side `Inertia::location`, flash)
- Generate: none
- Tests (`tests/Feature/Threads/ThreadsConnectTest.php`):
    - `::test_connect_redirects_to_the_threads_authorization_url_with_required_parameters`
    - `::test_connect_returns_inertia_location_for_inertia_visits`
    - `::test_each_connect_stores_a_new_random_state_in_the_session`
    - `::test_connect_and_callback_are_throttled_per_user`
    - `::test_guests_cannot_start_a_connection`
- Tests (`tests/Feature/Threads/ThreadsCallbackTest.php`):
    - `::test_valid_callback_saves_a_connected_connection_and_flashes_success`
    - `::test_token_expiry_and_issue_time_come_from_the_exchange_time`
    - `::test_missing_state_makes_no_api_call_and_flashes_verification_error`
    - `::test_mismatched_state_makes_no_api_call_and_flashes_verification_error`
    - `::test_state_is_accepted_for_one_callback_only`
    - `::test_error_parameter_leaves_the_connection_unchanged_and_never_echoes_it`
    - `::test_failed_code_exchange_flashes_connection_failed`
    - `::test_failed_long_lived_exchange_flashes_connection_failed`
    - `::test_failed_profile_call_flashes_connection_failed`
    - `::test_account_connected_to_another_user_is_rejected`
    - `::test_account_disconnected_from_another_user_can_be_connected`
    - `::test_reconnecting_with_a_different_account_updates_the_same_row_and_keeps_keywords_and_posts`
    - `::test_short_lived_token_is_never_stored_logged_or_put_in_the_session`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ThreadsConnectTest.php tests/Feature/Threads/ThreadsCallbackTest.php`

### Step 3: Threads tab, disconnect and shared connection prop [backend]

- Requirements: AC-10, AC-11, AC-12, AC-14, AC-15, AC-68, AC-91, AC-93, NFR-2, NFR-6, MC-1, MC-10
- Files: modify `app/Http/Controllers/Threads/ThreadsConnectionController.php` (`show`, `destroy`), `app/Http/Middleware/HandleInertiaRequests.php`
- Do: RT-1 `connection` prop per spec (ISO 8601 dates); RT-4 uses `markDisconnected(User)` and T-6, or T-9 with no change when there is no `connected` connection; shared prop `threadsConnection` as a closure (`null | {status, disconnectReason, username}`), only for authenticated users.
- Skills: `laravel-best-practices`, `inertia-vue-development`
- Generate: none
- Tests (`tests/Feature/Threads/ThreadsSettingsPageTest.php`):
    - `::test_threads_tab_shows_connected_account_details`
    - `::test_threads_tab_shows_disconnected_state_with_reason`
    - `::test_threads_tab_has_null_connection_when_never_connected`
    - `::test_threads_tab_props_never_contain_the_access_token`
- Tests (`tests/Feature/Threads/ThreadsDisconnectTest.php`):
    - `::test_disconnect_deletes_the_token_and_sets_reason_user`
    - `::test_disconnect_keeps_keywords_and_posts`
    - `::test_disconnect_without_a_connection_flashes_not_connected_and_changes_nothing`
- Tests (`tests/Feature/Threads/ThreadsSharedPropTest.php`):
    - `::test_disconnected_connection_is_shared_on_every_signed_in_page`
    - `::test_shared_connection_is_null_without_a_connection`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ThreadsSettingsPageTest.php tests/Feature/Threads/ThreadsDisconnectTest.php tests/Feature/Threads/ThreadsSharedPropTest.php`

### Step 4: Meta uninstall and delete callbacks, data-deletion status [backend]

- Requirements: AC-18, AC-19, AC-20, AC-21, AC-22, AC-23, AC-24, AC-25, NFR-2, NFR-4, NFR-11, NFR-12, UI-2, UI-7, MC-5, MC-6
- Files: modify `app/Http/Controllers/Threads/MetaCallbackController.php`, `app/Services/Threads/SignedRequestParser.php`, `app/Services/Threads/InvalidSignedRequest.php`, `app/Actions/Threads/DeleteThreadsDataForMeta.php`, `app/Http/Controllers/Legal/DataDeletionController.php`
- Do: as in section 3. RT-6 JSON `{"url": url('/data-deletion').'?code='.$code, "confirmation_code": $code}`. RT-7: a `code` that isn't 1–32 `[A-Za-z0-9]` → `codeNotFound: true` without a query (UI-7); known → `deletionRequest` props; unknown → `codeNotFound: true`; no code → both empty.
- Skills: `laravel-best-practices` (security, eloquent, routing)
- Generate: none
- Tests (`tests/Feature/Threads/MetaUninstallCallbackTest.php`):
    - `::test_valid_uninstall_disconnects_matching_connections_with_reason_uninstalled`
    - `::test_repeated_uninstall_changes_nothing`
    - `::test_unmatched_uninstall_logs_the_id_and_returns_200`
    - `::test_invalid_signed_requests_return_400_and_change_nothing` (data provider: missing, malformed, wrong algorithm, bad signature, over 8,192 characters)
    - `::test_rejected_signed_request_warning_does_not_contain_the_body`
    - `::test_meta_callbacks_need_no_session_or_csrf_token`
    - `::test_meta_callbacks_are_throttled_per_ip`
- Tests (`tests/Feature/Threads/MetaDataDeletionCallbackTest.php`):
    - `::test_valid_delete_removes_connections_posts_and_runs_and_keeps_keywords_and_users`
    - `::test_valid_delete_returns_status_url_and_a_new_alphanumeric_code_each_time`
    - `::test_valid_delete_marks_the_request_completed`
    - `::test_unmatched_delete_records_a_completed_request_and_returns_the_same_shape`
    - `::test_deletion_request_stores_no_threads_user_id_or_username`
    - `::test_delete_also_removes_data_of_earlier_disconnected_users_of_the_same_account`
- Tests (`tests/Feature/Threads/DataDeletionPageTest.php`):
    - `::test_known_code_shows_the_deletion_request_status`
    - `::test_unknown_code_sets_code_not_found`
    - `::test_malformed_code_sets_code_not_found_without_querying` (asserts no query on `threads_data_deletion_requests` via `DB::listen` or query count)
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/MetaUninstallCallbackTest.php tests/Feature/Threads/MetaDataDeletionCallbackTest.php tests/Feature/Threads/DataDeletionPageTest.php tests/Feature/LegalPagesTest.php`

### Step 5: `threads:refresh-tokens` [backend]

- Requirements: AC-26, AC-27, AC-28, AC-29, AC-30, AC-31, AC-32, AC-33, AC-17, NFR-8, MC-7
- Files: modify `app/Console/Commands/RefreshThreadsTokens.php`
- Do: iterate `connected` connections with `token_expires_at` ≤ now + 7 days (lazy by id); expired → `markDisconnected(Expired)`; else if `token_issued_at` ≤ now − 24 h → TA-5 via `usableAccessToken()`; per-connection try/catch by `kind`; counters and one summary info log.
- Skills: `laravel-best-practices` (scheduling, error-handling, http-client)
- Generate: none
- Tests (`tests/Feature/Threads/RefreshThreadsTokensCommandTest.php`):
    - `::test_refreshes_connections_expiring_within_seven_days_and_issued_a_day_ago`
    - `::test_skips_connections_not_expiring_soon_issued_recently_or_disconnected`
    - `::test_saves_the_new_token_expiry_and_issue_time`
    - `::test_continues_after_one_connection_fails`
    - `::test_error_190_disconnects_with_reason_token_invalid`
    - `::test_transient_failure_logs_a_warning_and_leaves_the_connection_unchanged`
    - `::test_other_error_logs_an_error_with_the_threads_code`
    - `::test_expired_token_disconnects_with_reason_expired`
    - `::test_undecryptable_token_disconnects_with_reason_token_invalid`
    - `::test_writes_one_summary_log_entry`
- Tests (`tests/Feature/Threads/ThreadsScheduleTest.php`):
    - `::test_refresh_and_prune_commands_run_daily_without_overlapping_on_one_server`
    - `::test_search_dispatcher_runs_every_minute_without_overlapping_on_one_server`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/RefreshThreadsTokensCommandTest.php tests/Feature/Threads/ThreadsScheduleTest.php`

### Step 6: Keywords, search settings and the Scraping page props [backend]

- Requirements: AC-34, AC-35, AC-36, AC-37, AC-39, AC-40, AC-41, AC-42, AC-43, AC-44, AC-49, AC-70, AC-90, AC-95, AC-103, UI-3, UI-9, NFR-6, NFR-12, MC-2
- Files: modify `app/Http/Controllers/Threads/ThreadsKeywordController.php`, `app/Http/Controllers/Threads/ScrapingSettingsController.php`, `app/Http/Requests/Threads/StoreThreadsKeywordRequest.php`, `UpdateThreadsKeywordRequest.php`, `UpdateSearchSettingsRequest.php`; create `app/Concerns/ThreadsKeywordValidationRules.php`; modify `app/Models/ThreadsKeyword.php`, `app/Enums/SearchInterval.php`
- Do: validation and normalisation as in section 3, with the spec's exact messages; store / rename set `search_window_start` = now − lookback days (AC-49) and rename resets `last_searched_at`, `last_result_count`, `zero_results_since`; delete leaves posts (FK set null, AC-103); RT-8 props per spec (`searchBudget` from `SearchInterval` and keyword count; `ownPostsOnly` from config); RT-12 with the AC-44 cap check; toasts T-7 / T-8. Back redirects with 303 for Inertia.
- Skills: `laravel-best-practices` (validation, eloquent, routing)
- Generate: none
- Tests (`tests/Feature/Threads/ThreadsKeywordTest.php`):
    - `::test_adding_a_keyword_normalises_and_saves_it_for_the_user`
    - `::test_keyword_validation_rejects_empty_control_characters_and_over_100_characters` (data provider)
    - `::test_duplicate_keyword_after_normalisation_is_rejected`
    - `::test_keyword_comparison_is_byte_for_byte` (`cafe` vs `café` both accepted)
    - `::test_keyword_cap_depends_on_the_search_interval` (data provider: 5 → 6, 15 → 20)
    - `::test_new_keyword_window_starts_at_now_minus_the_lookback` (data provider 0, 1, 3)
    - `::test_renaming_applies_normalisation_and_resets_the_window`
    - `::test_renaming_to_its_own_value_is_allowed_and_to_another_existing_keyword_is_rejected`
    - `::test_deleting_a_keyword_keeps_its_posts_with_the_keyword_text`
    - `::test_other_users_keyword_returns_404_on_update_and_delete`
    - `::test_keywords_can_be_changed_without_a_connection`
    - `::test_keyword_writes_are_throttled_per_user`
- Tests (`tests/Feature/Threads/SearchSettingsTest.php`):
    - `::test_search_settings_are_saved`
    - `::test_new_users_default_to_15_minutes_and_one_day_lookback`
    - `::test_interval_and_lookback_outside_the_allowed_sets_are_rejected` (data provider)
    - `::test_interval_with_a_cap_below_the_keyword_count_is_rejected_with_the_spec_message`
- Tests (`tests/Feature/Threads/ScrapingSettingsPageTest.php`):
    - `::test_scraping_page_lists_only_the_users_keywords_with_search_status`
    - `::test_search_budget_shows_planned_queries_runs_per_day_and_cap`
    - `::test_own_posts_only_flag_is_passed_from_config`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ThreadsKeywordTest.php tests/Feature/Threads/SearchSettingsTest.php tests/Feature/Threads/ScrapingSettingsPageTest.php tests/Feature/Settings/SettingsUrlSplitTest.php`

### Step 7: Search budget and search status services [backend]

- Requirements: AC-47, AC-59, AC-72, AC-75, AC-88, NFR-6; technical: EC-46 stale-run rule
- Files: modify `app/Services/Threads/SearchBudget.php`, `app/Services/Threads/SearchStatus.php`, `app/Models/ThreadsSearchRun.php` (`scopeActive`, `scopeStale`)
- Do: rolling count, remaining (≥ 0), active / stale definitions, `searchStatus` array exactly per the spec shape, manual-run blocking reasons in M-1..M-6 order. Tests for these run through Steps 8–11 endpoints and commands.
- Skills: `laravel-best-practices` (eloquent, db-performance)
- Generate: none
- Tests: covered by the tests in Steps 8, 9, 10, 11 (behaviour is only observable through them)
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ManualSearchRunTest.php` after Step 9; until then `php -l` via Pint: `vendor/bin/pint --dirty --format agent`

### Step 8: Search run job [backend]

- Requirements: AC-12, AC-16, AC-40, AC-46, AC-48, AC-50, AC-51, AC-52, AC-53, AC-54, AC-55, AC-56, AC-57, AC-58, AC-59, AC-60, AC-73, AC-74, AC-78, AC-92, AC-95, AC-104, NFR-2, NFR-9, UI-5, UI-6, MC-9
- Files: modify `app/Jobs/RunThreadsSearch.php`, `app/Actions/Threads/ExecuteThreadsSearchRun.php`, `app/Models/ThreadsPost.php`, `app/Events/ThreadsPostsStored.php` (if needed)
- Do: algorithm and storage rules from section 3; pause on rate limit sets `search_paused_until = now + 1 h` and logs the sanitized body once (the run ends at the first rate-limit error, and no run starts while paused, so one log per pause); `failed()` closes the run as `partial`.
- Skills: `laravel-best-practices` (queue-jobs, http-client, events-notifications, eloquent, error-handling)
- Generate: none
- Tests (`tests/Feature/Threads/RunThreadsSearchJobTest.php`):
    - `::test_sends_one_keyword_search_per_keyword_with_window_and_run_start`
    - `::test_successful_query_moves_the_window_to_until_minus_ten_minutes`
    - `::test_failed_query_keeps_the_window`
    - `::test_stores_new_posts_with_all_fields_and_owner`
    - `::test_skips_posts_already_stored_for_the_user_and_keeps_their_keyword`
    - `::test_same_post_id_for_another_user_is_stored`
    - `::test_empty_result_records_zero_results_and_continues`
    - `::test_zero_results_since_is_set_on_first_empty_result_and_cleared_by_a_non_empty_one`
    - `::test_records_last_searched_at_and_result_count`
    - `::test_error_190_disconnects_and_ends_the_run`
    - `::test_transient_error_logs_a_warning_and_continues`
    - `::test_other_error_logs_an_error_and_continues`
    - `::test_rate_limit_ends_the_run_pauses_for_one_hour_and_logs_the_body_once_without_token`
    - `::test_stops_with_budget_reached_at_2000_queries_in_the_last_24_hours`
    - `::test_records_trigger_times_counts_and_outcome` (data provider over outcomes `completed`, `partial`, `paused_quota`, `budget_reached`, `stopped_disconnected`)
    - `::test_disconnected_or_missing_connection_sends_no_request`
    - `::test_paused_user_sends_no_request`
    - `::test_keyword_deleted_before_the_run_starts_is_not_searched`
    - `::test_dispatches_posts_stored_event_with_new_post_ids_only_when_posts_were_stored`
    - `::test_sends_no_notifications`
    - `::test_invalid_permalinks_are_stored_as_null` (data provider: `javascript:`, `http://threads.net/...`, `https://evil.com/...`)
    - `::test_items_without_id_or_timestamp_are_skipped_with_a_warning`
    - `::test_job_payload_contains_only_the_run_id`
    - `::test_failed_job_closes_the_run_as_partial`
    - `::test_writes_one_summary_log_entry_per_run`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/RunThreadsSearchJobTest.php`

### Step 9: Scheduled dispatch and manual runs [backend]

- Requirements: AC-45, AC-46, AC-47, AC-71, AC-72, AC-73, NFR-12, MC-3, MC-7; technical: EC-46
- Files: modify `app/Console/Commands/DispatchThreadsSearchRuns.php`, `app/Actions/Threads/StartManualSearchRun.php`, `app/Http/Controllers/Threads/SearchRunController.php`
- Do: as in section 3; RT-15 flashes T-10 or an error toast with M-1..M-6.
- Skills: `laravel-best-practices` (scheduling, queue-jobs)
- Generate: none
- Tests (`tests/Feature/Threads/DispatchThreadsSearchRunsCommandTest.php`):
    - `::test_queues_a_run_for_users_due_by_their_interval`
    - `::test_does_not_queue_before_the_interval_has_passed`
    - `::test_skips_users_without_connection_keywords_or_while_paused`
    - `::test_does_not_queue_while_a_run_is_queued_or_running`
    - `::test_closes_stale_runs_as_partial_and_queues_a_new_one`
- Tests (`tests/Feature/Threads/ManualSearchRunTest.php`):
    - `::test_run_scout_queues_a_manual_run_and_flashes_search_started`
    - `::test_run_scout_is_refused_with_the_matching_reason` (data provider: M-1 not connected, M-2 no keywords, M-3 run active, M-4 cooldown 15 min, M-5 paused, M-6 budget below keyword count)
    - `::test_run_scout_never_calls_threads_inside_the_request`
    - `::test_manual_runs_are_throttled_per_user`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/DispatchThreadsSearchRunsCommandTest.php tests/Feature/Threads/ManualSearchRunTest.php`

### Step 10: Posts list and post detail [backend]

- Requirements: AC-61, AC-63, AC-64, AC-79, AC-80, AC-83, AC-88, AC-97, AC-111, UI-8, UI-10, NFR-6, MC-3
- Files: modify `app/Http/Controllers/Threads/PostController.php`, `app/Http/Requests/Threads/PostIndexRequest.php`, `app/Http/Resources/ThreadsPostResource.php`
- Do: RT-13 props per spec as closures (`posts`, `filters`, `keywordOptions`, `keywordCount`, `searchStatus`, `ownPostsOnly`); RT-14 renders `PostDetail` with `post` (resource) and 404 for foreign or missing posts. Remove the old `postId` prop.
- Skills: `laravel-best-practices` (eloquent, advanced-queries, validation), `inertia-vue-development`
- Generate: none
- Tests (`tests/Feature/Threads/PostsIndexTest.php`):
    - `::test_lists_only_the_users_posts_newest_post_first_ten_per_page`
    - `::test_search_matches_text_or_username`
    - `::test_search_treats_percent_and_underscore_literally`
    - `::test_keyword_filter_matches_the_stored_keyword_exactly`
    - `::test_filters_are_returned_for_the_url`
    - `::test_page_beyond_the_last_shows_the_last_page`
    - `::test_overlong_search_or_keyword_is_rejected` (422)
    - `::test_keyword_options_and_count_are_the_users_current_keywords`
    - `::test_search_status_reports_queued_running_and_idle_states`
    - `::test_partial_reload_returns_only_search_status_and_posts`
- Tests (`tests/Feature/Threads/PostShowTest.php`):
    - `::test_shows_the_users_post`
    - `::test_other_users_or_missing_post_returns_404`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/PostsIndexTest.php tests/Feature/Threads/PostShowTest.php`

### Step 11: Dashboard backend and time zone [backend]

- Requirements: AC-75, AC-77, AC-107, AC-108, AC-111, UI-12, MC-8, NFR-6
- Files: modify `app/Http/Controllers/Threads/DashboardController.php`, `app/Services/Threads/ClientTimezone.php`
- Do: RT-16 props per spec as closures; `threadsDailyLimit: 2200`; `activity` = 30 days ending today in the resolved zone; `latestPosts` = 5 newest by `created_at`.
- Skills: `laravel-best-practices` (db-performance, eloquent)
- Generate: none
- Tests (`tests/Feature/Threads/DashboardThreadsTest.php`):
    - `::test_quota_widget_counts_queries_of_runs_started_in_the_last_24_hours`
    - `::test_remaining_queries_never_drop_below_zero`
    - `::test_stats_count_total_last_seven_days_and_today`
    - `::test_today_and_daily_series_use_the_timezone_cookie`
    - `::test_missing_or_invalid_timezone_cookie_falls_back_to_utc` (data provider: none, `Mars/Base`, 500 characters)
    - `::test_latest_posts_are_the_five_most_recently_found`
    - `::test_dashboard_shows_only_the_users_data`
    - `::test_partial_reload_returns_only_search_status_and_latest_posts`
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/DashboardThreadsTest.php tests/Feature/DashboardTest.php`

### Step 12: `threads:prune-posts` [backend]

- Requirements: AC-66, NFR-10, MC-7, accepted recommendation 4
- Files: modify `app/Console/Commands/PruneThreadsPosts.php`
- Do: delete posts with `created_at < now − 90 days` and runs with `queued_at < now − 7 days` in chunks; one info log with both counts.
- Skills: `laravel-best-practices` (scheduling, db-performance)
- Generate: none
- Tests (`tests/Feature/Threads/PruneThreadsPostsCommandTest.php`):
    - `::test_deletes_posts_found_more_than_90_days_ago_by_found_time`
    - `::test_deletes_search_runs_older_than_seven_days_and_keeps_recent_ones`
    - `::test_writes_one_log_entry_with_the_counts`
- Tests (`tests/Feature/Threads/AccountDeletionCascadeTest.php`):
    - `::test_deleting_the_account_removes_connection_keywords_posts_and_runs` (MC-4; fits the data-cleanup area, owned by WP-3)
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/PruneThreadsPostsCommandTest.php tests/Feature/Threads/AccountDeletionCascadeTest.php`

### Step 13: Frontend foundations: types, shared prop, time zone cookie, banner [frontend]

- Requirements: AC-68, AC-109, NFR-7, MC-10, UI-4
- Files: modify `resources/js/types/threads.ts` (add `ThreadsConnectionSummary`, `ThreadsConnectionDetails`, `ThreadsDisconnectReason`, `SearchStatus`, `ThreadsPost`, `PaginatedPosts`, `ScrapingKeyword`, `SearchSettings`, `SearchBudget`, `ThreadsQuota`, `DashboardThreadsStats`, `FoundActivityPoint`, `DeletionRequest`, per the spec's prop contracts; remove mock-only types that lose consumers), `resources/js/types/global.d.ts` (`threadsConnection` in `sharedPageProps`), `resources/js/app.ts`; create `resources/js/lib/timezoneCookie.ts`, `resources/js/lib/threadsCopy.ts` (R-1..R-4, M-1..M-6 texts keyed by reason), `resources/js/components/ThreadsDisconnectedBanner.vue`; modify `resources/js/layouts/app/AppSidebarLayout.vue` (banner above the slot)
- Do: banner B-1 with reason text and a Wayfinder link to `settings.threads`; cookie writer as in section 3. Text rendering only through interpolation (no `v-html`, UI-4).
- Skills: `inertia-vue-development`, `wayfinder-development`, `tailwindcss-development`
- Generate: none (Wayfinder output from Step 0)
- Tests: none: frontend-only behaviour without a JS test runner; manual checks M-UI-1, M-UI-2 in Step 20; the server half is covered by `ThreadsSharedPropTest`
- Verify: `npm run types:check`

### Step 14: Threads tab UI [frontend]

- Requirements: AC-1, AC-10, AC-15, AC-91, AC-93, AC-94, AC-102, NFR-7
- Files: modify `resources/js/pages/scout-settings/Threads.vue`, `resources/js/components/settings/ThreadsConnectionCard.vue`; delete `resources/js/composables/useThreadsConnection.ts`; trim `resources/js/data/settings.ts`
- Do: card driven by the `connection` prop: states "Not connected", "Connected" (Name row hidden when null), "Disconnected" + R-n + "Reconnect Threads", N-6, N-5, DLG-1 (ui `dialog`) before `router.delete(threads.disconnect())`; "Connect Threads" posts to `threads.connect()` and stays disabled with spinner until the browser leaves (AC-102). Copy verbatim from the spec.
- Skills: `inertia-vue-development`, `wayfinder-development`, `tailwindcss-development`
- Generate: none
- Tests: none: UI-only; server behaviour covered by Steps 2–3 tests; manual checks M-UI-3..M-UI-5
- Verify: `npm run types:check`

### Step 15: Scraping tab UI [frontend]

- Requirements: AC-34, AC-35, AC-36, AC-37, AC-39, AC-40, AC-42, AC-43, AC-69, AC-70, AC-44, AC-89, AC-90, AC-92, AC-95, AC-96, AC-100, AC-106, user answers 1–3
- Files: modify `resources/js/pages/scout-settings/Scraping.vue`, `resources/js/components/settings/ScrapingSettingsForm.vue` (search settings only: interval and lookback selects, "Save settings"), `resources/js/components/TagInput.vue` (or replace with `resources/js/components/settings/KeywordsEditor.vue` if `TagInput` has no other consumer — implementer checks with grep); delete `resources/js/composables/useScrapingSettings.ts`
- Do: keyword editor with server writes: Enter → `router.post(store)`; chip click → inline input, Enter → `router.patch(update)`, Esc cancels; × → `router.delete(destroy)`; no comma/blur add, no Backspace delete; validation error under the field from the `keyword` error key; input disabled + N-3 at cap; N-1 (with link) when the shared connection isn't `connected`; N-2, N-4, N-10 (when `ownPostsOnly`); tooltip (ui `tooltip`, also on focus) "Last searched {time} · {n} posts" or "Not searched yet"; N-7 marker when `zeroResultsSince` ≤ now − 7 days; remove "Posts per run", "Regions / languages" and the template field.
- Skills: `inertia-vue-development`, `wayfinder-development`, `tailwindcss-development`
- Generate: none
- Tests: none: UI-only; server behaviour covered by Step 6 tests; manual checks M-UI-6..M-UI-10
- Verify: `npm run types:check`

### Step 16: "Run scout", status line and polling [frontend]

- Requirements: AC-71, AC-72, AC-88, AC-111, AC-112, AC-113, NFR-7
- Files: modify `resources/js/components/RunScoutButton.vue`; create `resources/js/composables/useSearchRunPolling.ts`, `resources/js/components/posts/SearchStatusLine.vue`; delete `resources/js/composables/useScoutRun.ts`
- Do: button takes `searchStatus`; "Running…" + disabled while a run is active; disabled reason text (M-1..M-6 with `manualRunAvailableAt` / `pausedUntil` formatted in the browser) next to it as text; click → `router.post(searchRuns.store(), {}, { preserveScroll: true })`; status line precedence from section 3; polling composable as in section 3.
- Skills: `inertia-vue-development`, `wayfinder-development`, `tailwindcss-development`
- Generate: none
- Tests: none: UI-only; server side covered by `ManualSearchRunTest`, `PostsIndexTest::test_partial_reload_returns_only_search_status_and_posts`, `DashboardThreadsTest::test_partial_reload_returns_only_search_status_and_latest_posts`; manual checks M-UI-11..M-UI-13
- Verify: `npm run types:check`

### Step 17: Posts page and post detail UI [frontend]

- Requirements: AC-61, AC-62, AC-63, AC-64, AC-65, AC-79, AC-81, AC-82, AC-83, AC-85, AC-86, AC-87, AC-88, AC-90, AC-97, AC-98, AC-110, AC-111, user answer 5, R3 decision
- Files: modify `resources/js/pages/Posts.vue`, `resources/js/components/posts/PostsFilters.vue`, `resources/js/components/posts/PostsTable.vue`, `resources/js/pages/PostDetail.vue`; delete `resources/js/composables/usePosts.ts`, `resources/js/data/posts.ts` (if no consumer remains)
- Do: filters → `router.get(posts.index(), {search, keyword, page}, { preserveState: true, replace: true })`, search debounced 300 ms, keyword select from `keywordOptions` ("All keywords" = null), sort select with only "Newest first"; `TableSkeleton` while a filter / search / page visit is in flight (`onStart` / `onFinish`), not during polling; table columns: post excerpt or "(no text)", `@username`, keyword, post time (relative, absolute in `title`), found time, "Open in Threads" (only when `permalink`, `target="_blank" rel="noopener noreferrer"`); empty states E-1..E-4 with their buttons (E-4 "Clear filters" visits without query); status line and N-10 above the table; "Run scout" with polling `only: ['searchStatus', 'posts']`; `PostsTable` `dashboard` variant used by Step 18. Post detail: "Back to posts", `@username` · post time, text, keyword chip, "Open in Threads"; no AI blocks, no "Mark as relevant", no "Suggested response".
- Skills: `inertia-vue-development`, `wayfinder-development`, `tailwindcss-development`
- Generate: none
- Tests: none: UI-only; server behaviour covered by Step 10 tests; manual checks M-UI-14..M-UI-18
- Verify: `npm run types:check`

### Step 18: Dashboard UI [frontend]

- Requirements: AC-75, AC-76, AC-77, AC-105, AC-110, AC-111, user answer 4
- Files: modify `resources/js/pages/Dashboard.vue`, `resources/js/composables/useDashboardStats.ts` (only the mock relevant value and series), `resources/js/data/dashboard.ts`, `resources/js/components/ActivityChart.vue` (accept merged real `found` + mock `relevant`); create `resources/js/components/ThreadsQuotaCard.vue`
- Do: cards "Posts collected" = `stats.postsFound` with change "+{foundLast7Days} in the last 7 days"; "Relevant posts" mock unchanged; "New today" = `stats.foundToday`, no change line; chart: 7 / 30-day selector slices `activity`, relevant series stays mock; quota widget with "{used} used in the last 24 hours" · "{remaining} left" · "Budget 2,000" and N-11; "Latest posts" heading with `PostsTable` dashboard variant (no "Mark as relevant"); AI alert unchanged; "Run scout" + polling `only: ['searchStatus', 'latestPosts']`.
- Skills: `inertia-vue-development`, `tailwindcss-development`
- Generate: none
- Tests: none: UI-only; server behaviour covered by Step 11 tests; manual checks M-UI-19, M-UI-20
- Verify: `npm run types:check`

### Step 19: Data-deletion page UI [frontend]

- Requirements: AC-24, AC-25, AC-99
- Files: modify `resources/js/pages/legal/DataDeletion.vue`
- Do: status block N-8 when `deletionRequest` is set ("Status: Pending" or "Status: Completed on {date}"), N-9 when `codeNotFound`; account step text "Open Account → Profile."
- Skills: `inertia-vue-development`, `tailwindcss-development`
- Generate: none
- Tests: none: copy and layout; server props covered by `DataDeletionPageTest`; manual check M-UI-21
- Verify: `npm run types:check`

### Step 20: Docker, README, final regeneration and manual verification [infra]

- Requirements: AC-26, AC-45, MC-7 (operability), user answer 6, V-1, V-2, V-3, manual checks for frontend-only criteria
- Files: modify `docker-compose.yml` (services `queue`: same `image: threads-scraper/app`, `command: php artisan queue:work --tries=1 --timeout=300`, same volumes, network, `APP_ENV`, `depends_on: mysql (service_healthy)`; `scheduler`: same, `command: php artisan schedule:work`), `README.md` (the two services, the `THREADS_*` env keys, `DB_QUEUE_RETRY_AFTER=360`, that search and refresh need both services)
- Do:
    1. Run `php artisan wayfinder:generate --with-form`, `npm run build` (refreshes the Vite manifest for feature tests, backend insight 2026-10-21), `vendor/bin/pint --dirty --format agent`.
    2. Hand over to `check-runner` for the full suite, PHPStan and `npm run check`.
    3. Record in the run ledger the manual checks below as "to be done by the user" with their result column; they do not block completion.
- Manual checks for frontend-only criteria (browser, `docker compose up -d`, `npm run build`):
    - M-UI-1 AC-68, NFR-7: with a `disconnected` connection, B-1 with reason text and link shows on Dashboard, Posts, Settings and Account pages
    - M-UI-2 AC-109, EC-47: DevTools shows the `timezone` cookie (`path=/`, `SameSite=Lax`, plain text) after the first page load
    - M-UI-3 AC-102: "Connect Threads" disables with spinner until Threads opens
    - M-UI-4 AC-94, AC-91: DLG-1 appears; after confirming, T-6 and N-5 show
    - M-UI-5 AC-93: with `token_expires_at` set to now + 2 days, N-6 and "Reconnect Threads" show
    - M-UI-6 AC-106, user answer 2: Enter adds; comma, blur and Backspace do nothing to saved keywords; × deletes
    - M-UI-7 user answer 1: chip click edits inline; Enter saves (T-7 "Keyword updated."); Esc cancels
    - M-UI-8 AC-96, AC-42, AC-89: at the cap the input is disabled with N-3; N-2 numbers match the budget table; N-4 shows
    - M-UI-9 AC-95, AC-92: tooltip on hover and keyboard focus; N-7 marker with `zero_results_since` 8 days ago
    - M-UI-10 AC-100, AC-69, AC-90, user answer 3: no "Posts per run", "Regions / languages" or template field; N-1 without a connection; N-10 with `THREADS_OWN_POSTS_ONLY=true`
    - M-UI-11 AC-71, AC-72, NFR-7: "Run scout" shows T-10; each M-n reason shows as text next to the disabled button
    - M-UI-12 AC-111, AC-112, EC-51: Network tab shows partial reloads every 10 s only while a run is active and only on Dashboard / Posts; they stop after the run ends or when leaving the page
    - M-UI-13 AC-113: T-11 with the right count when the run ends
    - M-UI-14 AC-62, UI-4: a post with `<script>` text renders as literal text; "(no text)" for media-only; link opens in a new tab; no link without permalink
    - M-UI-15 AC-82, R3 decision: no STATUS/SCORE column, relevance filter, "Highest score", AI subtitle or "Remote · contract opportunity"; sort select shows only "Newest first"
    - M-UI-16 AC-97, AC-98, user answer 5: typing updates the URL after ~300 ms with one request; back button restores search, keyword and page; skeleton during those visits
    - M-UI-17 AC-65, AC-85, AC-86, AC-87, AC-88: E-1..E-4 and ST-1..ST-6 in their states
    - M-UI-18 AC-81, AC-110: post detail without relevance chip, AI reasons, "Mark as relevant" or "Suggested response"
    - M-UI-19 AC-76, AC-105, user answer 4: quota widget with N-11; relevant card, relevant series and AI alert still mock; "+{n} in the last 7 days" on "Posts collected"; no change line on "New today"
    - M-UI-20 AC-77, AC-107: "New today" and the chart change when the browser time zone moves across midnight
    - M-UI-21 AC-24, AC-25, AC-99: `/data-deletion?code=<known>` shows N-8; unknown and malformed codes show N-9; account step reads "Open Account → Profile."
- Verification notes (manual, non-blocking):
    - V-1: keep the `queue` and `scheduler` services running with the tester account; when a rate-limit pause starts, read the AC-74 log entry and record the real HTTP status, code, subcode and message in the run ledger. If it differs from AC-58, ask the user to revise the spec with `spec-creator` (do not change the classification in code without a spec change).
    - V-2: remove the app in Threads with `@elrondhalfelven`; read the info log `{user_id, matched_connections}` from the uninstall callback and confirm `user_id` equals `29580006461583503` and `matched_connections` is 1. A mismatch goes back to `spec-creator` (D-15).
    - V-3: once the tester token is ≥ 24 hours old, set its `token_expires_at` to now + 6 days (manual DB edit by the user), run `php artisan threads:refresh-tokens` and confirm the summary log shows 1 refreshed and the new `token_expires_at` is about now + 60 days.
- Skills: `laravel-best-practices` (config, queue-jobs, scheduling)
- Generate: `php artisan wayfinder:generate --with-form`, `npm run build`
- Tests: none: infrastructure and manual checks
- Verify: `docker compose config --services` lists `laravel.test`, `mysql`, `queue`, `scheduler`

## 5. Order, dependencies and execution

- Step 0 comes first: every test and every later step needs the schema, routes, factories and generated Wayfinder routes.
- Red tests depend on Step 0.
- Step 1 (client, token helpers) is used by Steps 2, 5 and 8, so it runs alone before them.
- Steps 2–5 (WP-2), Steps 6–12 (WP-3) and Steps 13–19 (WP-4) own disjoint files and run in parallel. WP-4 depends only on Step 0 (generated routes) and the spec's prop contracts.
- Inside WP-3: Step 7 before Steps 8–11; Step 8 before Step 9.
- Step 20 runs last because it rebuilds the frontend and regenerates routes over the whole change.

| Package | Agent       | Steps      | Owns files                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | Depends on                                                           | Parallel with    |
| ------- | ----------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- | ---------------- |
| WP-0    | implementer | 0          | `database/migrations/*threads*`, users migration, `database/factories/Threads*`, `app/Enums/*`, `app/Models/Threads*`, `app/Models/User.php`, all new controller / request / resource / service / action / job / event / command class files (stub versions), `routes/web.php`, `routes/meta-callbacks.php`, `routes/console.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `config/services.php`, `config/queue.php`, `.env.example`, generated `resources/js/{routes,actions,wayfinder}`                                                                                                                                                                                        | —                                                                    | —                |
| WP-T    | test-writer | red        | `tests/Feature/Threads/*`, `tests/Feature/DashboardTest.php`, `tests/Feature/LegalPagesTest.php`, `tests/Feature/Settings/SettingsUrlSplitTest.php`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | WP-0                                                                 | —                |
| WP-1    | implementer | 1          | `app/Services/Threads/{ThreadsClient,ThreadsApiException,ThreadsErrorKind}.php`, `app/Models/ThreadsConnection.php`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | WP-T                                                                 | —                |
| WP-2    | implementer | 2, 3, 4, 5 | `app/Http/Controllers/Threads/{ThreadsConnectionController,MetaCallbackController}.php`, `app/Http/Controllers/Legal/DataDeletionController.php`, `app/Actions/Threads/{ConnectThreadsAccount,DeleteThreadsDataForMeta}.php`, `app/Services/Threads/{SignedRequestParser,InvalidSignedRequest}.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Console/Commands/RefreshThreadsTokens.php`, `app/Models/ThreadsDataDeletionRequest.php`                                                                                                                                                                                                                                                    | WP-1                                                                 | WP-3, WP-4       |
| WP-3    | implementer | 6–12       | `app/Http/Controllers/Threads/{ThreadsKeywordController,ScrapingSettingsController,PostController,SearchRunController,DashboardController}.php`, `app/Http/Requests/Threads/*`, `app/Concerns/ThreadsKeywordValidationRules.php`, `app/Http/Resources/ThreadsPostResource.php`, `app/Services/Threads/{SearchBudget,SearchStatus,ClientTimezone}.php`, `app/Actions/Threads/{ExecuteThreadsSearchRun,StartManualSearchRun}.php`, `app/Jobs/RunThreadsSearch.php`, `app/Events/ThreadsPostsStored.php`, `app/Console/Commands/{DispatchThreadsSearchRuns,PruneThreadsPosts}.php`, `app/Models/{ThreadsKeyword,ThreadsPost,ThreadsSearchRun}.php`, `app/Enums/{SearchInterval,FirstSearchLookback}.php` | WP-1                                                                 | WP-2, WP-4       |
| WP-4    | implementer | 13–19      | `resources/js/**` except generated `routes/`, `actions/`, `wayfinder/`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | WP-0 (and WP-T finished, to keep agents sequential with test-writer) | WP-1, WP-2, WP-3 |
| WP-5    | implementer | 20         | `docker-compose.yml`, `README.md`, regenerated `resources/js/{routes,actions,wayfinder}`, `public/build`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | WP-2, WP-3, WP-4                                                     | —                |

Note: WP-4 can start as soon as WP-T is done, so it runs in parallel with WP-1 as well as with WP-2 and WP-3. If a model method another package needs is missing (for example a relation), the owning package adds it; the requesting package reports it instead of editing a file it does not own.

Final checks over the whole change: WP-5, then `check-runner` (full suite, PHPStan, `npm run check`, `npm run types:check`).

## 6. Traceability

Test paths are under `tests/Feature/Threads/` unless written in full. "Manual M-UI-n" refers to the checks in Step 20.

| Spec ID | Steps              | Tests                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| ------- | ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| AC-1    | 2, 14              | `ThreadsConnectTest::test_connect_redirects_to_the_threads_authorization_url_with_required_parameters`, `::test_connect_returns_inertia_location_for_inertia_visits`                                                                                                                                                                                                                                                                                                                                               |
| AC-102  | 14                 | none: UI-only, no JS test runner; manual M-UI-3                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| AC-2    | 2                  | `ThreadsConnectTest::test_each_connect_stores_a_new_random_state_in_the_session`, `ThreadsCallbackTest::test_state_is_accepted_for_one_callback_only`                                                                                                                                                                                                                                                                                                                                                              |
| AC-3    | 2                  | `ThreadsCallbackTest::test_missing_state_makes_no_api_call_and_flashes_verification_error`, `::test_mismatched_state_makes_no_api_call_and_flashes_verification_error`, `::test_state_is_accepted_for_one_callback_only`                                                                                                                                                                                                                                                                                           |
| AC-4    | 2                  | `ThreadsCallbackTest::test_error_parameter_leaves_the_connection_unchanged_and_never_echoes_it`                                                                                                                                                                                                                                                                                                                                                                                                                    |
| AC-5    | 2                  | `ThreadsCallbackTest::test_valid_callback_saves_a_connected_connection_and_flashes_success`                                                                                                                                                                                                                                                                                                                                                                                                                        |
| AC-67   | 2                  | `ThreadsCallbackTest::test_account_connected_to_another_user_is_rejected`, `::test_account_disconnected_from_another_user_can_be_connected`                                                                                                                                                                                                                                                                                                                                                                        |
| AC-6    | 2                  | `ThreadsCallbackTest::test_failed_code_exchange_flashes_connection_failed`                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| AC-7    | 2                  | `ThreadsCallbackTest::test_failed_long_lived_exchange_flashes_connection_failed`, `::test_failed_profile_call_flashes_connection_failed`                                                                                                                                                                                                                                                                                                                                                                           |
| AC-8    | 2, 8               | `ThreadsCallbackTest::test_short_lived_token_is_never_stored_logged_or_put_in_the_session`, `RunThreadsSearchJobTest::test_job_payload_contains_only_the_run_id`                                                                                                                                                                                                                                                                                                                                                   |
| AC-9    | 2                  | `ThreadsCallbackTest::test_token_expiry_and_issue_time_come_from_the_exchange_time`                                                                                                                                                                                                                                                                                                                                                                                                                                |
| AC-10   | 2, 3, 14           | `ThreadsCallbackTest::test_valid_callback_saves_a_connected_connection_and_flashes_success`, `ThreadsSettingsPageTest::test_threads_tab_shows_connected_account_details`                                                                                                                                                                                                                                                                                                                                           |
| AC-94   | 14                 | none: UI-only; manual M-UI-4                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-11   | 3, 14              | `ThreadsDisconnectTest::test_disconnect_deletes_the_token_and_sets_reason_user`                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| AC-91   | 3, 14              | `ThreadsSettingsPageTest::test_threads_tab_shows_disconnected_state_with_reason` (reason `user` in props); manual M-UI-4                                                                                                                                                                                                                                                                                                                                                                                           |
| AC-12   | 3, 8, 9            | `RunThreadsSearchJobTest::test_disconnected_or_missing_connection_sends_no_request`, `DispatchThreadsSearchRunsCommandTest::test_skips_users_without_connection_keywords_or_while_paused`, `RefreshThreadsTokensCommandTest::test_skips_connections_not_expiring_soon_issued_recently_or_disconnected`                                                                                                                                                                                                             |
| AC-13   | 2                  | `ThreadsCallbackTest::test_reconnecting_with_a_different_account_updates_the_same_row_and_keeps_keywords_and_posts`                                                                                                                                                                                                                                                                                                                                                                                                |
| AC-14   | 3, 4, 5, 8         | `ThreadsDisconnectTest::test_disconnect_keeps_keywords_and_posts`, `MetaUninstallCallbackTest::test_valid_uninstall_disconnects_matching_connections_with_reason_uninstalled`                                                                                                                                                                                                                                                                                                                                      |
| AC-15   | 3, 14              | `ThreadsSettingsPageTest::test_threads_tab_shows_disconnected_state_with_reason`; manual M-UI-4                                                                                                                                                                                                                                                                                                                                                                                                                    |
| AC-68   | 3, 13              | `ThreadsSharedPropTest::test_disconnected_connection_is_shared_on_every_signed_in_page`; manual M-UI-1                                                                                                                                                                                                                                                                                                                                                                                                             |
| AC-93   | 14                 | `ThreadsSettingsPageTest::test_threads_tab_shows_connected_account_details` (`tokenExpiresAt` prop); manual M-UI-5                                                                                                                                                                                                                                                                                                                                                                                                 |
| AC-16   | 1, 5, 8            | `RunThreadsSearchJobTest::test_error_190_disconnects_and_ends_the_run`, `RefreshThreadsTokensCommandTest::test_error_190_disconnects_with_reason_token_invalid`                                                                                                                                                                                                                                                                                                                                                    |
| AC-17   | 1, 5, 8            | `ThreadsConnectionTokenTest::test_undecryptable_token_disconnects_with_reason_token_invalid`, `RefreshThreadsTokensCommandTest::test_undecryptable_token_disconnects_with_reason_token_invalid`                                                                                                                                                                                                                                                                                                                    |
| AC-18   | 4                  | `MetaUninstallCallbackTest::test_valid_uninstall_disconnects_matching_connections_with_reason_uninstalled`, `::test_repeated_uninstall_changes_nothing`                                                                                                                                                                                                                                                                                                                                                            |
| AC-19   | 4                  | `MetaUninstallCallbackTest::test_unmatched_uninstall_logs_the_id_and_returns_200`                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| AC-20   | 4                  | `MetaUninstallCallbackTest::test_invalid_signed_requests_return_400_and_change_nothing`, `::test_rejected_signed_request_warning_does_not_contain_the_body`                                                                                                                                                                                                                                                                                                                                                        |
| AC-21   | 4                  | `MetaDataDeletionCallbackTest::test_valid_delete_removes_connections_posts_and_runs_and_keeps_keywords_and_users`, `::test_valid_delete_returns_status_url_and_a_new_alphanumeric_code_each_time`, `::test_delete_also_removes_data_of_earlier_disconnected_users_of_the_same_account`                                                                                                                                                                                                                             |
| AC-22   | 4                  | `MetaDataDeletionCallbackTest::test_unmatched_delete_records_a_completed_request_and_returns_the_same_shape`                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-23   | 4                  | `MetaDataDeletionCallbackTest::test_valid_delete_marks_the_request_completed`                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| AC-24   | 4, 19              | `DataDeletionPageTest::test_known_code_shows_the_deletion_request_status`; manual M-UI-21                                                                                                                                                                                                                                                                                                                                                                                                                          |
| AC-25   | 4, 19              | `DataDeletionPageTest::test_unknown_code_sets_code_not_found`, `::test_malformed_code_sets_code_not_found_without_querying`                                                                                                                                                                                                                                                                                                                                                                                        |
| AC-99   | 19                 | none: static copy; manual M-UI-21                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| AC-26   | 0, 5               | `ThreadsScheduleTest::test_refresh_and_prune_commands_run_daily_without_overlapping_on_one_server`                                                                                                                                                                                                                                                                                                                                                                                                                 |
| AC-27   | 5                  | `RefreshThreadsTokensCommandTest::test_refreshes_connections_expiring_within_seven_days_and_issued_a_day_ago`, `::test_skips_connections_not_expiring_soon_issued_recently_or_disconnected`                                                                                                                                                                                                                                                                                                                        |
| AC-28   | 5                  | `RefreshThreadsTokensCommandTest::test_saves_the_new_token_expiry_and_issue_time`                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| AC-29   | 5                  | `RefreshThreadsTokensCommandTest::test_continues_after_one_connection_fails`                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-30   | 5                  | `RefreshThreadsTokensCommandTest::test_error_190_disconnects_with_reason_token_invalid`                                                                                                                                                                                                                                                                                                                                                                                                                            |
| AC-31   | 5                  | `RefreshThreadsTokensCommandTest::test_transient_failure_logs_a_warning_and_leaves_the_connection_unchanged`                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-32   | 5                  | `RefreshThreadsTokensCommandTest::test_other_error_logs_an_error_with_the_threads_code`                                                                                                                                                                                                                                                                                                                                                                                                                            |
| AC-33   | 5                  | `RefreshThreadsTokensCommandTest::test_expired_token_disconnects_with_reason_expired`                                                                                                                                                                                                                                                                                                                                                                                                                              |
| AC-34   | 6, 15              | `ThreadsKeywordTest::test_adding_a_keyword_normalises_and_saves_it_for_the_user`                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| AC-106  | 15                 | none: UI-only; manual M-UI-6                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-35   | 6, 15              | `ThreadsKeywordTest::test_keyword_validation_rejects_empty_control_characters_and_over_100_characters`                                                                                                                                                                                                                                                                                                                                                                                                             |
| AC-36   | 6, 15              | `ThreadsKeywordTest::test_duplicate_keyword_after_normalisation_is_rejected`, `::test_keyword_comparison_is_byte_for_byte`                                                                                                                                                                                                                                                                                                                                                                                         |
| AC-37   | 6, 15              | `ThreadsKeywordTest::test_keyword_cap_depends_on_the_search_interval`                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| AC-38   | —                  | none: removed by the spec (merged into AC-37)                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| AC-96   | 15                 | `ScrapingSettingsPageTest::test_search_budget_shows_planned_queries_runs_per_day_and_cap` (cap and count props); manual M-UI-8                                                                                                                                                                                                                                                                                                                                                                                     |
| AC-39   | 6, 15              | `ThreadsKeywordTest::test_renaming_applies_normalisation_and_resets_the_window`, `::test_renaming_to_its_own_value_is_allowed_and_to_another_existing_keyword_is_rejected`                                                                                                                                                                                                                                                                                                                                         |
| AC-40   | 6, 8               | `RunThreadsSearchJobTest::test_keyword_deleted_before_the_run_starts_is_not_searched`                                                                                                                                                                                                                                                                                                                                                                                                                              |
| AC-103  | 6                  | `ThreadsKeywordTest::test_deleting_a_keyword_keeps_its_posts_with_the_keyword_text`                                                                                                                                                                                                                                                                                                                                                                                                                                |
| AC-41   | 6                  | `ThreadsKeywordTest::test_other_users_keyword_returns_404_on_update_and_delete`                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| AC-42   | 6, 15              | `ScrapingSettingsPageTest::test_search_budget_shows_planned_queries_runs_per_day_and_cap`; manual M-UI-8                                                                                                                                                                                                                                                                                                                                                                                                           |
| AC-43   | 6                  | `ThreadsKeywordTest::test_keywords_can_be_changed_without_a_connection`                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| AC-69   | 15                 | none: UI-only (driven by the shared prop tested in `ThreadsSharedPropTest`); manual M-UI-10                                                                                                                                                                                                                                                                                                                                                                                                                        |
| AC-70   | 6, 15              | `SearchSettingsTest::test_search_settings_are_saved`, `::test_new_users_default_to_15_minutes_and_one_day_lookback`                                                                                                                                                                                                                                                                                                                                                                                                |
| AC-44   | 6                  | `SearchSettingsTest::test_interval_with_a_cap_below_the_keyword_count_is_rejected_with_the_spec_message`                                                                                                                                                                                                                                                                                                                                                                                                           |
| AC-89   | 15                 | none: static copy; manual M-UI-8                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| AC-95   | 6, 8, 15           | `ScrapingSettingsPageTest::test_scraping_page_lists_only_the_users_keywords_with_search_status`, `RunThreadsSearchJobTest::test_records_last_searched_at_and_result_count`; manual M-UI-9                                                                                                                                                                                                                                                                                                                          |
| AC-92   | 8, 15              | `RunThreadsSearchJobTest::test_zero_results_since_is_set_on_first_empty_result_and_cleared_by_a_non_empty_one`; manual M-UI-9                                                                                                                                                                                                                                                                                                                                                                                      |
| AC-100  | 15                 | none: removed fields (layout); manual M-UI-10                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| AC-90   | 6, 10, 15, 17      | `ScrapingSettingsPageTest::test_own_posts_only_flag_is_passed_from_config`; manual M-UI-10                                                                                                                                                                                                                                                                                                                                                                                                                         |
| AC-45   | 9                  | `DispatchThreadsSearchRunsCommandTest::test_queues_a_run_for_users_due_by_their_interval`, `::test_does_not_queue_before_the_interval_has_passed`, `::test_skips_users_without_connection_keywords_or_while_paused`                                                                                                                                                                                                                                                                                                |
| AC-46   | 8, 9               | `ManualSearchRunTest::test_run_scout_never_calls_threads_inside_the_request`, `DispatchThreadsSearchRunsCommandTest::test_queues_a_run_for_users_due_by_their_interval` (job pushed)                                                                                                                                                                                                                                                                                                                               |
| AC-47   | 7, 9               | `DispatchThreadsSearchRunsCommandTest::test_does_not_queue_while_a_run_is_queued_or_running`, `::test_closes_stale_runs_as_partial_and_queues_a_new_one`, `ManualSearchRunTest::test_run_scout_is_refused_with_the_matching_reason` (M-3)                                                                                                                                                                                                                                                                          |
| AC-48   | 1, 8               | `ThreadsClientTest::test_keyword_search_sends_the_documented_parameters_without_paging`, `RunThreadsSearchJobTest::test_sends_one_keyword_search_per_keyword_with_window_and_run_start`                                                                                                                                                                                                                                                                                                                            |
| AC-49   | 6                  | `ThreadsKeywordTest::test_new_keyword_window_starts_at_now_minus_the_lookback`, `::test_renaming_applies_normalisation_and_resets_the_window`                                                                                                                                                                                                                                                                                                                                                                      |
| AC-50   | 8                  | `RunThreadsSearchJobTest::test_successful_query_moves_the_window_to_until_minus_ten_minutes`                                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-51   | 8                  | `RunThreadsSearchJobTest::test_failed_query_keeps_the_window`                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| AC-52   | 8                  | `RunThreadsSearchJobTest::test_stores_new_posts_with_all_fields_and_owner`                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| AC-53   | 8                  | `RunThreadsSearchJobTest::test_skips_posts_already_stored_for_the_user_and_keeps_their_keyword`, `::test_same_post_id_for_another_user_is_stored`                                                                                                                                                                                                                                                                                                                                                                  |
| AC-54   | 8                  | `RunThreadsSearchJobTest::test_empty_result_records_zero_results_and_continues`                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| AC-55   | 8                  | `RunThreadsSearchJobTest::test_error_190_disconnects_and_ends_the_run`                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| AC-56   | 8                  | `RunThreadsSearchJobTest::test_transient_error_logs_a_warning_and_continues`                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-57   | 8                  | `RunThreadsSearchJobTest::test_other_error_logs_an_error_and_continues`                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| AC-58   | 1, 8               | `ThreadsClientTest::test_rate_limit_codes_http_429_and_limit_messages_are_classified_as_rate_limited`, `RunThreadsSearchJobTest::test_rate_limit_ends_the_run_pauses_for_one_hour_and_logs_the_body_once_without_token`                                                                                                                                                                                                                                                                                            |
| AC-74   | 8                  | `RunThreadsSearchJobTest::test_rate_limit_ends_the_run_pauses_for_one_hour_and_logs_the_body_once_without_token`                                                                                                                                                                                                                                                                                                                                                                                                   |
| AC-73   | 8, 9               | `RunThreadsSearchJobTest::test_paused_user_sends_no_request`, `DispatchThreadsSearchRunsCommandTest::test_skips_users_without_connection_keywords_or_while_paused`, `ManualSearchRunTest::test_run_scout_is_refused_with_the_matching_reason` (M-5)                                                                                                                                                                                                                                                                |
| AC-59   | 7, 8               | `RunThreadsSearchJobTest::test_stops_with_budget_reached_at_2000_queries_in_the_last_24_hours`                                                                                                                                                                                                                                                                                                                                                                                                                     |
| AC-60   | 8                  | `RunThreadsSearchJobTest::test_records_trigger_times_counts_and_outcome`                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| AC-78   | 8                  | `RunThreadsSearchJobTest::test_dispatches_posts_stored_event_with_new_post_ids_only_when_posts_were_stored`                                                                                                                                                                                                                                                                                                                                                                                                        |
| AC-104  | 8                  | `RunThreadsSearchJobTest::test_sends_no_notifications`                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| AC-71   | 9, 16              | `ManualSearchRunTest::test_run_scout_queues_a_manual_run_and_flashes_search_started`; manual M-UI-11                                                                                                                                                                                                                                                                                                                                                                                                               |
| AC-72   | 7, 9, 16           | `ManualSearchRunTest::test_run_scout_is_refused_with_the_matching_reason`, `PostsIndexTest::test_search_status_reports_queued_running_and_idle_states`; manual M-UI-11                                                                                                                                                                                                                                                                                                                                             |
| AC-111  | 10, 11, 16, 17, 18 | `PostsIndexTest::test_partial_reload_returns_only_search_status_and_posts`, `DashboardThreadsTest::test_partial_reload_returns_only_search_status_and_latest_posts`; manual M-UI-12                                                                                                                                                                                                                                                                                                                                |
| AC-112  | 16                 | none: client-side polling control; manual M-UI-12                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| AC-113  | 16                 | none: client-side toast; manual M-UI-13 (server `lastRunPostsStored` covered by `PostsIndexTest::test_search_status_reports_queued_running_and_idle_states`)                                                                                                                                                                                                                                                                                                                                                       |
| AC-61   | 10, 17             | `PostsIndexTest::test_lists_only_the_users_posts_newest_post_first_ten_per_page`                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| AC-62   | 10, 17             | `PostsIndexTest::test_lists_only_the_users_posts_newest_post_first_ten_per_page` (Post shape); manual M-UI-14                                                                                                                                                                                                                                                                                                                                                                                                      |
| AC-82   | 17                 | none: removed UI; manual M-UI-15                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| AC-63   | 10, 17             | `PostsIndexTest::test_search_matches_text_or_username`                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| AC-83   | 10, 17             | `PostsIndexTest::test_keyword_filter_matches_the_stored_keyword_exactly`                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| AC-97   | 10, 17             | `PostsIndexTest::test_filters_are_returned_for_the_url`; manual M-UI-16                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| AC-64   | 10                 | `PostsIndexTest::test_page_beyond_the_last_shows_the_last_page`                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| AC-98   | 17                 | none: UI-only; manual M-UI-16                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| AC-65   | 17                 | none: UI state from tested props (`ThreadsSharedPropTest`, `PostsIndexTest`); manual M-UI-17                                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-85   | 17                 | none: UI state from `PostsIndexTest::test_keyword_options_and_count_are_the_users_current_keywords`; manual M-UI-17                                                                                                                                                                                                                                                                                                                                                                                                |
| AC-86   | 17                 | none: UI state from tested `searchStatus`; manual M-UI-17                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| AC-87   | 17                 | none: UI state from tested `filters`; manual M-UI-17                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| AC-88   | 7, 10, 16, 17      | `PostsIndexTest::test_search_status_reports_queued_running_and_idle_states`; manual M-UI-17                                                                                                                                                                                                                                                                                                                                                                                                                        |
| AC-66   | 12                 | `PruneThreadsPostsCommandTest::test_deletes_posts_found_more_than_90_days_ago_by_found_time`                                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-79   | 10, 17             | `PostShowTest::test_shows_the_users_post`                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| AC-80   | 10                 | `PostShowTest::test_other_users_or_missing_post_returns_404`                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| AC-81   | 17                 | none: removed UI; manual M-UI-18                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| AC-110  | 17, 18             | none: removed UI; manual M-UI-18, M-UI-19                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| AC-75   | 7, 11, 18          | `DashboardThreadsTest::test_quota_widget_counts_queries_of_runs_started_in_the_last_24_hours`, `::test_remaining_queries_never_drop_below_zero`                                                                                                                                                                                                                                                                                                                                                                    |
| AC-76   | 18                 | none: static copy; manual M-UI-19                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| AC-77   | 11, 18             | `DashboardThreadsTest::test_stats_count_total_last_seven_days_and_today`, `::test_latest_posts_are_the_five_most_recently_found`; manual M-UI-19                                                                                                                                                                                                                                                                                                                                                                   |
| AC-109  | 13                 | none: client-side cookie write; manual M-UI-2                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| AC-107  | 11                 | `DashboardThreadsTest::test_today_and_daily_series_use_the_timezone_cookie`                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| AC-108  | 11                 | `DashboardThreadsTest::test_missing_or_invalid_timezone_cookie_falls_back_to_utc`                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| AC-105  | 18                 | none: mock data kept unchanged; manual M-UI-19                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| NFR-1   | 0, 1               | `ThreadsConnectionTokenTest::test_access_token_is_encrypted_at_rest`                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| NFR-2   | 1, 2, 3, 4, 8      | `ThreadsClientTest::test_exception_message_and_body_never_contain_the_access_token`, `ThreadsConnectionTokenTest::test_access_token_is_hidden_from_serialization`, `ThreadsSettingsPageTest::test_threads_tab_props_never_contain_the_access_token`, `ThreadsCallbackTest::test_short_lived_token_is_never_stored_logged_or_put_in_the_session`, `MetaUninstallCallbackTest::test_rejected_signed_request_warning_does_not_contain_the_body`, `RunThreadsSearchJobTest::test_job_payload_contains_only_the_run_id` |
| NFR-3   | 0, 1               | none: configuration source; architecture review checks no `env()` outside `config/`                                                                                                                                                                                                                                                                                                                                                                                                                                |
| NFR-4   | 2, 4               | none: timing property not observable in a feature test; review checks `hash_equals` in the callback and `SignedRequestParser`                                                                                                                                                                                                                                                                                                                                                                                      |
| NFR-5   | 1                  | `ThreadsClientTest` (all methods; `Http::preventStrayRequests()` everywhere proves no other HTTP path)                                                                                                                                                                                                                                                                                                                                                                                                             |
| NFR-6   | 3, 6, 10, 11       | `ThreadsKeywordTest::test_other_users_keyword_returns_404_on_update_and_delete`, `PostShowTest::test_other_users_or_missing_post_returns_404`, `PostsIndexTest::test_lists_only_the_users_posts_newest_post_first_ten_per_page`, `ScrapingSettingsPageTest::test_scraping_page_lists_only_the_users_keywords_with_search_status`, `DashboardThreadsTest::test_dashboard_shows_only_the_users_data`                                                                                                                 |
| NFR-7   | 13, 14, 16         | none: rendering as text; manual M-UI-1, M-UI-11                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| NFR-8   | 5                  | `RefreshThreadsTokensCommandTest::test_writes_one_summary_log_entry`                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| NFR-9   | 8                  | `RunThreadsSearchJobTest::test_writes_one_summary_log_entry_per_run`                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| NFR-10  | 12                 | `PruneThreadsPostsCommandTest::test_writes_one_log_entry_with_the_counts`                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| NFR-11  | 0, 4               | `MetaDataDeletionCallbackTest::test_deletion_request_stores_no_threads_user_id_or_username`                                                                                                                                                                                                                                                                                                                                                                                                                        |
| NFR-12  | 0, 2, 4, 6, 9      | `ThreadsConnectTest::test_connect_and_callback_are_throttled_per_user`, `MetaUninstallCallbackTest::test_meta_callbacks_are_throttled_per_ip`, `ThreadsKeywordTest::test_keyword_writes_are_throttled_per_user`, `ManualSearchRunTest::test_manual_runs_are_throttled_per_user` (per-IP limit bypassable through `trustProxies('*')`: accepted risk, section 8)                                                                                                                                                    |
| MC-1    | 2, 3, 14           | `ThreadsSettingsPageTest`, `ThreadsCallbackTest`, `ThreadsDisconnectTest`                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| MC-2    | 6, 15              | `ScrapingSettingsPageTest`, `ThreadsKeywordTest`, `SearchSettingsTest`, `tests/Feature/Settings/SettingsUrlSplitTest.php::test_scraping_settings_page_renders_under_settings_url`                                                                                                                                                                                                                                                                                                                                  |
| MC-3    | 9, 10, 16, 17      | `PostsIndexTest`, `PostShowTest`, `ManualSearchRunTest`                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| MC-4    | 0, 12              | `AccountDeletionCascadeTest::test_deleting_the_account_removes_connection_keywords_posts_and_runs`                                                                                                                                                                                                                                                                                                                                                                                                                 |
| MC-5    | 4                  | `MetaUninstallCallbackTest`, `MetaDataDeletionCallbackTest`                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| MC-6    | 4, 19              | `DataDeletionPageTest`, `tests/Feature/LegalPagesTest.php::test_guest_can_view_the_data_deletion_instructions`                                                                                                                                                                                                                                                                                                                                                                                                     |
| MC-7    | 0, 5, 9, 12, 20    | `ThreadsScheduleTest::test_refresh_and_prune_commands_run_daily_without_overlapping_on_one_server`, `::test_search_dispatcher_runs_every_minute_without_overlapping_on_one_server`                                                                                                                                                                                                                                                                                                                                 |
| MC-8    | 11, 18             | `DashboardThreadsTest`, `tests/Feature/DashboardTest.php::test_authenticated_users_can_visit_the_dashboard`                                                                                                                                                                                                                                                                                                                                                                                                        |
| MC-9    | 0, 8               | `RunThreadsSearchJobTest::test_dispatches_posts_stored_event_with_new_post_ids_only_when_posts_were_stored`                                                                                                                                                                                                                                                                                                                                                                                                        |
| MC-10   | 3, 13              | `ThreadsSharedPropTest::test_disconnected_connection_is_shared_on_every_signed_in_page`, `::test_shared_connection_is_null_without_a_connection`                                                                                                                                                                                                                                                                                                                                                                   |
| UI-1    | 2                  | `ThreadsCallbackTest::test_error_parameter_leaves_the_connection_unchanged_and_never_echoes_it`                                                                                                                                                                                                                                                                                                                                                                                                                    |
| UI-2    | 4                  | `MetaUninstallCallbackTest::test_invalid_signed_requests_return_400_and_change_nothing` (over-8,192 case)                                                                                                                                                                                                                                                                                                                                                                                                          |
| UI-3    | 6                  | `ThreadsKeywordTest::test_keyword_validation_rejects_empty_control_characters_and_over_100_characters`                                                                                                                                                                                                                                                                                                                                                                                                             |
| UI-4    | 13, 17             | none: Vue interpolation escapes by default; review checks there is no `v-html`; manual M-UI-14                                                                                                                                                                                                                                                                                                                                                                                                                     |
| UI-5    | 8                  | `RunThreadsSearchJobTest::test_invalid_permalinks_are_stored_as_null`                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| UI-6    | 8                  | `RunThreadsSearchJobTest::test_items_without_id_or_timestamp_are_skipped_with_a_warning`                                                                                                                                                                                                                                                                                                                                                                                                                           |
| UI-7    | 4                  | `DataDeletionPageTest::test_malformed_code_sets_code_not_found_without_querying`                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| UI-8    | 10                 | `PostsIndexTest::test_search_treats_percent_and_underscore_literally`                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| UI-9    | 6                  | `SearchSettingsTest::test_interval_and_lookback_outside_the_allowed_sets_are_rejected`                                                                                                                                                                                                                                                                                                                                                                                                                             |
| UI-10   | 10                 | `PostsIndexTest::test_overlong_search_or_keyword_is_rejected`                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| UI-11   | 1, 8               | `ThreadsClientTest::test_exception_message_and_body_never_contain_the_access_token`, `RunThreadsSearchJobTest::test_rate_limit_ends_the_run_pauses_for_one_hour_and_logs_the_body_once_without_token`                                                                                                                                                                                                                                                                                                              |
| UI-12   | 11                 | `DashboardThreadsTest::test_missing_or_invalid_timezone_cookie_falls_back_to_utc`                                                                                                                                                                                                                                                                                                                                                                                                                                  |

Not covered: None. AC-38 has no step because the spec removed it. Spec IDs AC-84 and AC-101 do not exist in SPEC-01.

## 7. Requires approval

Approved by the user on 2026-10-23 (folders, route file, Docker services).

- New Laravel-conventional folders created by `make:*`: `app/Enums/`, `app/Services/Threads/`, `app/Actions/Threads/`, `app/Jobs/`, `app/Events/`, `app/Console/Commands/`, `app/Http/Resources/`, `app/Http/Controllers/Threads/`, `app/Http/Controllers/Legal/`, `app/Http/Requests/Threads/`, `tests/Feature/Threads/`; new route file `routes/meta-callbacks.php`.
- `docker-compose.yml` gains two services (`queue`, `scheduler`) — approved in the user's answer to question 6.
- No new dependencies. No destructive migrations (new tables and two added columns with defaults).

## 8. Risks and open questions

- Accepted risk against NFR-12: `trustProxies(at: '*')` (uncommitted, `bootstrap/app.php:18`) trusts any `X-Forwarded-For`, so the 60/min per-IP limit on the Meta callbacks can be bypassed by a client that sets that header. User decision of 2026-10-23: keep the line unchanged.
- Accepted spec behaviour: a delete callback for a Threads account also deletes posts and runs of earlier Scout users whose `disconnected` rows still hold that `threads_user_id` (AC-21; confirmed by the user).
- `config/queue.php:43` `retry_after` 90 s is shorter than a 20-keyword run; this plan raises it to 360 (accepted recommendation 5). Environments that already set `DB_QUEUE_RETRY_AFTER` must update it.
- `Dockerfile` / `docker-compose.yml` had no worker or scheduler; Step 20 adds them. Without both running, nothing is searched or refreshed.
- `resources/js/components/TagInput.vue:42-52` added on comma and blur and deleted on Backspace; Step 15 removes those keys (user answer 2).
- No JS test runner (`package.json`); the frontend-only criteria marked "none" in section 6 rely on manual checks M-UI-1..M-UI-21 (Step 20).
- `utf8mb4_bin` collation on `keyword` columns works on MySQL (Docker and the `testing` database); the SQLite default in `.env.example:23` ignores collation, so byte-for-byte uniqueness is only guaranteed on MySQL.
- After page changes, feature tests that render pages need `npm run build` (backend insight 2026-10-21); WP-3 and WP-5 must rebuild before running page tests if WP-4 has changed pages in the meantime.
- Rate-limit classification (D-8) is unconfirmed until V-1; a non-quota error mentioning "limit" pauses search for 1 hour (EC-36, accepted by the spec).
- Callback `user_id` equality with the `/me` id is unconfirmed until V-2; if it differs, callbacks match nothing (logged as unmatched).
- Refresh endpoint unverified until V-3.
- Time zones: legacy alias identifiers (for example `Europe/Kiev`, still sent by some browsers in Ukraine) are accepted via `DateTimeZone::ALL_WITH_BC` (user decision 2026-10-23); tests should cover one alias being accepted.
- Agents share one working tree: WP-2, WP-3 and WP-4 run in parallel on disjoint files; a missing cross-package model method must be reported, not added by a package that doesn't own the file.

## 9. Notes for architecture and security review

- Token secrecy end to end (NFR-2, AC-8): `ThreadsClient` exception handling and redaction; `#[Hidden]` on `access_token` with `auth.user` serialization in `HandleInertiaRequests`; logs in the job, the refresh command and callbacks; `failed_jobs` exception text.
- OAuth CSRF (AC-2, AC-3, NFR-4): `state` generation, `pull()` before any work, `hash_equals`, order of checks in the callback; `SESSION_SAME_SITE` must stay `lax`.
- Signed-request verification (AC-20, UI-2, NFR-4): length check before decoding, algorithm check, constant-time compare, no body in logs; Meta routes outside the `web` group (no session, no CSRF) and the per-IP limiter with the accepted `trustProxies('*')` risk.
- Authorization / tenant isolation (NFR-6, AC-41, AC-80): keyword and post lookups through the user's relations; every query in `PostController`, `DashboardController`, `SearchBudget`, `SearchStatus` scoped by `user_id`.
- `LIKE` escaping (UI-8), permalink allow-list (UI-5), no `v-html` (UI-4), error parameters never displayed (UI-1).
- Concurrency: AC-67 row lock, manual-run user-row lock (EC-37), unique-violation handling in post storage, stale-run handling vs. a job that resumes late.
- Delete callback transaction scope and the deletion of earlier users' data (accepted).
- Scheduler and queue settings: `withoutOverlapping` lock expiry, `onOneServer` with the `database` cache store, job `$timeout` < worker `--timeout` < `retry_after`.
- Layering: controllers thin, actions own the workflows, `ThreadsClient` is the only HTTP path (NFR-5); no `env()` outside `config/` (NFR-3).

## Changelog

- 2026-10-23 00:34 — created from SPEC-01 (approved) with the user's answers to the Requirements review (questions 1–7, recommendations R1–R5, multi-agent, tdd)
