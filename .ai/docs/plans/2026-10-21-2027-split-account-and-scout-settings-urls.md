# Development Plan: Move account settings to `/account/*`, leave `/settings/*` to scout settings

- Created: 2026-10-21 20:27
- Status: draft
- Request: Architecture review finding AR-2 (user decision 2026-10-21). Account settings (profile, security, appearance; everything in `routes/settings.php`) move to `/account/*` with their route names unchanged. `/settings/*` then belongs only to scout settings. `/settings` redirects to scraping (the main-nav "Settings" target), and a new `/account` redirects to profile.

## 1. Goal and acceptance criteria

- [ ] `GET /account/profile`, `/account/security`, `/account/appearance` render the account pages. `PATCH /account/profile`, `DELETE /account/profile` and `PUT /account/password` work. Route names are unchanged: `profile.edit|update|destroy`, `security.edit`, `user-password.update`, `appearance.edit`.
- [ ] `GET /account` redirects to `/account/profile` (only for authenticated users; guests go to login).
- [ ] `GET /settings` redirects to `/settings/scraping`.
- [ ] `/settings/scraping|ai-filtering|threads|integrations` still render the scout pages with the same route names (`settings.scraping`, `settings.aiFiltering`, `settings.threads`, `settings.integrations`).
- [ ] The old account URLs (`/settings/profile`, `/settings/security`, `/settings/appearance`, `/settings/password`) return 404. This is the recommended default; see Open question 1.
- [ ] Account pages use the Inertia page directory `account/` (`account/Profile`, `account/Security`, `account/Appearance`). `app.ts` picks the account sub-layout by the `account/` prefix. Nothing is named "settings" for the account area apart from the PHP controller namespace (see Decisions).
- [ ] The main-nav "Settings" item (`AppSidebar.vue`) is not highlighted on `/account/*` pages and is still highlighted on all `/settings/*` scout pages.
- [ ] `/.well-known/passkey-endpoints` returns `/account/security` URLs.
- [ ] The full suite passes in docker. `pint`, `composer types:check`, `npm run check` and `npm run types:check` are clean.

## 2. Context

- Modules and files in scope:
    - `routes/settings.php`: account routes. `settings` redirect (line 9), `auth` group (8-13), `auth, verified` group (15-27), well-known passkeys endpoint (29-34).
    - `routes/web.php:28-31`: scout settings routes (flat, not grouped). `require __DIR__.'/settings.php'` is on line 35.
    - `app/Http/Controllers/Settings/ProfileController.php:22`: `Inertia::render('settings/Profile', ...)`.
    - `app/Http/Controllers/Settings/SecurityController.php:50`: `Inertia::render('settings/Security', $props)`.
    - `resources/js/pages/settings/{Profile,Security,Appearance}.vue`: account pages. Breadcrumbs use Wayfinder `edit()` helpers, so they need no URL changes.
    - `resources/js/pages/scout-settings/*.vue`: scout pages. They already use a distinct directory and fall through to the default `AppLayout` in `app.ts`.
    - `resources/js/app.ts:21-22`: `name.startsWith('settings/')` → `[AppLayout, SettingsLayout]`. This applies only to the account pages, which makes the name misleading.
    - `resources/js/layouts/settings/Layout.vue`: account sub-layout (Profile/Security/Appearance side nav, heading "Settings").
    - `resources/js/components/AppSidebar.vue:31-36`: main-nav "Settings" → `scraping()`, `activeMatch: '/settings'`.
    - `resources/js/components/NavMain.vue:17-23`: `currentUrl.startsWith(item.activeMatch)`.
    - `resources/js/components/UserMenuContent.vue:40-44`: "Account settings" → `profile.edit`. It uses the route name, so it needs no change.
    - `resources/js/components/settings/*`: scout-settings components (`ScoutSettingsTabs`, `*SettingsForm`, `ThreadsConnectionCard`). They stay as they are.
    - `resources/js/pages/Dashboard.vue:22`: imports `aiFiltering` from `@/routes/settings`. Unaffected.
    - `tests/Feature/Settings/ProfileUpdateTest.php`, `SecurityTest.php`: use `route(...)` names everywhere (confirmed). `SecurityTest` asserts `->component('settings/Security')` on lines 34 and 72; this must change.
