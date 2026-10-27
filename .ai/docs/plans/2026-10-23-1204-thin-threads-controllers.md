# Implementation Plan: Thin Threads controllers (services, actions, resources)

- Created: 2026-10-23 12:04
- Status: draft
- Requirements source: user change request (no specification): "Controllers must be as light as possible: they only handle the request and return the response. Anything more complex than a one-line GET from the database moves to the service layer; response shaping moves to Eloquent API Resources. Behaviour does not change; the existing tests must pass unchanged." Applies to the SPEC-01 Threads feature (original plan `.ai/docs/plans/2026-10-23-0034-threads-connection-and-keyword-search.md`).
- Execution mode: single-agent
- Test mode: test-after

## 1. Requirements and acceptance criteria

- [ ] R1 Controllers only read the request, make one call per prop/operation, flash, and return the response; no logic beyond a one-line database read — source: request
- [ ] R2 Every prop and JSON payload shape is produced by a JsonResource, scalar-array props included (`threadsQuota`, `stats`, `activity`, `searchBudget`, `searchSettings`) as well as model-backed ones — source: request, answer to question 1 (A)
- [ ] R3 One-line trivial props stay inline in the controller: `keywordCount` (`threadsKeywords()->count()`), `ownPostsOnly` (`config(...)`), `filters` (`$request->filters()`) — source: answer to question 2 (A)
- [ ] R4 `ThreadsKeywordController::destroy` stays inline — source: answer to question 3 (A)
- [ ] R5 `HandleInertiaRequests` uses `ThreadsConnectionSummaryResource`; `SearchStatus::for()` is split into raw `snapshot()` plus `SearchStatusResource`; `manualRunBlock()` and `isScheduledRunDue()` stay — source: answer to question 4 (A)
- [ ] R6 `MetaCallbackController` keeps its try/catch on `InvalidSignedRequest`; the exception class gets no `report()`/`render()` — source: answer to question 5 (B)
- [ ] R7 Toast copy is produced by outcome enums with `toastType()` and `toastMessage()`; the controller flashes with one call; manual-run copy takes the client time zone for `H:i`; `StartManualSearchRun` return shape is unchanged — source: answer to question 6 (B)
- [ ] R8 New focused tests only for newly callable logic (activity bucketing, page clamp, OAuth check order, outcome mapping), written by test-writer after the refactor; no existing test is edited — source: answer to question 7 (B)
- [ ] R9 `app/Http/Controllers/Settings/*` is out of scope — source: answer to question 8 (A)
- [ ] R10 Mutations/workflows are Actions in `App\Actions\Threads` (verb name, `handle()`); reads/computations are services in `App\Services\Threads` — source: accepted recommendation 1
- [ ] R11 Resources are always `->resolve($request)`-ed; paginated posts use `ThreadsPostPageResource`; no global `JsonResource::withoutWrapping()` — source: accepted recommendation 2
- [ ] R12 Keyword actions keep Eloquent `create()`/`update()` and throw `ValidationException` on unique violation — source: accepted recommendation 3
- [ ] R13 The uninstall info log moves into `DisconnectThreadsAccountsForMeta` with the same message and context keys — source: accepted recommendation 4
- [ ] R14 Behaviour unchanged: all 364 existing tests pass unedited, PHPStan, Pint, `npm run check` and `types:check` stay green, no frontend file changes, controller class/method names and routes unchanged — source: request, coordinator constraints

Behaviour-preservation constraints carried from the architecture audit (each step names the ones it must respect):

