# Backend Engineering Insights

Raw, append-only session notes for the Laravel backend (app/**, routes/**,
database/**, tests/**, config/**, bootstrap/**). Maintained by the
`engineering-insights` skill (`.claude/skills/engineering-insights/`). Never
edit or delete existing entries — corrections are new dated entries. This is
not a vetted rules store; see `.ai/rules` (if present) for approved, durable
conventions.

## What Works

## What Doesn't Work

## Codebase Patterns

## Tool & Library Notes

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