- Facts checked:
    - No hardcoded `/settings` URL exists in `app/`, `config/`, `bootstrap/`, `resources/views`, `README.md` or `vite.config.ts`. The only literal paths are the two `Route::redirect` targets and `activeMatch: '/settings'`.
    - Fortify uses `prefix => ''` and `home => '/dashboard'`. All its routes are under `/user/*`, `/two-factor-challenge` and `/passkeys/*`, so none of them live under `/settings`. `Features::updatePasswords()` is not enabled, so the app's own `user-password.update` route does not collide with Fortify's.
    - `RequirePassword` on `security.edit` redirects to `password.confirm` (`/user/confirm-password`) and then back to the intended URL. That URL comes from the request, so it follows the move automatically.
    - `config/inertia.php:66` has `'ensure_pages_exist' => true`. `assertInertia()->component('account/Security')` fails until the `.vue` file exists, so the page move and the render-name change must land in the same step.
    - `resources/js/routes`, `resources/js/actions` and `resources/js/wayfinder` are gitignored and generated. Route names are kept, so the generated module paths (`@/routes/profile`, `@/routes/security`, `@/routes/appearance`, `@/routes/settings`, `@/actions/App/Http/Controllers/Settings/*`) do not change. Only the URLs inside them change.
    - No `.ai/rules` directory exists.
- Relevant insights:
    - 2026-09-18: `defineOptions({ layout: {...} })` only passes props. The wrapping layout is chosen by the page-name prefix in `app.ts` (`.ai/insights/frontend.md`). This is why renaming the page directory has to come with an `app.ts` case change.
    - 2026-09-18: Wayfinder groups helpers by the first segment of the route name. Run `php artisan wayfinder:generate --with-form` after route changes (`.ai/insights/frontend.md`).
    - 2026-10-21: run tests only in docker with `docker compose exec -T laravel.test php artisan test --compact`. The 18-failing-tests open question (2026-09-18) is resolved by the `<server name="APP_ENV">` line in `phpunit.xml` (`.ai/insights/backend.md`).
    - 2026-10-21: agent Bash guards split on `|`. Use `grep -e a -e b` (`.ai/insights/backend.md`).
- Existing code to reuse: the `Route::redirect` / `Route::inertia` patterns already in both route files, and Wayfinder route-name helpers, which need no hardcoded URLs in Vue.
- Assumptions:
    - A1: The app has no production users with bookmarked `/settings/profile` links, because it is pre-launch and the Threads OAuth backend is still a mock. This is the reason 404 is recommended over legacy redirects. If this is wrong, see Open question 1.
    - A2: Renaming the PHP controller namespace `App\Http\Controllers\Settings` is not wanted. It would change Wayfinder action import paths in `Profile.vue`/`Security.vue` and the test namespace, for little gain.

## 3. Decisions and constraints

