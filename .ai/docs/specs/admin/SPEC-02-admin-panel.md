# Spec: Admin panel

Spec ID: SPEC-02
Status: draft
Supersedes: `.ai/docs/specs/threads/SPEC-01-threads-connection-and-keyword-search.md`, in part only (D-15): (1) NFR-6: admins read every user's keywords, search settings, found posts (read-only), Threads connection status and last search run (AC-7, AC-9, AC-47); (2) AC-45, AC-71/AC-72 and AC-88 are extended: no scheduled or manual search run starts while the global "Pause search" switch is on or the user is blocked, and the Dashboard and Posts page show that state (MC-6, AC-42, AC-88, AC-92). SPEC-01 itself is not edited; every other rule of SPEC-01 stays.

## Problem and user

Opportunity Scout has no operator role: every account is a regular user, and `users` has no role (S-4). The operator cannot see who uses the service or what each user searches for (S-1). The AI provider key and the Telegram bot token are today per-user mock fields on the user's Settings tabs (S-3, S-4), but the operator wants one global OpenRouter connection that filters fetched Threads posts and one global Telegram bot through which users get notifications (S-1). Because the OpenRouter key is global, every user's posts spend the operator's credits, so the operator also needs to block or delete users, see the key's usage and pause the pipeline in an incident (S-10). This spec defines the admin area for these tasks; the AI filtering pipeline and Telegram linking and sending are separate specs (D-4).

Delivery workflow: the screens are designed in Claude Design and exported to `design/*.html`. The current canvas has no admin artboards; DG-11 lists the artboards the next handoff must export. Then: this spec (draft) → answers by ID → approve → `implementation-planner` → implementer, test-writer and reviews → `plan-verifier` → mark implemented (S-9).

## Goals / Non-goals

Goals:

- G-1 Only users with the admin role can open any admin page or run any admin action; the server checks this on every request, and non-admins get HTTP 404.
- G-2 An admin sees every registered user with their health state and, for any user, all keywords, search settings and found posts, without changing them.
- G-3 An admin connects, tests, replaces and removes one global OpenRouter connection (API key, model, default filtering criteria), checked with OpenRouter before it is stored; the key is never readable back.
- G-4 An admin connects, tests, replaces and removes one global Telegram bot token, checked with Telegram before it is stored; the token is never readable back.
- G-5 An admin grants and revokes the admin role, blocks, unblocks and deletes users, and pauses search, AI filtering or notifications globally; every such action is in the audit log, and at least one active admin always remains.
- G-6 The AI filtering, Telegram notification and Threads search features read the admin settings through the contracts MC-3, MC-4 and MC-6.

Non-goals:

- The AI filtering pipeline: sending posts to the model, prompt assembly, user on/off and per-user criteria override, verdict storage, relevance in the Posts UI and Dashboard, per-user AI usage and budgets, the AI test console (UX-9). All belong to the AI-filtering spec (D-4, D-3, MI-1, MI-3, UX-8, UX-9).
- Telegram chat linking, webhook registration, message sending, the user's Integrations tab (DG-7) and the delivery log (UX-12). All belong to the Telegram notifications spec (D-4, MI-2).
- Editing other users' keywords, search settings or found posts (S-1, D-8).
- The Threads app credentials. They stay in environment configuration (SPEC-01 NFR-3).
- System overview page (UX-7), failed-jobs view (UX-10) and 2FA reset (UX-13): deferred to a later spec.
- Impersonation (UX-17) and admin-editable search limits (UX-18): rejected.

## User stories

- US-1 As an admin, I want an area that only admins can open, so that operator tools and integration secrets are not exposed to other users.
- US-2 As an admin, I want to see a list of all users with their state, so that I know who uses the service and whose search is broken.
- US-3 As an admin, I want to see all keywords and search settings of any user, so that I understand what each user searches for.
- US-4 As an admin, I want to connect OpenRouter with an API key, a model and default filtering criteria, so that fetched posts can be filtered by AI.
- US-5 As an admin, I want to connect the Telegram bot, so that users can receive notifications through it.
- US-6 As the operator, I want to create the first admin with a console command, so that a fresh installation has an admin.
- US-7 As an admin, I want to grant and revoke the admin role in the panel, so that others can help operate the service.
- US-8 As an admin, I want to block, unblock and delete users, so that I can stop abuse of the global AI key and remove spam accounts.
- US-9 As an admin, I want to see each user's found posts read-only, so that I understand what their search finds.
- US-10 As an admin, I want a log of admin actions, so that changes to secrets, roles, users and switches are accountable.
- US-11 As an admin, I want global switches that pause search, AI filtering and notifications, so that I can stop spending or spamming during an incident.
- US-12 As an admin, I want to see all keywords across users, so that I can spot popular or abusive keywords.
- US-13 As an admin, I want to see the OpenRouter key's credit limit and usage, so that I can control spending.

## Decisions

The user's answers of 2026-10-23 (S-10), relayed by the coordinator. Each decision resolves the item it names.

- D-1 (Q-1, option C) The first admin is created by a console command; after that, admins grant and revoke the admin role in the panel (UX-14).
- D-2 (Q-2, option B) Non-admins get HTTP 404 for every admin URL, the app's standard not-found page (DG-9).
- D-3 (Q-3, option C) The admin sets a global default filtering instruction and relevance threshold; each user can override them with their own criteria (override in the AI-filtering spec).
- D-4 (Q-4, option A) This spec delivers only the admin side. The AI filtering pipeline and Telegram linking, webhook and sending get their own specs.
- D-5 (Q-5, option A) Users list: 25 per page, newest registration first, search by name or email, `search` and `page` in the URL.
- D-6 (Q-6, option A) The model is a free-text OpenRouter model id. API key, model id and bot token are at most 255 characters each.
- D-7 (Q-7, option A) An admin is a regular Scout account plus a role and appears in the users list with an admin badge.
- D-8 (Q-8, option B) The admin sees a read-only list of each user's found posts.
- D-9 DG-1 to DG-10 accepted as proposed; DG-10 includes light and dark variants and a 375 px variant. DG-6: the user AI tab keeps on/off and the user's own criteria override. DG-7: implemented in the Telegram spec.
- D-10 MI-1 accepted as proposed. MI-2: this spec only stores and checks the bot token; linking, webhook and sending are in the Telegram spec. MI-3: the AI filter consumes SPEC-01's `ThreadsPostsStored` event and marks stored posts; no SPEC-01 change. MI-4: while AI is not connected or failing, every new post notifies; while AI works, only relevant posts notify (details in the notifications spec).
- D-11 Accepted now: UX-1, UX-2, UX-3, UX-4, UX-5, UX-6, UX-8 (global key limit and usage on the AI page only), UX-11, UX-14 (with password confirmation and audit), UX-15 (with confirmation, password confirmation and audit; irreversible), UX-16.
- D-12 Deferred: UX-7 (later spec), UX-9 (AI-filtering spec), UX-10 (later spec), UX-12 (Telegram spec), UX-13 (later spec).
- D-13 Rejected: UX-17 (bypasses 2FA and passkeys; privacy), UX-18 (SPEC-01 derives caps from the Threads quota).
- D-14 Derived edge cases (S-10): no action may leave zero active admins; deleting a user removes their data and is audited without secret values; the SPEC-01 scheduler must respect the kill switch (MC-6).
- D-15 (MI-5) The exception to SPEC-01 stays in this spec's `Supersedes:`; SPEC-01 is not edited. It covers keywords, search settings and read-only posts, plus the UX-1 health data and the MC-6 extensions.