- R-1 Data wrapping: a JsonResource/ResourceCollection given to Inertia or `response()->json()` unresolved becomes `{data: ...}` (Inertia 3.3.4 calls `toResponse()->getData(true)`). Always `->resolve($request)`.
- R-2 Posts paginator shape `{data, currentPage, lastPage, perPage, total}` and count-then-clamp: page above last page is clamped to last page, `lastPage >= 1`.
- R-3 Exact key order and scalar types (`assertSame`) for `threadsQuota`, `stats`, `activity` (`{date: string, found: int}`), `searchStatus`, `searchBudget`, `searchSettings` (`firstSearchLookbackDays` int).
- R-4 Exact key sets: shared `threadsConnection` has 3 keys; settings `connection` has 6.
- R-5 Every Dashboard and Posts prop stays a closure (reloadOnly tests). Scraping-settings props stay eager.
- R-6 Meta data-deletion JSON keeps status 200 (no 201 from a fresh-model resource response).
- R-7 Keyword writes keep Eloquent `create()`/`update()` so `ThreadsKeyword::creating/updating` hooks fire (`tests/Feature/Threads/ThreadsKeywordTest.php:391,411`).
- R-8 OAuth: check order is state pull → `error` param → code shape (non-empty string, ≤2048) → exchange with `ThreadsApiException` catch → `null` = taken; session key `threads_oauth_state`; `connect` returns `Inertia::location` (409 for Inertia requests); every callback/destroy outcome redirects with `to_route('settings.threads')`.
- R-9 Data-deletion page: null/empty code → `deletionRequest: null, codeNotFound: false` without a query; malformed code (not `/\A[A-Za-z0-9]{1,32}\z/` or not a string) → `codeNotFound: true` without a query.
- R-10 Meta routes have no session; log context keys `user_id` and `matched_connections` stay; nothing secret logged.
- R-11 Exact toast copy and types; keyword, settings and search-run routes return `back(303)`.
- R-12 Routes, throttles, controller class and method names (Wayfinder imports them), int route params with `user()->threadsKeywords()` scoping, and `UpdateThreadsKeywordRequest::rules()` `firstOrFail()` before validation stay unchanged.
- R-13 `StartManualSearchRun::handle()` and `StartScheduledSearchRun::handle()` signatures and return shapes stay.
- R-14 Resources whitelist fields explicitly, never call `parent::toArray()`, never expose `access_token`.

## 2. Context

- Modules and files in scope:
    - `app/Http/Controllers/Threads/DashboardController.php` — inline quota/stats arrays and private raw-SQL `activity()`
    - `app/Http/Controllers/Threads/PostController.php` — `index` builds filtered query, count-then-clamp, page array; `show` already thin
    - `app/Http/Controllers/Threads/ScrapingSettingsController.php` — inline keyword/budget arrays; `update()` writes user
    - `app/Http/Controllers/Threads/SearchRunController.php` — reason→copy `match` and `H:i` formatting
    - `app/Http/Controllers/Threads/ThreadsConnectionController.php` — connection array, OAuth URL/state, callback chain, disconnect
    - `app/Http/Controllers/Threads/ThreadsKeywordController.php` — create/update with unique-violation translation
    - `app/Http/Controllers/Threads/MetaCallbackController.php` — try/catch, info log, JSON payload
    - `app/Http/Controllers/Legal/DataDeletionController.php` — code regex and lookup, request array
    - `app/Http/Middleware/HandleInertiaRequests.php` — `threadsConnectionFor()` array
    - `app/Services/Threads/SearchStatus.php` — `for()` builds the prop; `manualRunBlock()`, `pausedUntil()`, `nextScheduledRunAt()`, `isScheduledRunDue()` used by Start* actions
    - `app/Services/Threads/SearchBudget.php`, `app/Services/Threads/ClientTimezone.php`
    - `app/Actions/Threads/DisconnectThreadsAccountsForMeta.php`, `StartManualSearchRun.php`, `ConnectThreadsAccount.php`
    - `app/Http/Requests/Threads/PostIndexRequest.php`
- Relevant insights: `.ai/insights/backend.md` — `Route::inertia` passes route params as strings (not relevant to change, routes untouched); Vite manifest 500 after renaming pages (not relevant, no page renames).
- Existing code to reuse: `app/Http/Resources/ThreadsPostResource.php` (resource style, `@mixin`, `->resolve($request)` call sites); `app/Actions/Threads/DeleteThreadsDataForMeta.php` (action logging style with `user_id`/`matched_connections`); `app/Enums/SearchInterval.php` (enum-with-methods style); `tests/Concerns/InteractsWithThreads.php` (helpers for the new tests).
- Versions: laravel/framework ^13.17, inertiajs/inertia-laravel ^3.0 (3.3.4), PHPUnit 12, larastan level 7.
- Technical assumptions:
    - A1 `Illuminate\Contracts\Session\Session` exposes `put()` and `pull()`; if `pull()` is missing from the contract, type the parameter as `Illuminate\Session\Store`.
    - A2 `JsonResource::resolve()` over a plain array resource returns `toArray()` filtered of `MissingValue` only; `null` values and key order are preserved.
    - A3 `new LengthAwarePaginator($items, $total, $perPage, $page)` keeps an already clamped `$page` and computes `lastPage()` as `max(1, ceil(total/perPage))`, identical to the current code.
    - A4 `$request->query()` (array) is equivalent to the current `$request->query('x')` / `$request->query->has('error')` reads when accessed as `$query['x'] ?? null` / `array_key_exists('error', $query)`.

## 3. Decisions and constraints