- **Rename the Inertia page directory `pages/settings` → `pages/account`** and the sub-layout `layouts/settings/Layout.vue` → `layouts/account/Layout.vue`. In `app.ts`, replace the `settings/` case with `name.startsWith('account/')` → `[AppLayout, AccountLayout]`. Reason: after the split, `settings/` would sit beside `/settings/*` URLs that render `scout-settings/*`, which is exactly the misleading pairing the review flagged. Page directory = URL area = layout name keeps it obvious. Keep `pages/scout-settings` as it is; renaming it to `pages/settings` would invite the confusion back.
- **Rename `routes/settings.php` → `routes/account.php`** with `git mv`, and update the `require` in `web.php`. This is a one-line change that keeps the file name consistent with its URL space. The well-known passkeys route stays in this file because it is about account security.
- **Group scout routes** in `routes/web.php` under `Route::prefix('settings')->name('settings.')->group(...)` inside the existing `auth, verified` group, with the `/settings` redirect as the first entry (`Route::redirect('/', '/settings/scraping')`). This is low cost and makes `/settings/*` visibly owned by one block. No new controllers.
- **Redirect targets are literal paths** (`'/settings/scraping'`, `'/account/profile'`), like the existing `Route::redirect`. `route()` is not reliably resolvable while route files are being loaded.
- **Middleware for redirects:** `/account` stays in the `auth` group, like today's `settings` redirect. `/settings` sits in the scout group (`auth, verified`), so an unverified user is not redirected to a page that would bounce them to verification anyway.
- **Old account URLs return 404** (no legacy redirects). This is recommended under A1, keeps `/settings/*` exclusively scout-owned, and avoids permanent redirects that browsers cache. This is open question 1.
- **Main-nav active state:** keep `activeMatch: '/settings'` in `AppSidebar.vue`. Once account pages leave `/settings`, it no longer matches them, which satisfies the criterion with no code change. Tighten it to `'/settings/'` only if the implementer finds a top-level path starting with `/settings` other than the scout area (there is none today). Do not change `NavMain.vue`.
- **Account layout copy:** change the `Heading` title in the account layout from "Settings" to "Account" and the side-nav `aria-label="Settings"` to `aria-label="Account"`, so the account area no longer presents itself as "Settings". The description stays the same. This is copy only, with no tests.
- Keep the controller namespace `App\Http\Controllers\Settings` and the test directory `tests/Feature/Settings` (A2).
- Out of scope: AR-1 (email verification), AR-3 (Threads OAuth backend), renaming `components/settings/*`, renaming route names, changing Fortify config.

## 4. Steps

### Step 1: Rename account pages and sub-layout to `account/` [backend + frontend, atomic]

- Files:
    - `git mv resources/js/pages/settings resources/js/pages/account`
    - `git mv resources/js/layouts/settings resources/js/layouts/account`, keeping the file name `Layout.vue`
    - modify `resources/js/app.ts`: import `AccountLayout from '@/layouts/account/Layout.vue'` and add the case `name.startsWith('account/')` → `[AppLayout, AccountLayout]`. Remove the `settings/` case and the `SettingsLayout` import.
    - modify `app/Http/Controllers/Settings/ProfileController.php:22` → `'account/Profile'`
    - modify `app/Http/Controllers/Settings/SecurityController.php:50` → `'account/Security'`
    - modify `routes/settings.php`: in `Route::inertia(..., 'settings/Appearance')`, change only the component to `'account/Appearance'` (the URL changes in Step 2)
    - modify `tests/Feature/Settings/SecurityTest.php:34,72`: `->component('account/Security')`
    - modify `resources/js/layouts/account/Layout.vue`: Heading title "Account", nav `aria-label="Account"`
- Do: Rename only. URLs stay the same in this step, so Step 1 is verifiable on its own.
- Skills: `inertia-vue-development`, `laravel-best-practices`
- Generate: none
- Tests: update the `SecurityTest` component assertions (above).
- Verify: `docker compose exec -T laravel.test php artisan test --compact tests/Feature/Settings`, `npm run types:check`, `vendor/bin/pint --dirty --format agent`

### Step 2: Move account routes to `/account/*` and group scout routes [backend]

- Files: `git mv routes/settings.php routes/account.php`; modify `routes/account.php`, `routes/web.php`
- Do:
    - In `routes/account.php`, replace every `settings/...` URI with `account/...`: `account/profile` (GET/PATCH/DELETE), `account/security`, `account/password`, `account/appearance`. Keep every `->name(...)`, the middleware and the group structure unchanged.
    - Replace `Route::redirect('settings', '/settings/profile')` with `Route::redirect('account', '/account/profile')` in the `auth` group.
    - In `routes/web.php`, change the `require` to `__DIR__.'/account.php'`. Wrap the four scout routes in `Route::prefix('settings')->name('settings.')->group(function () { ... })` inside the existing `auth, verified` group. Inside the group, first add `Route::redirect('/', '/settings/scraping');`, then `Route::inertia('scraping', 'scout-settings/Scraping')->name('scraping')`, `ai-filtering` → `aiFiltering`, `threads`, `integrations`.
    - Do not add legacy `/settings/profile` redirects (pending Open question 1).
