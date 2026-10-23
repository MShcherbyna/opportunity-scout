# Backend Engineering Insights

Raw, append-only session notes for the Laravel backend (app/**, routes/**,
database/**, tests/**, config/**, bootstrap/**). Maintained by the
`engineering-insights` skill (`.claude/skills/engineering-insights/`). Never
edit or delete existing entries — corrections are new dated entries. This is
not a vetted rules store; see `.ai/rules` (if present) for approved, durable
conventions.

## What Works

## What Doesn't Work

### 2026-09-18

Shell `export VAR=x` before `php artisan serve &` did not reliably reach the
request-handling process in this sandboxed dev environment — the server kept
reading the original `.env` values (mysql/database session) even though a
foreground `php artisan tinker --execute 'echo config(...)'` in the same
shell correctly showed the override. To point the app at a throwaway SQLite
DB + file sessions for a local check without Docker/MySQL, back up and edit
`.env` directly instead (it's gitignored), then restart `serve`.
Evidence: `.env` (`DB_CONNECTION`, `SESSION_DRIVER`), `docker-compose.yml`
(mysql-hostname-only setup)

## Codebase Patterns

### 2026-10-23

Thin controllers, the approach used for the Threads feature (refactor plan
`2026-10-23-1204-thin-threads-controllers`). This is a draft for CLAUDE.md:

- A controller only reads the request (FormRequest accessors such as
  `$request->filters()`), makes one call per prop or operation, flashes, and
  returns. A one-line DB read (`->count()`, `config()`) may stay inline.
- Workflows that change state are Actions in `App\Actions\Threads`, named with
  a verb and exposing `handle()`. Reads and calculations are Services in
  `App\Services\Threads`. An Action may depend on a Service, but a Service must
  never depend on an Action (review finding AR-1).
- Every prop or JSON shape, including scalar arrays, comes from an
  `App\Http\Resources\*Resource` with an explicit field whitelist and no
  `parent::toArray()`. Always call `->resolve($request)` on it, because an
  unresolved resource sent through Inertia or `response()->json()` gets
  wrapped in `{data: ...}` and a fresh model returns 201. A paginated page uses
  a `JsonResource` over the paginator, not a `ResourceCollection`, which would
  add `links`/`meta`.
- Toast text and type come from outcome enums (`toastType()`,
  `toastMessage()`/`toast()`), so the controller flashes in a single call.
- Keep Dashboard and Posts props as closures, so partial reloads work.
  Evidence: `app/Http/Controllers/Threads/*`, `app/Actions/Threads/CompleteThreadsAuthorization.php`,
  `app/Http/Resources/ThreadsPostPageResource.php`, `app/Enums/ManualSearchRunOutcome.php`

## Tool & Library Notes

### 2026-09-18

`Route::inertia($uri, $component, $props)` (the `inertiajs/inertia-laravel`
Router macro) only accepts a static `$props` array — it can't forward route
parameters like `{post}` into the Inertia props. For a page that needs a URL
param, register a plain closure route instead: `Route::get('posts/{post}',
fn (string $post) => Inertia::render('PostDetail', ['postId' => (int)
$post]))->name('posts.show');`.
Evidence: `vendor/inertiajs/inertia-laravel/src/ServiceProvider.php:155`,
`routes/web.php` (posts.show route)

### 2026-10-21

`.claude/` is gitignored except `.claude/skills/engineering-insights`, so the
project agents (`.claude/agents/*`) and their guard hooks (`.claude/hooks/*`)
exist only locally and never show up in `git status`/`git diff`. `vp check`
(`npm run check`) also skips gitignored paths, so Markdown under `.claude/` is
never format-checked by it — run `npx --no-install vp check <file>` on such
files directly. The agents' Bash guard hooks split commands on `|`, `;`, `&`
even inside quotes, so `grep "a|b"` is blocked; use `grep -e a -e b` or the
Grep tool.
Evidence: `.gitignore:32` — `/.claude/*`; `.claude/hooks/readonly-bash-guard.sh`

### 2026-10-23

Query-builder `update([...])` (e.g. a compare-and-set
`ThreadsConnection::whereKey(...)->where(...)->update()`) bypasses the model's
`encrypted` cast, so a token written that way is stored as plaintext and the
next cast read throws `DecryptException`. Encrypt it yourself with the same
encrypter the cast uses: `Crypt::encryptString($token)` (equivalent to
`currentEncrypter()->encrypt($v, false)` while no custom encrypter is set).
Evidence: `app/Models/ThreadsConnection.php` (`storeRefreshedTokenIfUnchanged`)

### 2026-10-23

Guzzle's `ConnectionException` message contains the full request URL. Threads
takes `access_token` as a query parameter, so wrapping that exception (as
`previous`, or by copying its message) leaks the token into logs and
`failed_jobs`. `ThreadsClient` throws a fresh `ThreadsApiException` without
`previous` for connection failures; keep it that way.
Evidence: `app/Services/Threads/ThreadsClient.php` (catch in `send()`)

### 2026-10-23

`Schedule::...->withoutOverlapping()` defaults to a 1440-minute mutex. With
`CACHE_STORE=database` the mutex survives restarts, so a SIGKILLed scheduler
blocks an every-minute command for up to 24 h with no error. Always pass an
expiry (`withoutOverlapping(5)` for the search dispatcher, 60 for the daily
commands). Also: `composer types:check` needs `--memory-limit=1G`, because the
host PHP CLI has `memory_limit=128M` and PHPStan's parallel worker crashes
there (it passes inside the container).
Evidence: `routes/console.php`, `composer.json` (`types:check` script)

### 2026-10-23

`vp check --fix` (`npm run check -- --fix`) also reformats untracked Markdown
under `.ai/docs/` (specs, plans, `_uk` translations, run ledgers) and pads
tables (`|---|` → `| --- |` with aligned columns). A script that edits those
files by matching the exact separator row then silently does nothing, so
match the table header with a regex, or re-read the file after a format run.
Evidence: `.ai/docs/runs/2026-10-23-0034--threads-connection-and-keyword-search.md`

### 2026-10-23

`JsonResource::resolve()` is declared `@return array`, so the precise PHPStan
array shape of a resource's `toArray()` never reaches the caller. Declare the
exact shape on `toArray()` and type the resolved value (e.g. a shared Inertia
prop helper) as `array<string, mixed>|null`; forcing the shape at the call site
needs an unchecked `@var`.
Evidence: `vendor/laravel/framework/src/Illuminate/Http/Resources/Json/JsonResource.php:114-120`;
`app/Http/Middleware/HandleInertiaRequests.php` (`threadsConnectionFor()`)

### 2026-10-23

Larastan level 7 infers `$query->pluck('col')->all()` as `array<mixed>`, so
returning it as `list<string>` fails. Map through a typed closure and
reindex — `array_values($q->pluck('keyword')->map(fn (mixed $k): string => $k)->all())`
— instead of adding an ignore. Also, `SomeBackedEnum::tryFrom(null)` is a
Larastan error (and a PHP 8.1+ deprecation, because the built-in parameter is
non-nullable), so check for null before calling `tryFrom()`.
Evidence: `app/Services/Threads/PostListing.php` (`keywordOptions()`),
`app/Enums/ManualSearchRunOutcome.php` (`fromResult()`)

### 2026-10-23

These run-plan agent guard limits are not obvious from the error text (they add to
the 2026-10-21 guard entry):

- The implementer guard rejects any Bash command that both writes (heredoc or
  python) and mentions `tests/`, even as a test-runner argument, so run
  `.claude/scripts/run-tests.sh tests/...` as its own call.
- The test-writer guard blocks `rm`, so it cannot rename or delete a test file.
  The orchestrator has to get the user's OK for the deletion.
- The check-runner guard rejects `$(...)` command substitution.
- The plan-verifier guard blocks `git ls-tree`/`git hash-object` and treats
  `->` in a grep pattern as output redirection.
- Passing `bootstrap` as a Pint path pulls in the gitignored
  `bootstrap/cache/*.php` and makes `pint --test` fail, so use
  `vendor/bin/pint --test --dirty`.
  Evidence: `.claude/hooks/implementer-bash-guard.sh`, `.claude/hooks/readonly-bash-guard.sh`;
  `.ai/docs/runs/2026-10-23-1204-thin-threads-controllers.md` (log)

## Recurring Errors & Fixes

### 2026-10-21

Root cause of the 18 failing auth/session tests (Open Questions, 2026-09-18):
`docker-compose.yml` sets `APP_ENV: local` in the container environment, so it
lands in `$_SERVER`, which Laravel's env repository reads before `$_ENV`.
`phpunit.xml`'s `<env name="APP_ENV" value="testing"/>` therefore lost, the app
ran tests as `local`, CSRF stayed active and every POST/PATCH/DELETE got 419.
`<env ... force="true"/>` does NOT fix it (PHPUnit's force only sets
`putenv`/`$_ENV`); `<server name="APP_ENV" value="testing"/>` does (PHPUnit sets
`$_SERVER` unconditionally). Full suite in docker went 18 failed → 41 passed.
Run tests with `docker compose exec -T laravel.test php artisan test --compact`
— on the host the DB host `mysql` does not resolve.
Evidence: `docker-compose.yml:11` — `APP_ENV: local`; `phpunit.xml:21` — added
`<server>` line; `vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:119-147`
— `$_SERVER` set unconditionally vs `force` only affecting `putenv`/`$_ENV`

### 2026-10-23

Laravel's `StartSession` stores the full URL of every GET request, query string
included, in the session key `_previous.url`. A test asserting "the OAuth
`error_description` value never reaches the session" therefore always fails,
even though the controller never stores it. Exclude the framework key:
`Arr::except(session()->all(), ['_previous'])`. The real UI-1 risk is
displaying the value, not this key.
Evidence: `tests/Feature/Threads/ThreadsCallbackTest.php` (DT-1,
`test_error_parameter_leaves_the_connection_unchanged_and_never_echoes_it`)

## Session Notes (datestamped)

### 2026-10-21

After renaming/moving an Inertia page, feature tests that render it fail with
500 `Unable to locate file in Vite manifest: resources/js/pages/<new>.vue`
until `npm run build` refreshes the gitignored `public/build` manifest (the
docker container reads the host's `public/build`). Run `npm run build` before
the suite after page renames. The 2026-09-18 "18 failing tests" open question
below is closed (see the 2026-10-21 Recurring Errors entry).
Evidence: `public/build` is gitignored; suite went 44 passed/4 failed ->
48 passed after `npm run build`.

## Open Questions

### 2026-09-18

18 of 39 tests fail, all auth/session flows: `Tests\Feature\Auth\
AuthenticationTest`, `PasswordResetTest`, `RegistrationTest`,
`TwoFactorChallengeTest`, `VerificationNotificationTest`, and
`Tests\Feature\Settings\ProfileUpdateTest`, `SecurityTest`. Failures are
either a 419 (CSRF/session token mismatch) or "The user is not
authenticated" right after a POST that otherwise looks successful — e.g.
`RegistrationTest::test_new_users_can_register` fails asserting the user is
authenticated post-registration. `phpunit.xml` already sets
`SESSION_DRIVER=array` for tests, ruling out a missing sessions table.
Reproduces identically via `php artisan test` and a direct `vendor/bin/phpunit
--filter=RegistrationTest`, so it isn't an artisan-test wrapper quirk — session
state isn't surviving across simulated requests within a single test. Root
cause not yet diagnosed. Confirmed unrelated to the Docker setup (build,
migrate, npm build, and a real HTTP request all work) — an app-level issue in
the fresh Fortify scaffold.
Evidence: `tests/Feature/Auth/RegistrationTest.php:36`,
`vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/InteractsWithAuthentication.php:64`,
`phpunit.xml:30`