- Resources live flat in `app/Http/Resources` (existing convention). Array-backed resources read through a local typed variable (`/** @var array{...} $data */ $data = $this->resource;`) so larastan level 7 passes; model-backed resources use `@mixin <Model>` like `ThreadsPostResource`.
- Model-or-null props use a one-line ternary in the controller/middleware: `$model === null ? null : (new XResource($model))->resolve($request)`. This is response handling, not logic.
- Outcome enums live in `app/Enums` (existing folder): `ThreadsCallbackOutcome`, `ThreadsDisconnectOutcome`, `ManualSearchRunOutcome`. Each has `toastType(): string`, `toastMessage(...): string` and `toast(...): array{type: string, message: string}` so the controller flashes with one call.
- `ManualSearchRunOutcome` is string-backed with the block reasons as values plus `Started`. `fromResult(array $result): self` returns `Started` when `run !== null`, else `tryFrom(reason) ?? RunInProgress` (keeps the current `default` arm). `toast(array $block, string $timezone)` picks `availableAt` (cooldown) or `pausedUntil` (paused) and formats `H:i` in the zone, `''` for null.
- `ThreadsOAuth` (service) owns only `STATE_SESSION_KEY = 'threads_oauth_state'` and the authorize URL build (`authorizationUrl()`); it has no constructor dependencies. The callback check chain is a state-changing workflow, so it is the Action `CompleteThreadsAuthorization` (injects `ConnectThreadsAccount`, reads `ThreadsOAuth::STATE_SESSION_KEY`), per R10 (no Service → Action dependency; review finding AR-1). No Form Request for the callback (decided in the original plan).
- `SearchStatus::snapshot(User)` returns raw values `{runState: string, lastRun: ?ThreadsSearchRun, nextRunAt: ?CarbonInterface, pausedUntil: ?CarbonInterface, block: array{reason, availableAt, pausedUntil}}`; `runState` stays computed in the service. `for()` is removed (only the two controllers call it).
- `keywordOptions` (Posts) moves into `PostListing::keywordOptions()` and stays a plain `list<string>`: it has no object shape for a Resource to define. `keywordCount`, `ownPostsOnly`, `filters`, `codeNotFound` stay plain scalars per answer 2.
- `PER_PAGE = 10` and `ACTIVITY_DAYS = 30` move to `PostListing` and `DashboardStats`.
- Clarifications from the user: Q1 → A; Q2 → A; Q3 → A; Q4 → A; Q5 → B (keep try/catch); Q6 → B (outcome enums); Q7 → B (focused new tests); Q8 → A (Settings out of scope).
- Recommendations: Actions/Services split — accepted; always `->resolve()` + `ThreadsPostPageResource` — accepted; keyword actions keep Eloquent events — accepted; uninstall info log into action — accepted; per-step targeted existing-test verification — accepted.
- Out of scope: frontend files, routes, Wayfinder output, `app/Http/Controllers/Settings/*`, `PostController::show`, `ThreadsKeywordController::destroy`, `InvalidSignedRequest`, `StartManualSearchRun`/`StartScheduledSearchRun`, any existing test file.

## 4. Steps

### Step 1: Split SearchStatus into snapshot + SearchStatusResource [backend]