- Skills: `laravel-best-practices`
- Generate: none in this step (Step 3 regenerates)
- Tests: covered in Step 4. The existing `ProfileUpdateTest`/`SecurityTest` use route names and must still pass unchanged.
- Verify: `php artisan route:list --path=settings` (only the 4 scout routes plus the redirect), `php artisan route:list --path=account` (all account routes, names unchanged), `docker compose exec -T laravel.test php artisan test --compact tests/Feature/Settings`

### Step 3: Regenerate Wayfinder and check frontend references [frontend]

- Files: generated `resources/js/routes/**`, `resources/js/actions/**` (gitignored); no source edits expected
- Do: Regenerate. Confirm `@/routes/profile`, `@/routes/security`, `@/routes/appearance`, `@/routes/settings` and `@/actions/App/Http/Controllers/Settings/*` still exist with the same exports and new `/account/...` URLs. Then run `grep -rn -e "'/settings" -e "layouts/settings" -e "pages/settings" resources/js --include=*.vue --include=*.ts` outside the generated dirs. The only expected hit is `activeMatch: '/settings'` in `AppSidebar.vue`, which stays as decided. Open an account page and a scout page in the browser: the account pages show the account side nav, the main-nav "Settings" item is highlighted only on scout pages, and the user menu "Account settings" opens `/account/profile`.
- Skills: `wayfinder-development`, `inertia-vue-development`
- Generate: `php artisan wayfinder:generate --with-form --no-interaction` (run inside docker if the host cannot boot the app)
- Tests: none (no frontend test harness; behavior covered by Step 4 feature tests)
- Verify: `npm run types:check`, `npm run check`

### Step 4: Feature tests for the new URL split [backend]

- Files: create `tests/Feature/Settings/SettingsUrlSplitTest.php` (`php artisan make:test --phpunit Settings/SettingsUrlSplitTest --no-interaction`). Follow the existing style in `tests/Feature/Settings/*` and `LegalPagesTest`: `test_` prefix, `RefreshDatabase`, `User::factory()`, and `AssertableInertia as Assert`.
- Do: These tests use literal URLs on purpose, because the URL is the contract under change. Add a short class PHPDoc saying so. Tests:
    - `test_guest_visiting_account_root_is_redirected_to_login`: `get('/account')` → `assertRedirect(route('login'))`
    - `test_account_root_redirects_to_profile_page`: as user, `get('/account')` → `assertRedirect('/account/profile')`
    - `test_profile_page_renders_under_account_url`: `get('/account/profile')` → `assertOk()` plus component `account/Profile`
    - `test_settings_root_redirects_to_scraping_settings`: as verified user, `get('/settings')` → `assertRedirect('/settings/scraping')`
    - `test_scraping_settings_page_renders_under_settings_url`: `get('/settings/scraping')` → `assertOk()` plus component `scout-settings/Scraping`
    - `test_old_account_settings_url_returns_404`: `get('/settings/profile')` → `assertNotFound()`. If Open question 1 is answered "permanent redirect", replace it with `assertRedirect('/account/profile')` plus a 301 status assertion.
    - `test_passkey_well_known_endpoints_point_to_account_security`: `get(route('well-known.passkeys'))` → `assertExactJson(['enroll' => url('/account/security'), 'manage' => url('/account/security')])`. This is an external, browser-consumed contract.
