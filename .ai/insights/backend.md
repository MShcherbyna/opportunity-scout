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

## Recurring Errors & Fixes

## Session Notes (datestamped)

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