- Requirements: R2, R5, R10, R14; constraints R-1, R-3, R-5, R-13
- Files: modify `app/Services/Threads/SearchStatus.php`; create `app/Http/Resources/SearchStatusResource.php`; modify `app/Http/Controllers/Threads/DashboardController.php`, `app/Http/Controllers/Threads/PostController.php` (only the `searchStatus` prop)
- Do: replace `for()` with `snapshot()` (same queries, same order: active run, last finished run, pausedUntil, block, nextRunAt). `SearchStatusResource::toArray()` emits exactly, in order: `runState, lastRunId, lastRunFinishedAt, lastRunPostsStored, lastRunOutcome, nextRunAt, pausedUntil, manualRunBlockedReason, manualRunAvailableAt` with the current `?->toIso8601String()` / `?->value` conversions. Controllers: `'searchStatus' => fn (): array => (new SearchStatusResource($searchStatus->snapshot($user)))->resolve($request)`. Keep `manualRunBlock()`, `pausedUntil()`, `nextScheduledRunAt()`, `isScheduledRunDue()` signatures.
- Skills: `laravel-best-practices`, `inertia-vue-development` (server props only)
- Generate: `php artisan make:resource SearchStatusResource --no-interaction`
- Tests: existing `tests/Feature/Threads/DashboardThreadsTest.php`, `tests/Feature/Threads/PostsIndexTest.php`, `tests/Feature/Threads/StartSearchRunActionsTest.php`, `tests/Feature/Threads/ManualSearchRunTest.php` stay green
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/DashboardThreadsTest.php tests/Feature/Threads/PostsIndexTest.php tests/Feature/Threads/StartSearchRunActionsTest.php tests/Feature/Threads/ManualSearchRunTest.php`

### Step 2: Dashboard stats service and resources [backend]

- Requirements: R1, R2, R10, R11; constraints R-1, R-3, R-5
- Files: create `app/Services/Threads/DashboardStats.php`, `app/Http/Resources/ThreadsQuotaResource.php`, `app/Http/Resources/DashboardStatsResource.php`, `app/Http/Resources/FoundActivityResource.php`; modify `app/Services/Threads/SearchBudget.php`, `app/Http/Controllers/Threads/DashboardController.php`
- Do:
    - `SearchBudget::usage(User): array{usedLast24Hours: int, remaining: int, dailyBudget: int, threadsDailyLimit: int}` (one `queriesUsedLast24Hours()` call, `remaining = max(0, DAILY_BUDGET - used)`).
    - `DashboardStats::stats(User, string $timezone): array{postsFound, foundLast7Days, foundToday}` with the current three queries; `activity(User, string $timezone): list<array{date: string, found: int}>` moved verbatim (raw SQL, `ACTIVITY_DAYS = 30`, `(int)` cast); `latestPosts(User): Collection<int, ThreadsPost>` (created_at desc, id desc, limit 5).
    - Resources emit keys in the current order; `FoundActivityResource` emits `{date, found}` per day and is used via `FoundActivityResource::collection($days)->resolve($request)`.
    - Controller: `$timezone` still computed eagerly; every prop is a closure with one call ending in `->resolve($request)`; private `activity()` and `DB`/`Carbon` imports removed.
- Skills: `laravel-best-practices`
- Generate: `php artisan make:class Services/Threads/DashboardStats --no-interaction`; `php artisan make:resource <Name> --no-interaction` for each resource
- Tests: existing `tests/Feature/Threads/DashboardThreadsTest.php`, `tests/Feature/DashboardTest.php` stay green; new `tests/Feature/Threads/DashboardStatsTest.php::test_activity_counts_posts_per_client_day_for_the_last_30_days` (Step 11)
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/DashboardThreadsTest.php tests/Feature/DashboardTest.php`

### Step 3: Posts listing service, request accessors and page resource [backend]

- Requirements: R1, R2, R3, R11; constraints R-1, R-2, R-3, R-5
- Files: create `app/Services/Threads/PostListing.php`, `app/Http/Resources/ThreadsPostPageResource.php`; modify `app/Http/Requests/Threads/PostIndexRequest.php`, `app/Http/Controllers/Threads/PostController.php`
- Do:
    - `PostIndexRequest`: `search(): string` (`(string) ($this->validated('search') ?? '')`), `keyword(): ?string`, `page(): int` (`(int) ($this->validated('page') ?? 1)`), `filters(): array{search: string, keyword: string|null}` (keys in that order).
    - `PostListing::paginate(User, string $search, ?string $keyword, int $page): LengthAwarePaginator<int, ThreadsPost>` — same LIKE escaping (`addcslashes($search, '\\%_')`, text OR username), keyword filter, `count()` on a clone first, clamp `min($page, max(1, ceil(total/PER_PAGE)))`, `orderByDesc('posted_at')->orderByDesc('id')->forPage()`, then `new LengthAwarePaginator($posts, $total, PER_PAGE, $page)`. `keywordOptions(User): list<string>` (orderBy id, pluck keyword).
    - `ThreadsPostPageResource` wraps the paginator and emits exactly `{data: ThreadsPostResource::collection(items)->resolve($request), currentPage, lastPage, perPage, total}` as ints.
    - Controller `index`: all six props remain closures; `posts`, `keywordOptions`, `searchStatus` are single calls; `filters`, `keywordCount`, `ownPostsOnly` stay inline one-liners. `show` unchanged.