- Skills: `testing-best-practices` (read `rules/naming.md`, `rules/assertions.md`, `rules/endpoint-tests.md`)
- Generate: `php artisan make:test --phpunit Settings/SettingsUrlSplitTest --no-interaction`. If `make:test` rejects the subdirectory form, create the file by hand in `tests/Feature/Settings/`.
- Tests: the file above
- Verify: `docker compose exec -T laravel.test php artisan test --compact tests/Feature/Settings/SettingsUrlSplitTest.php`, then the full suite `docker compose exec -T laravel.test php artisan test --compact` (expect 41 + 7 = 48 passed), then `vendor/bin/pint --dirty --format agent`, `composer types:check`

### Step 5: Record the insight [backend + frontend docs]

- Files: append to `.ai/insights/frontend.md` (and to `backend.md` if useful), using the `engineering-insights` skill. These files are append-only: never edit the outdated 2026-09-18 entry; add a dated entry instead.
- Do: Add a 2026-10-21 entry with these points:
    - Account pages are under `/account/*` with page prefix `account/` and layout `layouts/account/Layout.vue`.
    - `/settings/*` is scout-only (`pages/scout-settings`, default `AppLayout`).
    - The main-nav `activeMatch: '/settings'` relies on that split.
    - Optionally add a backend note that the 2026-09-18 "18 failing tests" open question is closed (already explained by the 2026-10-21 entry), so readers do not act on it.
- Skills: `engineering-insights`
- Generate: none
- Tests: none: documentation
- Verify: `npx --no-install vp check .ai/insights/frontend.md .ai/insights/backend.md`

## 5. Order and dependencies

- Step 1 must land as one unit: `ensure_pages_exist` makes `SecurityTest` fail if the render name changes before or after the `.vue` move, and `app.ts` must match the new prefix or the pages lose their sub-layout.
- Step 2 depends on Step 1 only for the `account/Appearance` component name (it can be done in either order, but Step 1 first keeps each diff verifiable).
- Step 3 depends on Step 2 (the URLs inside generated helpers change).
- Step 4 depends on Steps 1-2. Step 5 comes last.

## 6. Requires approval

- Nothing. No new dependency, no new base folder (`pages/account` and `layouts/account` replace existing `settings` subfolders under existing base folders), and no migration.

## 7. Risks and open questions

- **Open question 1:** What should the old account URLs (`/settings/profile|security|appearance|password`) do? The recommendation is 404 (A1: pre-launch, only internal Wayfinder links, keeps `/settings/*` purely scout). The alternative is `Route::permanentRedirect('settings/profile', '/account/profile')` and similar for the GET pages only, placed in `routes/account.php`. Choose this if any external links or bookmarks matter. Note that browsers cache 301s, so the redirects would be hard to remove later.
- Risk: a user with a cached SPA bundle (old Wayfinder URLs) posting to `PATCH /settings/profile` gets a 404 until reload. This is acceptable pre-launch.
- Risk: `php artisan wayfinder:generate` on the host may fail if the app cannot boot without the docker DB. Run it inside the container, or rely on `npm run dev`/`build` (the Vite plugin regenerates).
- Risk: `Route::redirect('/', ...)` inside `prefix('settings')` must register as URI `settings` (not `settings/`). Confirm with `route:list --path=settings` in Step 2.

## 8. Notes for architecture and security review

- Architecture: check that the URL area, page directory and layout prefix now align (`/account` ↔ `account/` ↔ `layouts/account`; `/settings` ↔ `scout-settings/` ↔ default layout). Check whether keeping the `App\Http\Controllers\Settings` namespace (A2) is acceptable or should become a follow-up. Check the decision to keep `activeMatch` as a literal prefix in `AppSidebar.vue`.
- Security: middleware must be preserved exactly after the move. `security.edit` keeps `RequirePassword`, `user-password.update` keeps `throttle:6,1`, `profile.destroy`/`security.edit`/`appearance.edit` keep `verified`, and `/account` and `/settings` redirects are behind `auth`. The password-confirmation intended-URL round trip goes to `/account/security`. The well-known passkeys endpoint advertises the new URL.

## Changelog

- 2026-10-21 20:27 — created