Second round of answers, 2026-10-23 (S-11):

- D-16 (Q-9, option A) A blocked user's open sessions end at their next request, and the login page shows "This account has been blocked."
- D-17 (Q-10, (a) A, (b) A) If the entered email already has an account, the admin-creation command grants that account the admin role and leaves its password unchanged. An account the command creates is marked email-verified at once.
- D-18 (Q-11, option B) Audit entries keep only the acting admin's id and the target user's id; a deleted person is shown as "Deleted user #{id}". Entries have no retention limit.
- D-19 (Q-12, (a) A, (b) A) While "Pause AI filtering" is on, every new post notifies, as when AI is not connected. Notifications that fall due while "Pause notifications" is on are dropped and never sent later.
- D-20 (Q-13) The default filtering instruction is at most 2,000 characters. Its starting value is the current design text (S-3), and the starting threshold is the design's 75 % (S-3).
- D-21 The defaults the spec-creator derived in round 1 are accepted: the admin tabs "Users", "Keywords", "AI", "Telegram", "System", "Audit"; the "Keywords" and "Audit" tabs show 25 per page, keywords ordered by number of users, most first; a user's posts show 10 per page, newest first; AI "Disconnect" removes only the key; a save is rejected when OpenRouter or Telegram can't be reached during its check; the users-list search term is at most 100 characters.

## Acceptance criteria (EARS)

An "active admin" is a user who holds the admin role and is not blocked.

### Admin access and navigation

- AC-1 (US-1, ubiquitous) The system shall treat a user as an admin only while that user's account holds the admin role. — source: S-1, D-1
- AC-2 (US-1, unwanted) IF a signed-in user who does not hold the admin role requests any admin page or admin action, THEN the system shall respond with HTTP 404 and the app's standard not-found page and change no data. — source: S-1, D-2, DG-9
- AC-3 (US-1, unwanted) IF a guest requests any admin page or admin action, THEN the system shall redirect to the login page. — source: S-1, S-4 (`auth` middleware, `routes/web.php:17`)
- AC-4 (US-1, unwanted) IF an admin whose email address is not verified requests any admin page, THEN the system shall redirect to the email verification notice. — source: S-4 (`verified` middleware, `routes/web.php:17`)
- AC-5 (US-1, state-driven) WHILE the signed-in user does not hold the admin role, the system shall show no link to the admin area on any page or menu. — source: S-1
- AC-31 (US-1, state-driven) WHILE the signed-in user holds the admin role, the system shall show the sidebar item "Admin" below "Settings", linking to the admin area under `/admin` with the tabs "Users", "Keywords", "AI", "Telegram", "System" and "Audit". — source: DG-1 (D-9), UX-3, UX-11, UX-16 (D-11), D-21

### Users list

- AC-6 (US-2, event-driven) WHEN an admin opens the users list, the system shall list every registered user, 25 per page, newest registration first. — source: S-1, D-5
- AC-7 (US-2, ubiquitous) The system shall show for each user in the users list the name, the email address, the registration date, an "Admin" badge for admins, a "Blocked" badge for blocked users, whether the email is verified, whether two-factor authentication is enabled, the Threads connection status (connected, disconnected with its reason, or not connected), the number of keywords, the number of found posts, and the time and outcome of the last search run. — source: S-1, D-7, UX-1, UX-4 (D-11), S-5 (SPEC-01 R-1 to R-4, AC-60)
- AC-51 (US-2, event-driven) WHEN an admin submits a search term in the users list, the system shall list only users whose name or email address contains the term. — source: D-5
- AC-52 (US-2, ubiquitous) The system shall keep `search` and `page` in the URL query of the users list, so that the back button and shared links restore them. — source: D-5, DG-2 (D-9)
- AC-53 (US-2, state-driven) WHILE a search term is active and no user matches it, the system shall show the state "No users match" with a "Clear search" button. — source: DG-2 (D-9)
- AC-54 (US-2, unwanted) IF the requested page number of an admin list is greater than its last page, THEN the system shall show the last page. — source: S-5 (SPEC-01 AC-64, same behaviour)
- AC-55 (US-2, state-driven) WHILE an Inertia visit for a search or page change in an admin list is in progress, the system shall show the existing table skeleton. — source: DG-2 (D-9), S-4 (`TableSkeleton.vue`)
- AC-8 (US-2, event-driven) WHEN an admin selects a user in the users list, the system shall open that user's detail page. — source: S-1

### User detail

- AC-9 (US-3, ubiquitous) The system shall show on a user's detail page the AC-7 fields of that user, the search interval, the first-search lookback, and every keyword of that user as read-only chips. — source: S-1, DG-3 (D-9), UX-1 (D-11), S-5 (SPEC-01 D-1, D-6)
- AC-10 (US-3, state-driven) WHILE the viewed user has no keywords, the system shall show on the detail page a message that the user has no keywords in place of the keyword list. — source: S-1, DG-3 (D-9)
- AC-11 (US-3, unwanted) IF the requested user does not exist, THEN the system shall respond with HTTP 404. — source: S-1, DG-3 (D-9)
- AC-12 (US-3, ubiquitous) The system shall offer no admin action that creates, renames or deletes another user's keyword or changes another user's search settings. — source: S-1 ("the admin can see")
- AC-56 (US-3, event-driven) WHEN an admin follows the "Back to users" link on a detail page, the system shall open the users list with the search term and page the admin came from. — source: DG-3 (D-9)

### Found posts of a user (read-only)

- AC-47 (US-9, event-driven) WHEN an admin opens the found posts of a user from that user's detail page, the system shall list only that user's found posts, ordered by post time, newest first, 10 per page, with `page` kept in the URL. — source: D-8, D-21, S-5 (SPEC-01 AC-61, AC-97)
- AC-48 (US-9, ubiquitous) The system shall show for each listed post its text excerpt (or "(no text)"), `@username`, matched keyword, post time and found time, and an "Open in Threads" link that opens the permalink in a new tab when the post has a permalink. — source: D-8, S-5 (SPEC-01 AC-62, UI-5)
- AC-49 (US-9, ubiquitous) The system shall offer no admin action that changes or deletes a user's found post. — source: D-8
- AC-50 (US-9, state-driven) WHILE the viewed user has no found posts, the system shall show a message that the user has no found posts in place of the list. — source: D-8

### Global keywords

- AC-57 (US-12, event-driven) WHEN an admin opens the "Keywords" tab, the system shall list every distinct keyword text across all users with the number of users who have it and the number of found posts stored with it, ordered by number of users, most first, 25 per page, with `page` kept in the URL. — source: UX-11 (D-11), D-21
- AC-58 (US-12, state-driven) WHILE no user has a keyword, the system shall show on the "Keywords" tab a message that there are no keywords yet. — source: UX-11 (D-11)