- Skills: `laravel-best-practices`
- Generate: `php artisan make:class Services/Threads/PostListing --no-interaction`; `php artisan make:resource ThreadsPostPageResource --no-interaction`
- Tests: existing `tests/Feature/Threads/PostsIndexTest.php`, `tests/Feature/Threads/PostShowTest.php` stay green; new `tests/Feature/Threads/PostListingTest.php::test_a_page_beyond_the_last_page_is_clamped_to_the_last_page`, `::test_an_empty_result_is_page_one_of_one` (Step 11)
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/PostsIndexTest.php tests/Feature/Threads/PostShowTest.php`

### Step 4: Scraping settings page resources and budget plan [backend]

- Requirements: R1, R2, R11, R14; constraints R-1, R-3, R-5 (props stay eager), R-14
- Files: create `app/Http/Resources/ThreadsKeywordResource.php`, `app/Http/Resources/SearchSettingsResource.php`, `app/Http/Resources/SearchBudgetResource.php`; modify `app/Services/Threads/SearchBudget.php`, `app/Http/Controllers/Threads/ScrapingSettingsController.php` (`show`)
- Do:
    - `SearchBudget::plan(SearchInterval $interval, int $keywordCount): array{dailyBudget, plannedDailyQueries, runsPerDay, keywordCap, keywordCount}`.
    - `ThreadsKeywordResource` (`@mixin ThreadsKeyword`): `id, keyword, lastSearchedAt, lastResultCount, zeroResultsSince`.
    - `SearchSettingsResource` wraps `User` and emits only `intervalMinutes` (`searchInterval()->value`) and `firstSearchLookbackDays` (`(int)`); never other user fields.
    - Controller `show`: one-line keyword read (`orderBy('id')->get()`), then eager `->resolve($request)` calls; `ownPostsOnly` inline.
- Skills: `laravel-best-practices`
- Generate: `php artisan make:resource <Name> --no-interaction` for each resource
- Tests: existing `tests/Feature/Threads/ScrapingSettingsPageTest.php` stays green
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ScrapingSettingsPageTest.php`

### Step 5: UpdateSearchSettings action [backend]

- Requirements: R1, R10; constraint R-11
- Files: create `app/Actions/Threads/UpdateSearchSettings.php`; modify `app/Http/Controllers/Threads/ScrapingSettingsController.php` (`update`)
- Do: `UpdateSearchSettings::handle(User $user, int $intervalMinutes, int $firstSearchLookbackDays): void` performs the current `update()`. Controller casts validated input, calls the action, flashes `['type' => 'success', 'message' => 'Search settings saved.']`, returns `back(303)`.
- Skills: `laravel-best-practices`
- Generate: `php artisan make:class Actions/Threads/UpdateSearchSettings --no-interaction`
- Tests: existing `tests/Feature/Threads/SearchSettingsTest.php` stays green
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/SearchSettingsTest.php`

### Step 6: Keyword add/rename actions [backend]

- Requirements: R1, R4, R10, R12; constraints R-7, R-11, R-12
- Files: create `app/Actions/Threads/AddThreadsKeyword.php`, `app/Actions/Threads/RenameThreadsKeyword.php`; modify `app/Http/Controllers/Threads/ThreadsKeywordController.php`
- Do:
    - `AddThreadsKeyword::handle(User $user, string $keyword): ThreadsKeyword` — `$user->threadsKeywords()->create([...])` with `search_window_start = now()->subDays($user->first_search_lookback_days)`; catch `UniqueConstraintViolationException` → `ValidationException::withMessages(['keyword' => 'This keyword already exists.'])`.
    - `RenameThreadsKeyword::handle(User $user, int $keywordId, string $keyword): ThreadsKeyword` — `findOrFail` scoped to the user, then `$model->update([...])` with the same five attributes; same exception translation.
    - Controller `store`/`update` call the action, flash the same toast, return `back(303)`; `destroy` unchanged; private `duplicateKeyword()` removed from the controller.
- Skills: `laravel-best-practices`
- Generate: `php artisan make:class Actions/Threads/<Name> --no-interaction`
- Tests: existing `tests/Feature/Threads/ThreadsKeywordTest.php` stays green (including the `creating`/`updating` hook tests)
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ThreadsKeywordTest.php`

### Step 7: ManualSearchRunOutcome enum [backend]

- Requirements: R1, R7; constraints R-11, R-13
- Files: create `app/Enums/ManualSearchRunOutcome.php`; modify `app/Http/Controllers/Threads/SearchRunController.php`
- Do:
    - Enum cases: `Started = 'started'`, `NotConnected = 'not_connected'`, `NoKeywords = 'no_keywords'`, `RunInProgress = 'run_in_progress'`, `Cooldown = 'cooldown'`, `Paused = 'paused'`, `Budget = 'budget'`.
    - `static fromResult(array $result): self` (as in section 3); `toastType(): string` (`success` for Started, else `error`); `toastMessage(?CarbonInterface $time, string $timezone): string` with the exact current copy; `toast(array $block, string $timezone): array{type: string, message: string}` selects the time (`availableAt` for Cooldown, `pausedUntil` for Paused, otherwise null).
    - Controller: `$result = $startManualSearchRun->handle($request->user());` then `Inertia::flash('toast', ManualSearchRunOutcome::fromResult($result)->toast($result['block'], $clientTimezone->fromRequest($request)));` and `return back(303);`. `StartManualSearchRun` untouched.
- Skills: `laravel-best-practices`
- Generate: `php artisan make:enum ManualSearchRunOutcome --string --no-interaction` (check `--help`; otherwise create the file by hand following `app/Enums/SearchInterval.php`)
- Tests: existing `tests/Feature/Threads/ManualSearchRunTest.php`, `tests/Feature/Threads/StartSearchRunActionsTest.php` stay green; new `tests/Unit/ManualSearchRunOutcomeTest.php` (Step 11)
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ManualSearchRunTest.php tests/Feature/Threads/StartSearchRunActionsTest.php`

### Step 8: Threads connection: resource, ThreadsOAuth service, authorization and disconnect actions [backend]

- Requirements: R1, R2, R7, R10, R11, R14; constraints R-1, R-4, R-8, R-11, R-12, R-14
- Files: create `app/Http/Resources/ThreadsConnectionResource.php`, `app/Services/Threads/ThreadsOAuth.php`, `app/Actions/Threads/CompleteThreadsAuthorization.php`, `app/Actions/Threads/DisconnectThreadsAccount.php`, `app/Enums/ThreadsCallbackOutcome.php`, `app/Enums/ThreadsDisconnectOutcome.php`; modify `app/Http/Controllers/Threads/ThreadsConnectionController.php`
- Do:
    - `ThreadsConnectionResource` (`@mixin ThreadsConnection`): exactly `username, name, status, disconnectReason, connectedAt, tokenExpiresAt`; no token fields.
    - `ThreadsOAuth::authorizationUrl(Session $session): string` — generates `Str::random(40)` state, puts it under `threads_oauth_state`, builds the URL with the same params, order and `PHP_QUERY_RFC3986`. `ThreadsOAuth` keeps only this method and `STATE_SESSION_KEY`; no constructor dependencies.
    - `CompleteThreadsAuthorization::handle(User $user, Session $session, array $query): ThreadsCallbackOutcome` (Action in `App\Actions\Threads`, injects `ConnectThreadsAccount`, reads `ThreadsOAuth::STATE_SESSION_KEY`) — keep R-8 order exactly: pull state (always, first) → `StateMismatch`; `error` key present → `AccessDenied`; bad code → `ConnectFailed`; `ThreadsApiException` → `ConnectFailed`; `null` → `AccountTaken`; else `Connected`.
    - `ThreadsCallbackOutcome` copy: StateMismatch "We couldn't verify the Threads connection. Please try again."; AccessDenied "Threads access was not granted."; ConnectFailed "Connecting Threads failed. Please try again."; AccountTaken "This Threads account is already connected to another Opportunity Scout account."; Connected (success) "Threads account connected." All others `error`.
    - `DisconnectThreadsAccount::handle(User): ThreadsDisconnectOutcome` — `NotConnected` (`info`, "Threads is not connected.") when no connection or not Connected; otherwise `markDisconnected(ThreadsDisconnectReason::User)` → `Disconnected` (`success`, "Threads account disconnected.").
    - Controller: `show` one-line read + null ternary resource; `connect` → `Inertia::location($oauth->authorizationUrl($request->session()))`; `callback` calls `CompleteThreadsAuthorization::handle()`; `callback` and `destroy` → `backToSettings($outcome->toast())`; helper keeps `to_route('settings.threads')`.
- Skills: `laravel-best-practices` (incl. `rules/security.md`), `inertia-vue-development` (server side `Inertia::location`)
- Generate: `php artisan make:resource ThreadsConnectionResource --no-interaction`; `php artisan make:class ... --no-interaction`; `php artisan make:enum ... --no-interaction`
- Tests: existing `tests/Feature/Threads/ThreadsSettingsPageTest.php`, `ThreadsConnectTest.php`, `ThreadsCallbackTest.php`, `ThreadsDisconnectTest.php`, `ThreadsConnectionTokenTest.php` stay green; new `tests/Feature/Threads/CompleteThreadsAuthorizationTest.php` (Step 11)
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ThreadsSettingsPageTest.php tests/Feature/Threads/ThreadsConnectTest.php tests/Feature/Threads/ThreadsCallbackTest.php tests/Feature/Threads/ThreadsDisconnectTest.php tests/Feature/Threads/ThreadsConnectionTokenTest.php`

### Step 9: Shared threadsConnection prop resource [backend]