### Admin role and the first admin

- AC-32 (US-6, event-driven) WHEN the operator runs the admin-creation console command and enters a name, an email address and a password at its prompts, the system shall create a user account that holds the admin role. — source: D-1
- AC-33 (US-6, unwanted) IF the entered email address already belongs to an account, THEN the admin-creation command shall grant that account the admin role, leave its password and other data unchanged, and create no new account. — source: D-1, D-17
- AC-34 (US-6, unwanted) IF the entered name, email address or password fails the app's registration rules, THEN the admin-creation command shall create nothing and print the validation messages. — source: D-1, S-4 (Fortify registration)
- AC-59 (US-6, event-driven) WHEN the admin-creation command creates an account, the system shall mark its email address as verified at the time of creation. — source: D-1, D-17
- AC-35 (US-7, event-driven) WHEN an admin grants the admin role on a non-admin user's detail page, the system shall give that user the admin role. — source: UX-14 (D-1, D-11)
- AC-36 (US-7, event-driven) WHEN an admin revokes the admin role on another admin's detail page, the system shall remove that user's admin role. — source: UX-14 (D-11)
- AC-37 (US-7, unwanted) IF an admin tries to revoke their own admin role, THEN the system shall refuse the action, change no data and show a message that admins can't revoke their own role. — source: UX-14 (D-11)
- AC-38 (US-7, US-8, unwanted) IF revoking an admin role, blocking a user or deleting a user would leave no active admin, THEN the system shall refuse the action, change no data and show a message that at least one active admin is required. — source: D-14, UX-14 (D-11)

### Blocking and deleting users

- AC-39 (US-8, event-driven) WHEN an admin confirms the block dialog on a user's detail page, the system shall mark that user as blocked and keep all of the user's data. — source: UX-4 (D-11)
- AC-40 (US-8, unwanted) IF a blocked user tries to sign in, THEN the system shall refuse the sign-in and show on the login page the message "This account has been blocked." — source: UX-4 (D-11), D-16
- AC-41 (US-8, event-driven) WHEN a blocked user sends a request with a session that was open before the block, the system shall end that session and redirect to the login page with the message "This account has been blocked." — source: UX-4 (D-11), D-16
- AC-42 (US-8, state-driven) WHILE a user is blocked, the system shall start no scheduled or manual search run, no AI filtering and no notification for that user. — source: UX-4 (D-11); contracts: MC-3, MC-4, MC-6
- AC-43 (US-8, event-driven) WHEN an admin unblocks a user, the system shall remove the block, so that the user can sign in and the user's next scheduled search run starts normally. — source: UX-4 (D-11)
- AC-44 (US-8, event-driven) WHEN an admin confirms the delete dialog on a user's detail page, the system shall delete that account and all its data in the same way as the user's own account deletion. — source: UX-15 (D-11), S-4 (`ProfileController@destroy`), S-5 (SPEC-01 MC-4)
- AC-45 (US-8, state-driven) WHILE the email address typed in the delete dialog does not equal the user's email address, the system shall keep the dialog's "Delete user" button disabled. — source: UX-15 (D-11, "typed-email confirmation")
- AC-46 (US-8, event-driven) WHEN an admin deletes their own account from the admin area, the system shall sign them out and redirect to the login page. — source: S-4 (`ProfileController@destroy` signs the user out), D-14

### Password confirmation

- AC-60 (US-4, US-5, US-7, US-8, state-driven) WHILE an admin has not confirmed their password within the last 3 hours, WHEN they save a new OpenRouter API key or bot token, remove one, grant or revoke the admin role, or delete a user, the system shall ask for their password before performing the action. — source: UX-5, UX-14, UX-15 (D-11), S-4 (`RequirePassword`, `config/auth.php:115` 10,800 seconds)

### OpenRouter connection

- AC-13 (US-4, state-driven) WHILE no OpenRouter API key is stored, the system shall show on the admin AI page the status "Not connected", an empty API key field, the Model field, and the default filtering instruction and default relevance threshold fields. — source: S-1, S-3, D-3, S-5 (SPEC-01 badge "Not connected")
- AC-61 (US-4, event-driven) WHEN an admin saves the admin AI page with a new API key or a changed model id, the system shall check the key with OpenRouter `GET /api/v1/key` and the model id with one test request to that model before storing anything. — source: UX-2 (D-11), S-6
- AC-14 (US-4, event-driven) WHEN both checks of AC-61 succeed, the system shall store the key and the model id as the global OpenRouter connection, clear the API key field and show the status "Connected". — source: S-1, UX-2 (D-11), S-4 (`AiFilteringSettingsForm.vue:46-47`)
- AC-62 (US-4, unwanted) IF OpenRouter rejects the API key as invalid, THEN the system shall store nothing, keep the stored connection unchanged and show under the API key field a message that OpenRouter rejected the key. — source: UX-2 (D-11)
- AC-63 (US-4, unwanted) IF OpenRouter responds with HTTP 402 for the key, THEN the system shall store nothing, keep the stored connection unchanged and show under the API key field a message that the key has no credits left. — source: UX-2 (D-11), S-6
- AC-64 (US-4, unwanted) IF OpenRouter rejects the model id, THEN the system shall store nothing, keep the stored connection unchanged and show under the Model field a message that OpenRouter doesn't know this model. — source: UX-2 (D-11)
- AC-65 (US-4, unwanted) IF a check of AC-61 fails with a network error, a timeout or an HTTP 5xx response, THEN the system shall store nothing, keep the stored connection unchanged and show a message that OpenRouter couldn't be reached. — source: UX-2 (D-11), D-21
- AC-15 (US-4, event-driven) WHEN an admin saves the admin AI page with an empty API key field while a key is stored, the system shall keep the stored key. — source: S-4 (`AiFilteringSettingsForm.vue:99`, placeholder "Key saved — enter a new one to replace it")
- AC-16 (US-4, event-driven) WHEN an admin saves a new API key that passes AC-61 while a key is stored, the system shall replace the stored key with the new one. — source: S-4 (`AiFilteringSettingsForm.vue:99`), UX-2 (D-11)
- AC-17 (US-4, unwanted) IF an admin saves the admin AI page with an empty API key field while no key is stored, THEN the system shall reject the save with a validation message under the API key field. — source: S-1
- AC-18 (US-4, state-driven) WHILE an OpenRouter API key is stored, the system shall show in the API key field the placeholder "Key saved — enter a new one to replace it" and no character of the key. — source: S-4 (`AiFilteringSettingsForm.vue:29-31, 99`), S-2
- AC-19 (US-4, unwanted) IF the stored OpenRouter API key cannot be decrypted, THEN the system shall treat the OpenRouter connection as not connected and show the admin a notice that the saved key can't be read and must be entered again. — source: S-5 (SPEC-01 AC-17)
- AC-66 (US-4, event-driven) WHEN an admin presses "Test connection" on the admin AI page, the system shall run the checks of AC-61 with the entered key, or the stored key when the field is empty, and the entered model id, show the result next to the button, and store nothing. — source: UX-2 (D-11), S-3 ("Test configuration")
- AC-67 (US-4, event-driven) WHEN an admin saves the admin AI page, the system shall store the default filtering instruction and the default relevance threshold (0 to 100 in steps of 5) as the global default criteria. — source: D-3, S-4 (`AiFilteringSettingsForm.vue` range 0–100, step 5)
- AC-68 (US-4, unwanted) IF the default filtering instruction is empty or longer than 2,000 characters, THEN the system shall reject the save with a validation message under the instruction field. — source: D-3, D-20
- AC-92 (US-4, state-driven) WHILE no admin has saved the default criteria yet, the system shall use as the default filtering instruction the text "Approve posts where a person or company is looking for a PHP, Laravel, Vue or full-stack developer. Prioritize contract, freelance and remote work. Reject job-seeker posts, courses, spam and unrelated discussions." and as the default relevance threshold 75. — source: D-20, S-3 (Settings · AI Filtering, l. 1916-1923)
- AC-69 (US-13, state-driven) WHILE OpenRouter is connected, the system shall show on the admin AI page the key's credit limit ("No limit" when OpenRouter reports none), the remaining credit, and the usage today, this week and this month, as reported by `GET /api/v1/key`. — source: UX-8 (D-11), S-6
- AC-70 (US-13, unwanted) IF the usage request fails, THEN the system shall show a notice that usage is unavailable right now and show the rest of the admin AI page. — source: UX-8 (D-11)
- AC-71 (US-4, event-driven) WHEN an admin presses "Disconnect" on the admin AI page, the system shall show a confirmation dialog stating that AI filtering stops for every user. — source: UX-6 (D-11)
- AC-72 (US-4, event-driven) WHEN the admin confirms the AI disconnect dialog, the system shall delete the stored API key, keep the model id and default criteria, and show the status "Not connected". — source: UX-6 (D-11), D-21
- AC-20 (US-4, ubiquitous) The system shall provide one OpenRouter connection for the whole application and no per-user AI provider key. — source: S-1, S-2
- AC-73 (US-4, ubiquitous) The system shall offer OpenRouter as the only AI provider and show no provider select on any page. — source: DG-8 (D-9)
- AC-74 (US-4, ubiquitous) The system shall show no Provider, Model or API key field on the user's AI Filtering settings tab. — source: DG-6 (D-9), AC-20
- AC-75 (US-4, state-driven) WHILE OpenRouter is not connected, the system shall show on the user's AI Filtering settings tab the state "AI filtering isn't available yet". — source: DG-6 (D-9), MI-1 (D-10)

### Telegram bot connection

- AC-21 (US-5, state-driven) WHILE no Telegram bot token is stored, the system shall show on the admin Telegram page the status "Not connected" and an empty bot token field. — source: S-1, S-3, S-5 (SPEC-01 badge "Not connected")
- AC-76 (US-5, event-driven) WHEN an admin saves the admin Telegram page with a new bot token, the system shall check it with Telegram `getMe` before storing anything. — source: UX-2 (D-11), S-8
- AC-22 (US-5, event-driven) WHEN the `getMe` check succeeds, the system shall store the token and the bot's username as the global Telegram bot connection, clear the bot token field and show the status "Connected" with the bot's `@username`. — source: S-1, UX-2 (D-11), S-4 (`TelegramSettingsForm.vue:45-46`)
- AC-77 (US-5, unwanted) IF Telegram answers the `getMe` check with `ok` false, THEN the system shall store nothing, keep the stored connection unchanged and show under the bot token field a message that Telegram rejected the token. — source: UX-2 (D-11), S-8
- AC-78 (US-5, unwanted) IF the `getMe` check fails with a network error, a timeout or an HTTP 5xx response, THEN the system shall store nothing, keep the stored connection unchanged and show a message that Telegram couldn't be reached. — source: UX-2 (D-11), D-21
- AC-23 (US-5, event-driven) WHEN an admin saves the admin Telegram page with an empty bot token field while a token is stored, the system shall keep the stored token. — source: S-4 (`TelegramSettingsForm.vue:70`, placeholder "Token saved — enter a new one to replace it")
- AC-24 (US-5, event-driven) WHEN an admin saves a new bot token that passes AC-76 while a token is stored, the system shall replace the stored token and bot username with the new ones. — source: S-4 (`TelegramSettingsForm.vue:70`), UX-2 (D-11)
- AC-25 (US-5, unwanted) IF an admin saves the admin Telegram page with an empty bot token field while no token is stored, THEN the system shall reject the save with a validation message under the bot token field. — source: S-1
- AC-26 (US-5, state-driven) WHILE a Telegram bot token is stored, the system shall show in the bot token field the placeholder "Token saved — enter a new one to replace it" and no character of the token. — source: S-4 (`TelegramSettingsForm.vue:20, 70`), S-2
- AC-27 (US-5, unwanted) IF the stored Telegram bot token cannot be decrypted, THEN the system shall treat the bot connection as not connected and show the admin a notice that the saved token can't be read and must be entered again. — source: S-5 (SPEC-01 AC-17)
- AC-79 (US-5, event-driven) WHEN an admin presses "Test connection" on the admin Telegram page, the system shall call `getMe` with the entered token, or the stored token when the field is empty, show the bot's `@username` or the failure message of AC-77 or AC-78 next to the button, and store nothing. — source: UX-2 (D-11), S-8
- AC-80 (US-5, event-driven) WHEN an admin presses "Disconnect" on the admin Telegram page, the system shall show a confirmation dialog stating that Telegram notifications stop for every user. — source: UX-6 (D-11)
- AC-81 (US-5, event-driven) WHEN the admin confirms the Telegram disconnect dialog, the system shall delete the stored token and bot username and show the status "Not connected". — source: UX-6 (D-11)
- AC-28 (US-5, ubiquitous) The system shall provide one Telegram bot connection for the whole application and no per-user bot token. — source: S-1, S-2

### Saving feedback

- AC-29 (US-4, US-5, state-driven) WHILE a save or a test on the admin AI page or the admin Telegram page is in progress, the system shall disable that page's save and test buttons and show a spinner on the pressed button. — source: S-4 (`saveState`, `testStatus` in `AiFilteringSettingsForm.vue`; `TelegramSettingsForm.vue:93`)
- AC-30 (US-4, US-5, event-driven) WHEN a save on the admin AI page or the admin Telegram page succeeds, the system shall show the success toast "AI filtering settings saved" or "Telegram settings saved" respectively. — source: S-4 (`handleSave` in both forms)

### Kill switches