- Requirements: R2, R5, R11; constraints R-1, R-4, R-14
- Files: create `app/Http/Resources/ThreadsConnectionSummaryResource.php`; modify `app/Http/Middleware/HandleInertiaRequests.php`
- Do: resource emits exactly `status, disconnectReason, username`. `threadsConnectionFor()` keeps its one-line read and returns `null` or `(new ThreadsConnectionSummaryResource($connection))->resolve($request)`; the shared prop stays a closure.
- Skills: `laravel-best-practices`, `inertia-vue-development`
- Generate: `php artisan make:resource ThreadsConnectionSummaryResource --no-interaction`
- Tests: existing `tests/Feature/Threads/ThreadsSharedPropTest.php` stays green
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/ThreadsSharedPropTest.php`

### Step 10: Meta callbacks and data-deletion page [backend]

- Requirements: R1, R2, R6, R11, R13; constraints R-1, R-6, R-9, R-10, R-14
- Files: create `app/Http/Resources/DataDeletionConfirmationResource.php`, `app/Http/Resources/DataDeletionRequestResource.php`, `app/Services/Threads/DataDeletionLookup.php`; modify `app/Actions/Threads/DisconnectThreadsAccountsForMeta.php`, `app/Http/Controllers/Threads/MetaCallbackController.php`, `app/Http/Controllers/Legal/DataDeletionController.php`
- Do:
    - `DisconnectThreadsAccountsForMeta::handle()` logs `Log::info('Threads uninstall callback processed.', ['user_id' => $threadsUserId, 'matched_connections' => $matched])` after the existing warning, unconditionally (same as now).
    - `MetaCallbackController`: keep try/catch, `signedRequestFrom()` and `reject()`; `uninstall` no longer logs; `dataDeletion` returns `response()->json((new DataDeletionConfirmationResource($deletionRequest))->resolve($request))` — never the resource itself (R-6).
    - `DataDeletionConfirmationResource`: `url` (`url('/data-deletion').'?code='.$code`), `confirmation_code`.
    - `DataDeletionLookup::lookup(mixed $code): array{deletionRequest: ThreadsDataDeletionRequest|null, codeNotFound: bool}` — null/'' → `[null, false]` with no query; non-string or regex miss → `[null, true]` with no query; else query by `confirmation_code`.
    - `DataDeletionRequestResource`: `confirmationCode, status, requestedAt, completedAt`.
    - `DataDeletionController::show` renders `deletionRequest` (null ternary + resource) and `codeNotFound` from the lookup.
- Skills: `laravel-best-practices` (incl. `rules/security.md`, `rules/error-handling.md`)
- Generate: `php artisan make:resource <Name> --no-interaction`; `php artisan make:class Services/Threads/DataDeletionLookup --no-interaction`
- Tests: existing `tests/Feature/Threads/MetaUninstallCallbackTest.php`, `MetaDataDeletionCallbackTest.php`, `DataDeletionPageTest.php`, `tests/Feature/LegalPagesTest.php` stay green
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/MetaUninstallCallbackTest.php tests/Feature/Threads/MetaDataDeletionCallbackTest.php tests/Feature/Threads/DataDeletionPageTest.php tests/Feature/LegalPagesTest.php`

### Step 11: Focused tests for newly callable logic — test-writer [backend]

- Requirements: R8
- Files: create `tests/Feature/Threads/DashboardStatsTest.php`, `tests/Feature/Threads/PostListingTest.php`, `tests/Feature/Threads/CompleteThreadsAuthorizationTest.php`, `tests/Unit/ManualSearchRunOutcomeTest.php`. No existing test file is touched.
- Do (test-writer, following `testing-best-practices` and `tests/Concerns/InteractsWithThreads.php`):
    - `DashboardStatsTest::test_activity_counts_posts_per_client_day_for_the_last_30_days` — 30 entries oldest first; a post just before and just after client-zone midnight lands on the right day; other users' posts and posts older than the window are excluded; `found` is int.
    - `PostListingTest::test_a_page_beyond_the_last_page_is_clamped_to_the_last_page`, `::test_an_empty_result_is_page_one_of_one`.
    - `CompleteThreadsAuthorizationTest::test_a_state_mismatch_wins_over_an_error_parameter`, `::test_an_error_parameter_wins_over_a_malformed_code`, `::test_the_state_is_consumed_even_when_the_callback_fails` (call the action directly with a session store; stub `ConnectThreadsAccount` in the container so no HTTP is sent).
    - `ManualSearchRunOutcomeTest::test_a_queued_run_maps_to_started`, `::test_an_unknown_reason_falls_back_to_run_in_progress`, `::test_cooldown_and_pause_times_are_formatted_in_the_client_timezone` (pure PHP unit test, no framework boot).
    - No test for `ThreadsCallbackOutcome`/`ThreadsDisconnectOutcome` copy: it is a declaration already asserted end-to-end by `ThreadsCallbackTest` and `ThreadsDisconnectTest` (the skill forbids repeating declarations).