- AC-82 (US-11, ubiquitous) The system shall show on the "System" tab three switches, "Pause search", "Pause AI filtering" and "Pause notifications", each with its current state as text. — source: UX-16 (D-11)
- AC-83 (US-11, state-driven) WHILE "Pause search" is on, the system shall start no scheduled or manual Threads search run for any user. — source: UX-16 (D-11), D-14; contract: MC-6
- AC-84 (US-11, state-driven) WHILE "Pause AI filtering" is on, the system shall send no request to OpenRouter for filtering posts. — source: UX-16 (D-11); contract: MC-3
- AC-93 (US-11, state-driven) WHILE "Pause AI filtering" is on, the system shall treat AI filtering as not connected for notifications, so that every new post notifies. — source: D-19, D-10 (MI-4); contract: MC-4
- AC-85 (US-11, state-driven) WHILE "Pause notifications" is on, the system shall send no Telegram notification to any user. — source: UX-16 (D-11); contract: MC-4
- AC-94 (US-11, event-driven) WHEN "Pause notifications" is turned off, the system shall send none of the notifications that fell due while it was on. — source: D-19; contract: MC-4
- AC-86 (US-11, state-driven) WHILE any kill switch is on, the system shall show on every admin page a banner naming the paused functions. — source: UX-16 (D-11)
- AC-88 (US-11, state-driven) WHILE "Pause search" is on, the system shall show on the Dashboard and the Posts page a status line stating that search is paused by the administrator, and the "Run scout" button disabled with that reason. — source: UX-16 (D-11); extends SPEC-01 AC-72, AC-88 (`Supersedes:`)

### Audit log

- AC-87 (US-10, event-driven) WHEN an admin saves, replaces or removes the OpenRouter key or the bot token, changes the model id or the default criteria, grants or revokes the admin role, blocks, unblocks or deletes a user, or turns a kill switch on or off, the system shall record an audit entry with the acting admin's user id, the time, the action and the target user's id when the action has a target user. — source: UX-3, UX-14, UX-15, UX-16 (D-11), D-14, D-18
- AC-89 (US-10, event-driven) WHEN an admin opens the "Audit" tab, the system shall list the audit entries, newest first, 25 per page, with `page` kept in the URL. — source: UX-3 (D-11), D-21
- AC-90 (US-10, ubiquitous) The system shall offer no action that edits or deletes an audit entry. — source: UX-3 (D-11, read-only "Audit" tab)
- AC-95 (US-10, ubiquitous) The system shall show for each audit entry the current name and email address of the acting admin and of the target user, looked up by their ids. — source: D-18
- AC-91 (US-10, unwanted) IF the admin or the target user of an audit entry has been deleted, THEN the system shall show "Deleted user #{id}" for that person. — source: UX-3 (D-11), D-14, D-18
- AC-96 (US-10, ubiquitous) The system shall keep audit entries with no time limit. — source: D-18

## Edge cases

- EC-1 An admin's role is removed while an admin page is open; the next request comes from that page → covered by AC-1, AC-2, NFR-3
- EC-2 A non-admin sends a crafted admin request or an Inertia partial reload of an admin page → covered by AC-2, NFR-3
- EC-3 Fresh installation with no admin; or the operator enters the email of an existing account → covered by AC-32, AC-33
- EC-4 An admin saves with an empty secret field while a secret is stored → covered by AC-15, AC-23
- EC-5 A pasted key or token has surrounding spaces or a trailing newline → covered by UI-1, UI-3
- EC-6 Two admins save different keys at nearly the same time → covered by AC-16, AC-24 (the later save replaces the earlier one)
- EC-7 Double click on a save or test button → covered by AC-29
- EC-8 `APP_KEY` is rotated and stored secrets can't be decrypted → covered by AC-19, AC-27
- EC-9 A valid OpenRouter key with no credits → covered by AC-63 at save; at filtering time: MC-3, MC-4 (D-10, MI-4: every new post notifies while AI fails)
- EC-10 The bot token is revoked or regenerated in BotFather after saving → covered by AC-79 (test shows the failure); at sending time: MC-4 (Telegram spec)
- EC-11 A user is deleted while an admin views the list or that user's pages → covered by AC-11
- EC-12 A user with no keywords, no posts, no Threads connection or no search run yet → covered by AC-7, AC-9, AC-10, AC-50 (SPEC-01 defaults: 15 minutes, 1 day)
- EC-13 Very long names, emails or keywords; emoji, RTL or mixed scripts; markup in names, keywords or posts → covered by UI-4; layout: NFR-6, DG-11
- EC-14 Thousands of users → covered by AC-6, AC-51, AC-52
- EC-15 An admin opens their own detail page → covered by AC-9, AC-37 (no self-revoke)
- EC-16 An admin's email is not verified → covered by AC-4; command-created admins: AC-59; an existing unverified account promoted by the command still has to verify: AC-33, AC-4
- EC-17 The Telegram API URL contains the bot token (`/bot{token}/METHOD`, S-7), so an HTTP exception that includes the URL would leak it → covered by NFR-2
- EC-18 SPEC-01 is not implemented yet, so no keywords, posts or runs exist → covered by MC-1 (dependency)
- EC-19 An admin tries to revoke their own role → covered by AC-37
- EC-20 Revoking, blocking or deleting would leave no active admin, including two admins acting on each other at the same time (the check must hold under concurrent requests) → covered by AC-38
- EC-21 An admin deletes their own account from the admin area while another active admin exists → covered by AC-46, AC-38
- EC-22 An admin blocks themselves while another active admin exists → covered by AC-39, AC-38, AC-41 (their session ends at the next request)
- EC-23 Deleting a user: the data is removed and the action is audited without secret values → covered by AC-44, AC-87, NFR-2; identity shown later: AC-91
- EC-24 A user is blocked, or "Pause search" is turned on, while a search run is in progress → covered by AC-42, AC-83 (no new run starts; the run in progress finishes)
- EC-25 The OpenRouter usage request fails → covered by AC-70
- EC-26 "Pause AI filtering" is on: new posts are notified as if AI were not connected → covered by AC-93
- EC-27 Notifications that become due while "Pause notifications" is on → covered by AC-85, AC-94 (dropped)
- EC-30 A blocked user is unblocked while signed out → covered by AC-43 (they can sign in again)
- EC-31 An audit entry's target was later deleted, or its acting admin deleted their own account → covered by AC-91
- EC-32 The admin clears the default instruction field and saves → covered by AC-68
- EC-28 The delete-dialog email is typed with different letter case or surrounding spaces → covered by AC-45, UI-8
- EC-29 OpenRouter or Telegram error bodies contain text controlled by others → covered by UI-9

## Non-functional requirements

- NFR-1 (security) The system shall store the OpenRouter API key and the Telegram bot token only encrypted at rest. — source: S-3 ("Stored encrypted on the backend."), S-2, S-5 (SPEC-01 NFR-1)
- NFR-2 (security) The system shall not include the OpenRouter API key or the Telegram bot token, in full or in part, in Inertia props, HTTP responses, validation messages, audit entries, logs, exception reports or queued job payloads. — source: S-2, S-4 (`AiFilteringSettingsForm.vue:29-31`), S-5 (SPEC-01 NFR-2), S-7, UX-3 (D-11), D-14
- NFR-3 (security) The system shall check the admin role on the server for every admin page request and every admin action request. — source: S-1
- NFR-4 (privacy) The system shall show admins no user's password hash, two-factor secret, recovery codes, passkey data or Threads access token. — source: S-4 (`User.php` `#[Hidden]`), S-5 (SPEC-01 NFR-2)
- NFR-5 (accessibility) The system shall show the connection status of each integration, each kill switch state and each user badge on the admin pages as text, not as color alone. — source: S-5 (SPEC-01 NFR-7)
- NFR-6 (accessibility) The system shall render every admin page in the light and the dark theme, and at a viewport width of 375 px without horizontal scrolling of the page. — source: DG-10 (D-9)
- NFR-7 (security) The system shall read the password in the admin-creation command from a hidden prompt and never from a command-line argument. — source: D-1, S-2

## Module contracts

- MC-1 threads (SPEC-01) → admin: read-only, for any user: keyword texts, search settings (`search_interval_minutes`, `first_search_lookback_days`), found posts (SPEC-01 post shape), Threads connection status and disconnect reason, the last search run's finish time and outcome; and, across users, keyword texts with user and post counts. Requires SPEC-01's data to exist. Trigger: an admin opens the users list, a detail page, a user's posts or the "Keywords" tab. On failure: AC-11; missing data: EC-18. — source: S-1, S-5, D-8, UX-1, UX-11
- MC-2 account → admin: `name`, `email`, `created_at`, `email_verified_at`, `two_factor_confirmed_at` from `users`; hidden fields stay hidden (NFR-4). Deleting a user (AC-44) reuses the account deletion and its foreign-key cascades (SPEC-01 MC-4). Trigger: list and detail views; delete. On failure: AC-11; a failed deletion deletes nothing. — source: S-4, S-5, UX-15
- MC-3 admin → ai-filtering (future spec): the global OpenRouter connection (connected yes or no, model id, and the decrypted API key, server-side only, NFR-2), the default filtering instruction and relevance threshold (D-3), the "Pause AI filtering" switch and each user's blocked state. The AI-filtering spec consumes SPEC-01's `ThreadsPostsStored` event and marks already stored posts (no SPEC-01 change); it owns the user's on/off switch and criteria override (MI-1, DG-6), per-user usage and the test console (UX-9). Trigger: each time posts are filtered. On failure: not connected, undecryptable (AC-19), paused (AC-84) or user blocked (AC-42) → no OpenRouter request; notification effect per MC-4. — source: S-1, D-3, D-4, D-10, UX-16
- MC-4 admin → notifications / Telegram (future spec): the global bot connection (connected yes or no, the decrypted token, server-side only, NFR-2, and the bot `@username` for the deep link), the "Pause notifications" switch and each user's blocked state. The Telegram spec owns chat linking through `https://t.me/{bot_username}?start={code}` (S-7), webhook registration (S-8), sending, the user's Integrations tab (DG-7) and the delivery log (UX-12). Rule (MI-4, D-19): while AI filtering is not connected, failing or paused ("Pause AI filtering", AC-93), every new post notifies; while it works, only posts judged relevant notify. Trigger: each send or link. On failure: not connected, undecryptable (AC-27), paused (AC-85) or user blocked (AC-42) → no message; notifications due during "Pause notifications" are dropped, never sent later (AC-94). — source: S-1, D-4, D-10, D-19, UX-16
- MC-5 admin → app layout (every signed-in page): whether the current user holds the admin role, in the shared Inertia props, so that the "Admin" item is shown only to admins (AC-5, AC-31). Trigger: every Inertia response for a signed-in user. On failure: none (database read). — source: S-1, S-4 (`HandleInertiaRequests.php:42`), DG-1
- MC-6 admin → threads (SPEC-01) search scheduling and manual runs — **touches SPEC-01** (named in `Supersedes:`): the "Pause search" switch and each user's blocked state. While "Pause search" is on, or the user is blocked, SPEC-01 queues no scheduled run (extends AC-45) and refuses "Run scout" with the reason of AC-88 (extends AC-72); the Dashboard and Posts page show the paused status (extends AC-88). Runs already in progress finish. Trigger: every scheduler tick and every "Run scout" press. On failure: none (database read). — source: UX-4, UX-16 (D-11), D-14
- MC-7 admin → auth (Fortify sign-in): each user's blocked state; a blocked user's sign-in is refused (AC-40) and sessions are handled per AC-41. Trigger: every sign-in, and every request for AC-41. On failure: none (database read). — source: UX-4 (D-11)

## Inputs and provenance

Data inputs:

| Input                                                         | Origin                                | Trust                      |
| ------------------------------------------------------------- | ------------------------------------- | -------------------------- |
| OpenRouter API key                                            | admin, admin AI page                  | untrusted; secret          |
| Model id                                                      | admin, admin AI page                  | untrusted                  |
| Default filtering instruction, default relevance threshold    | admin, admin AI page                  | untrusted                  |
| Telegram bot token                                            | admin, admin Telegram page            | untrusted; secret          |
| Name, email, password at the admin-creation command           | operator, console                     | untrusted; password secret |
| User id in admin URLs; `search` and `page` query              | admin, browser                        | untrusted                  |
| Email typed in the delete dialog                              | admin                                 | untrusted                  |
| Users' names and emails                                       | users, registration and profile forms | untrusted                  |
| Keyword texts, found posts (text, username, permalink)        | users and Threads (SPEC-01)           | untrusted                  |
| `GET /api/v1/key` response, model test response, error bodies | OpenRouter API                        | untrusted                  |
| `getMe` response (bot username), error `description`          | Telegram Bot API                      | untrusted                  |

Requirement sources:

| ID   | Source                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ---- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| S-1  | Request (translated from Ukrainian), relayed 2026-10-23: "Write a specification for implementing an admin panel: the admin can see all users, keywords, etc. — propose what else. There I also connect AI via OpenRouter to filter fetched posts, and there I also connect a Telegram bot through which users will receive notifications. Frame the spec as: we created the design via Claude Design and then handed it off for implementation through our workflow."                                                                                                                                                                                                                                                                                       |
| S-2  | Coordinator's task brief, 2026-10-23: admin owns global credentials; secrets encrypted at rest, masked in the UI, never sent to the frontend in full                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| S-3  | Claude Design canvas `design/Opportunity_Scout_Design_Canvas_UPDATED_v5.html`: "Settings · AI Filtering" (l. 1853: Provider select, Model `openai/gpt-5-mini`, API key "Stored encrypted on the backend.", relevance threshold, filtering instruction, "Test configuration") and "Settings · Integrations / Telegram" (l. 1940: Bot token, Chat ID, Minimum relevance, Delivery). No admin artboard.                                                                                                                                                                                                                                                                                                                                                        |
| S-4  | Existing behaviour: `app/Models/User.php` (no role; `#[Hidden]`), `routes/web.php:17-35`, `routes/account.php:20-22` (`RequirePassword`), `config/auth.php:115` (`password_timeout` 10,800 s), `app/Http/Controllers/Settings/ProfileController.php` (`destroy`: sign out, delete, invalidate session), `app/Http/Middleware/HandleInertiaRequests.php:42`, `resources/js/components/AppSidebar.vue`, `resources/js/components/TableSkeleton.vue`, `resources/js/components/settings/AiFilteringSettingsForm.vue:29-32, 46-47, 99, 105` (range 0–100 step 5, `saveState`, `testStatus`), `resources/js/components/settings/TelegramSettingsForm.vue:20-21, 45-46, 70, 93`, `resources/js/data/settings.ts`, Fortify registration (`config/fortify.php:164`) |
| S-5  | `.ai/docs/specs/threads/SPEC-01-threads-connection-and-keyword-search.md` (draft): D-1, D-6, AC-17, AC-45, AC-60 to AC-64, AC-71, AC-72, AC-88, AC-97, R-1 to R-4, MC-4, MC-9, NFR-1 to NFR-3, NFR-6, NFR-7, UI-5                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| S-6  | OpenRouter, API Rate Limits: https://openrouter.ai/docs/api-reference/limits (fetched 2026-10-23): `GET https://openrouter.ai/api/v1/key` returns `limit`, `limit_remaining`, `usage`, `usage_daily`, `usage_weekly`, `usage_monthly`, `is_free_tier` (`limit` null when unlimited); HTTP 402 for insufficient credits or a spent key limit; HTTP 429 with `error_type: rate_limit_exceeded`                                                                                                                                                                                                                                                                                                                                                                |
| S-7  | Telegram, Bot Features: https://core.telegram.org/bots/features (fetched 2026-10-23): token from @BotFather, anyone with it controls the bot; API URL `https://api.telegram.org/bot{token}/METHOD`; deep link `https://t.me/{bot_username}?start={parameter}`, up to 64 characters of `A-Z a-z 0-9 _ -`; token format not formally specified                                                                                                                                                                                                                                                                                                                                                                                                                |
| S-8  | Telegram Bot API: https://core.telegram.org/bots/api (fetched 2026-10-23): `getMe` tests the token; failures return `ok` false with `description` and `error_code` (contents subject to change); `setWebhook` `secret_token` header `X-Telegram-Bot-Api-Secret-Token`; `sendMessage` text 1–4096 characters                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| S-9  | `.claude/agents/README.md` "Workflow"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| S-10 | The user's answers to Q-1 to Q-8, DG-1 to DG-10, MI-1 to MI-5 and UX-1 to UX-18, relayed by the coordinator on 2026-10-23, recorded as D-1 to D-15                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| S-11 | The user's answers to Q-9 to Q-13 and the acceptance of the spec-creator's derived defaults, relayed by the coordinator on 2026-10-23, recorded as D-16 to D-21                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |

## Untrusted inputs

- UI-1 OpenRouter API key — risk: oversized payload, pasted whitespace or newlines, control characters, the secret echoed back — IF the submitted key, after trimming surrounding whitespace, contains whitespace or control characters or is longer than 255 characters, THEN the system shall reject it with a validation message that does not contain the submitted value. — source: D-6
- UI-2 Model id — risk: oversized input, injection into the OpenRouter request body — IF the model id is empty, longer than 255 characters, or contains characters other than letters, digits, `/`, `-`, `_`, `.` and `:`, THEN the system shall reject it with a validation message under the Model field. — source: D-6, S-3 (`openai/gpt-5-mini`)
- UI-3 Telegram bot token — risk: oversized payload, pasted whitespace, control characters, the secret echoed back; no documented format (S-7) — IF the submitted token, after trimming surrounding whitespace, contains whitespace or control characters or is longer than 255 characters, THEN the system shall reject it with a validation message that does not contain the submitted value. — source: D-6
- UI-4 Users' names, emails, keyword texts, post texts and usernames shown on admin pages — risk: stored XSS against the admin, misleading markup — IF such a value contains HTML or script markup, THEN the system shall display it as literal text.
- UI-5 User id in admin URLs — risk: enumeration errors, injection — IF the value is not the id of an existing user, THEN the system shall respond with HTTP 404 (AC-11).
- UI-6 Users-list `search` term — risk: oversized input, SQL `LIKE` wildcard abuse — IF the term is longer than 100 characters, THEN the system shall reject the request with HTTP 422. — source: S-5 (SPEC-01 RT-13, same limit), D-21
- UI-7 Users-list `search` term with wildcards — risk: `LIKE` wildcard abuse — IF the term contains `%` or `_`, THEN the system shall match those characters literally. — source: S-5 (SPEC-01 UI-8)
- UI-8 Email typed in the delete dialog — risk: deleting the wrong account — IF the typed value, after trimming surrounding whitespace, differs from the user's email address in any character other than letter case, THEN the system shall refuse the deletion. — source: UX-15 (D-11)
- UI-9 OpenRouter and Telegram responses — risk: third-party text injected into the admin UI, oversized error bodies — IF a check of AC-61, AC-76 or AC-79 fails, THEN the system shall show only its own fixed message and never the provider's error text.
- UI-10 Default filtering instruction — risk: oversized prompt, markup shown back to users — IF the instruction contains HTML or script markup, THEN the system shall store it unchanged and display it as literal text. (length: AC-68, at most 2,000 characters)
- UI-11 `page` query on admin lists — risk: non-numeric or negative values — IF `page` is not an integer of at least 1, THEN the system shall show the first page.

## Design review

Design gaps:

- DG-1 Admin entry and layout — sidebar item "Admin" below "Settings" for admins only; admin pages under `/admin`; route names `admin.*` — [accepted → AC-31, MC-5]
- DG-2 Admin · Users list — search, pagination, URL state, skeleton, "No users match", truncation, narrow layout — [accepted → AC-6, AC-7, AC-51 to AC-55]
- DG-3 Admin · User detail — header, read-only search settings and keyword chips, no-keywords state, "Back to users" keeping list state, 404 — [accepted → AC-9, AC-10, AC-11, AC-56]
- DG-4 Admin · AI (OpenRouter) — the "AI filtering model" card without the Provider select, with all states — [accepted → AC-13 to AC-19, AC-61 to AC-72]
- DG-5 Admin · Telegram bot — token card with all states and the bot `@username` — [accepted → AC-21 to AC-27, AC-76 to AC-81]
- DG-6 Settings · AI Filtering (user) — remove Provider, Model and API key; keep on/off and the user's own criteria override (AI-filtering spec); "AI filtering isn't available yet" state — [accepted → AC-74, AC-75, MC-3]
- DG-7 Settings · Integrations / Telegram (user) — "Connect Telegram" deep-link button, linked state with "Disconnect", "not available yet" state; notification rules stay with the user; implemented in the Telegram spec — [accepted → MC-4]
- DG-8 Provider select — OpenRouter only — [accepted → AC-73]
- DG-9 Access denied — the app's standard not-found page — [accepted → AC-2]
- DG-10 All admin artboards — light and dark variants and a 375 px variant — [accepted → NFR-6, DG-11]
- DG-11 Claude Design handoff not yet delivered — the following artboards, each in light and dark theme and at 375 px, must be exported into `design/` and checked against this spec before approval: (1) admin layout with the "Admin" sidebar item, the six tabs and the kill-switch banner (AC-31, AC-86); (2) Users list: default, searching (skeleton), "No users match", badges and UX-1 columns, long values; (3) User detail: fields, search settings, keyword chips, no keywords, actions "Grant admin" / "Revoke admin", "Block" / "Unblock", "Delete user"; (4) a user's found posts list and its empty state; (5) dialogs: block, unblock, delete with typed email, grant and revoke admin, the "at least one active admin" and "can't revoke your own role" messages, and the password confirmation step; (6) Admin · AI: not connected, connected with usage (AC-69) and "usage unavailable", saving, testing, each check failure (AC-62 to AC-65), unreadable key, default criteria fields, disconnect dialog; (7) Admin · Telegram: not connected, connected with `@username`, saving, testing, failures (AC-77, AC-78), unreadable token, disconnect dialog; (8) "Keywords" tab and its empty state; (9) "System" tab with the three switches; (10) "Audit" tab, including the "Deleted user #{id}" display (AC-91); (11) user-facing: revised Settings · AI Filtering (DG-6, AC-75), revised Settings · Integrations (DG-7), "search paused by the administrator" on the Dashboard and Posts page (AC-88), and the blocked-account message on the login page (AC-40) — [pending]