- Skills: `testing-best-practices`
- Generate: `php artisan make:test --phpunit <Name> --no-interaction` (`--unit` for the unit test)
- Tests: the new tests above pass
- Verify: `.claude/scripts/run-tests.sh tests/Feature/Threads/DashboardStatsTest.php tests/Feature/Threads/PostListingTest.php tests/Feature/Threads/CompleteThreadsAuthorizationTest.php tests/Unit/ManualSearchRunOutcomeTest.php`

## 5. Order, dependencies and execution

- Step 1 before Steps 2 and 3: both controllers switch `searchStatus` to the new resource in Step 1, so later steps edit already-migrated files.
- Step 2 and Step 4 both add methods to `SearchBudget`; sequential order avoids conflicts.
- Step 7 is independent of Step 8, but both add files to `app/Enums`; keep them sequential.
- Step 11 runs after Steps 1–10 (test-after).
- single-agent: one `implementer` runs Steps 1–10 in order, running `vendor/bin/pint --dirty --format agent` after each step; then `test-writer` runs Step 11; then `check-runner` runs the full suite (expect 364 + new tests green), PHPStan, Pint and `npm run check`/`types:check` once over the whole change. `git diff --stat -- resources/ routes/ tests/` must show no changes other than the new test files.

## 6. Traceability

No specification: requirements traced in section 1.

| Requirement | Steps                          |
| ----------- | ------------------------------ |
| R1          | 2, 3, 4, 5, 6, 7, 8, 10        |
| R2          | 1, 2, 3, 4, 8, 9, 10           |
| R3          | 3, 4                           |
| R4          | 6                              |
| R5          | 1, 9                           |
| R6          | 10                             |
| R7          | 7, 8                           |
| R8          | 11                             |
| R9          | none (out-of-scope constraint) |
| R10         | 1, 2, 5, 6, 8, 10              |
| R11         | 2, 3, 4, 8, 9, 10              |
| R12         | 6                              |
| R13         | 10                             |
| R14         | all; final checks              |

## 7. Requires approval

- Nothing. No new dependency, no new base folder (`app/Http/Resources`, `app/Enums`, `app/Actions/Threads`, `app/Services/Threads`, `tests/Unit` exist), no migration.

## 8. Risks and open questions

- Array-backed resources and larastan level 7: `$this->resource` is `mixed`; use a local `@var` array shape in each `toArray()` to avoid new PHPStan errors.
- `JsonResource::collection()` over arrays (activity) must receive a `Collection`/array of arrays; verify `resolve()` returns a 0-indexed list (assumption A2).
- `ThreadsPostPageResource` must not extend `ResourceCollection`; a paginator inside a ResourceCollection adds `links`/`meta` on `toResponse()` (R-2).
- Assumption A1 (Session contract `pull()`) — fall back to `Illuminate\Session\Store` if needed.
- Moving the info log into `DisconnectThreadsAccountsForMeta` means direct calls (only `MetaUninstallCallbackTest.php:99-120`) now also log; that test asserts no logs, so it stays green.
- `keywordOptions` stays a plain string list without a Resource (section 3); if the user wants a Resource there too, it is a one-file addition.

## 9. Notes for architecture and security review

- Check every new Resource whitelists fields and none of them or the enums expose `access_token` or other token columns (R-14).
- Check `CompleteThreadsAuthorization::handle()` keeps the state pull first and `hash_equals` comparison (CSRF on OAuth), and that the session key is unchanged.
- Check that no controller method holds more than one-line reads, flashes and returns, and that every Inertia/JSON resource is `->resolve()`-d (R-1).
- Check `DataDeletionLookup` never queries with a malformed code (R-9) and that the Meta controller still logs nothing from the signed request (R-10).
- Check the Actions/Services split matches `laravel-best-practices` `rules/architecture.md`.

## Changelog

- 2026-10-23 12:04 — created
- 2026-10-23 — review finding AR-1 (user-approved): the OAuth callback check chain moved from `ThreadsOAuth::handleCallback()` (Service → Action dependency, contradicting R10) to the Action `CompleteThreadsAuthorization::handle()`; `ThreadsOAuth` keeps only `authorizationUrl()` and `STATE_SESSION_KEY`; Step 11 test renamed `ThreadsOAuthTest.php` → `CompleteThreadsAuthorizationTest.php` (same three methods). Updated sections 3, 4 (Steps 8, 11) and 9.