UX proposals:

- UX-1 (P1) Health columns in the users list and detail — [accepted → AC-7, AC-9]
- UX-2 (P1) Check on save and "Test connection" for OpenRouter and Telegram — [accepted → AC-61 to AC-66, AC-76 to AC-79]
- UX-3 (P1) Audit log of admin actions — [accepted → AC-87, AC-89, AC-90, AC-91, NFR-2]
- UX-4 (P1) Block and unblock a user — [accepted → AC-39 to AC-43, MC-6, MC-7]
- UX-5 (P1) Password re-confirmation before secret changes — [accepted → AC-60]
- UX-6 (P1) Remove a stored key or token with a confirmation dialog — [accepted → AC-71, AC-72, AC-80, AC-81]
- UX-7 (P2) System overview page — [rejected: deferred to a later spec]
- UX-8 (P2) AI usage and cost — [accepted → AC-69, AC-70 (global key limit and usage only; per-user usage and budgets go to the AI-filtering spec)]
- UX-9 (P2) AI filter test console — [rejected: deferred to the AI-filtering spec]
- UX-10 (P2) Failed jobs view — [rejected: deferred to a later spec]
- UX-11 (P2) Global keywords view — [accepted → AC-57, AC-58]
- UX-12 (P2) Telegram delivery log — [rejected: deferred to the Telegram notifications spec]
- UX-13 (P3) Reset a user's two-factor authentication — [rejected: deferred to a later spec]
- UX-14 (P3) Grant and revoke the admin role in the panel — [accepted → AC-35 to AC-38, AC-60, AC-87]
- UX-15 (P3) Delete a user from the panel — [accepted → AC-44 to AC-46, AC-60, AC-87, UI-8]
- UX-16 (P3) Global kill switches — [accepted → AC-82 to AC-86, AC-88, MC-3, MC-4, MC-6]
- UX-17 (P3) Impersonate a user — [rejected: bypasses 2FA and passkeys; privacy risk]
- UX-18 (P3) Admin-editable global limits — [rejected: SPEC-01 derives the caps from the Threads quota]

Module interactions to confirm:

- MI-1 AI filtering split between admin and user — [accepted → AC-20, AC-74, AC-75, MC-3]
- MI-2 Telegram split between admin and user — this spec only stores and checks the bot token; linking, webhook and sending are in the Telegram spec — [accepted → AC-21 to AC-28, AC-76 to AC-81, MC-4]
- MI-3 Position of the AI filter — consumes `ThreadsPostsStored` and marks stored posts; no SPEC-01 change — [accepted → MC-3]
- MI-4 Notification trigger — every new post notifies while AI is not connected or failing; only relevant posts while it works — [accepted → MC-4]
- MI-5 Admin read access versus SPEC-01 NFR-6 — kept as `Supersedes:` in this spec, extended to read-only posts — [accepted → `Supersedes:`, MC-1]

## Open questions

- Q-1 How does a user become an admin? — [resolved → AC-32, AC-35, AC-36] (D-1)
- Q-2 What does a non-admin get for an admin URL? — [resolved → AC-2] (D-2)
- Q-3 Where do the filtering instruction and threshold live? — [resolved → AC-67, AC-68, MC-3] (D-3)
- Q-4 What does this spec deliver for AI and Telegram? — [resolved → Non-goals, MC-3, MC-4] (D-4)
- Q-5 Users list defaults — [resolved → AC-6, AC-51, AC-52] (D-5)
- Q-6 Model choice and input limits — [resolved → UI-1, UI-2, UI-3] (D-6)
- Q-7 Is an admin also a regular user? — [resolved → AC-7] (D-7)
- Q-8 Does the admin see users' found posts? — [resolved → AC-47 to AC-50] (D-8)
- Q-9 What happens to a blocked user's sign-in and open sessions? — [resolved → AC-40, AC-41] (D-16)
- Q-10 Admin-creation command: existing email, and email verification of a new account — [resolved → AC-33, AC-59] (D-17)
- Q-11 How does the audit log identify a deleted person, and how long are entries kept? — [resolved → AC-87, AC-91, AC-95, AC-96] (D-18)
- Q-12 Kill-switch side effects on notifications — [resolved → AC-93, AC-94, MC-4] (D-19)
- Q-13 Default filtering instruction: maximum length and starting value — [resolved → AC-68, AC-92] (D-20)

## Changelog

- 2026-10-23 00:16 — created (draft)
- 2026-10-23 00:47 — revised with the user's answers (D-1 to D-15): resolved Q-1 to Q-8; accepted DG-1 to DG-10, MI-1 to MI-5, UX-1 to UX-6, UX-8 (global key only), UX-11, UX-14, UX-15, UX-16; UX-7, UX-9, UX-10, UX-12, UX-13 deferred; UX-17, UX-18 rejected. Added US-6 to US-13, AC-31 to AC-91, EC-19 to EC-29, NFR-6, NFR-7, MC-6 (touches SPEC-01), MC-7, UI-6 to UI-11, Decisions. Added DG-11 (the Claude Design handoff brief) and new questions Q-9 to Q-13 raised by the answers. `Supersedes:` extended to read-only posts, health data and SPEC-01 AC-45, AC-72, AC-88. Status stays draft.
- 2026-10-23 01:05 — revised with the second round of answers (D-16 to D-21): resolved Q-9 to Q-13 and filled every placeholder (AC-33, AC-40, AC-41, AC-59, AC-68, AC-91); added AC-92 (starting default criteria), AC-93 and AC-94 (pause effects on notifications), AC-95 and AC-96 (audit display and retention), EC-30 to EC-32; AC-87 now records ids only; MC-4 updated; derived defaults recorded as D-21; URL templates written with `{…}`. Only DG-11 (the Claude Design handoff) is pending. Status stays draft.
