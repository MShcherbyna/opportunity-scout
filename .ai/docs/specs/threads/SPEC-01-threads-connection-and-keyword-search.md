# Spec: Threads account connection and keyword search

Spec ID: SPEC-01
Status: approved
Supersedes: —

## Problem and user

Opportunity Scout users (freelancers and agencies looking for work requests) need the app to find new Threads posts that match their keywords. Today, the Threads connection card, keyword settings, "Run scout" button, posts list, post detail and dashboard are frontend mocks: nothing talks to Threads (S-5). Meta counts the keyword-search quota (2,200 queries per rolling 24 hours) per Threads account and allows search only on behalf of a specific user. So each user has to connect their own Threads account, and the app has to keep that access alive, search in the background within the quota and store the posts it finds (S-1, S-2, S-3). The Meta app is configured and the OAuth flow and `keyword_search` were checked by hand on 2026-10-22, so the integration can now be specified against confirmed behaviour (S-2).

## Goals / Non-goals

Goals:

- G-1 Every user can connect, disconnect and reconnect their own Threads account through OAuth. There is no shared service account, and one Threads account belongs to at most one Scout user at a time.
- G-2 A connected account stays usable without any user action: every token is refreshed once a day during the 7 days before it expires, so it never reaches the 60-day limit while the user keeps access.
- G-3 On each run, every keyword of the user is searched once (at most 100 posts per keyword), and a Threads post is stored at most once per user.
- G-4 A user's planned and actual `keyword_search` queries never exceed 2,000 per rolling 24 hours, and the user sees the usage on the Dashboard.
- G-5 Meta's uninstall and data-deletion callbacks are verified, honoured and answered in the format Meta expects.

Non-goals:

- App Review, Business Verification and the screencast (S-1).
- Publishing to Threads, replies, analytics (S-1).
- Classifying or filtering posts by relevance (AI filtering, scores, statuses). Posts are stored unfiltered, with no AI columns (D-22). **Later improvement:** drop loose matches (word forms, hashtags) and irrelevant posts. This belongs to a future AI-filtering spec.
- AI-related Dashboard parts: the "Relevant posts" card, the relevant-posts chart series and the AI filtering alert stay on mock data (D-24).
- Notifications about new posts (Telegram, email). This spec only dispatches the `ThreadsPostsStored` event (D-23).
- The "Suggested response template" field of the Scraping form. Future spec.
- "Mark as relevant" and "Suggested response" for real posts. They are hidden (D-26) and return with the AI-filtering and template specs.
- Paginating `keyword_search` results (D-9).
- Re-checking stored posts that were later edited or deleted on Threads.
- Revoking the app's permission at Meta when the user disconnects in Opportunity Scout (AC-91 tells the user how to do it in Threads).

## User stories

- US-1 As a Scout user, I want to connect my own Threads account through Threads login, so that searches run on my behalf and use my own quota.
- US-2 As a Scout user, I want to disconnect my Threads account and connect it again later, so that I control the app's access.
- US-3 As a Scout user, I want to know when my Threads connection stopped working and be able to reconnect it, so that search resumes.
- US-4 As the app operator, I want Meta's uninstall and data-deletion callbacks handled, so that the app meets Meta platform requirements.
- US-5 As a Scout user, I want my Threads access renewed automatically, so that I don't have to reconnect every 60 days.
- US-6 As a Scout user, I want to add, rename and remove my own keywords, so that search looks for the posts I care about.
- US-7 As a Scout user, I want new posts matching my keywords collected in the background without duplicates, so that I can review them later.
- US-8 As a Scout user, I want my search settings to stay within the Threads search quota, so that search doesn't stop unexpectedly.
- US-9 As a Scout user, I want to see the posts that were found and open each original on Threads, so that I can reply to the author.
- US-10 As a Scout user, I want to see how much of my daily Threads search quota is used and left, so that I know whether search can keep running.
- US-11 As a Scout user, I want to start a search right away, so that I see results soon after changing my keywords.

## Decisions

The user's answers of 2026-10-22 (S-12), relayed by the coordinator. Each decision resolves the question or design item it names.

- D-1 (Q-1) The user picks the search interval: 5, 15, 30 or 60 minutes. The default is 15 minutes (S-5, `data/settings.ts`).
- D-2 (Q-3) The daily query budget is 2,000.
- D-3 (Q-1, Q-2, Q-3) Keyword cap = min(20, floor(2,000 / runs per day)): 6 keywords at 5 minutes, 20 at 15, 30 or 60 minutes. Changing to an interval whose cap is lower than the current keyword count is rejected. A keyword is at most 100 characters.
- D-4 (Q-4) Found posts are kept for 90 days, counted from the **found time** (`threads_posts.created_at`), not the Threads `timestamp`. Reason: each post stays visible for 90 days after it appears in the app, and found time is under our control, indexed and never in the future. A daily scheduled command deletes older posts.
- D-5 (Q-5) A `disconnected` connection is shown on the Threads tab and in a banner on every page of the signed-in app, whatever the disconnect reason.
- D-6 (Q-6) The user chooses how far back a new keyword's first search goes: none, 1 day (default) or 3 days. It is a **per-user setting**, stored next to the interval on `users`. Reason: one control on the Scraping tab instead of one per keyword. The value applies when a keyword is added or renamed.
- D-7 (Q-7) Consecutive search windows overlap by 10 minutes; deduplication removes the repeats.
- D-8 (Q-8) Meta documents no error for exceeding 2,200 queries, and no quota endpoint or header exists for `keyword_search` (`threads_publishing_limit` covers publishing only). Any of these counts as rate-limited: error code 4, 17, 32, 341 or 613, HTTP 429, or an error message containing "limit". It pauses that user's search for 1 hour. The first error of each pause is logged with its full body and no token. The real error must be confirmed in manual testing (V-1). The 2,200 limit is a rolling 24 hours, so capacity returns gradually. The general Threads call limit (4,800 × impressions) is separate and not handled here.
- D-9 (Q-9) No pagination: at most 100 posts per keyword per run.
- D-10 (Q-10) A Threads account can be connected to only one Scout user at a time. A connection is rejected while another user has that account `connected`.
- D-11 (Q-11) Reconnecting with a different Threads account keeps the keywords and posts.
- D-12 (Q-12) The delete callback removes the connection, found posts and search runs. Keywords and the Scout account stay.
- D-13 (Q-13) Deleting a keyword keeps its posts. The keyword text stays on each post, and the foreign key is set to null.
- D-14 (Q-14) Found posts appear on the existing `/posts` page, replacing the mock data, with the AI parts hidden.
- D-15 (Q-15) No Threads doc describes these callbacks. Meta's generic doc calls the `signed_request` `user_id` an "app-scoped ID"; it is probably the same value as the code exchange `user_id` and the `/me` id, but this is unverified (V-2). Callbacks look the connection up by `threads_user_id`. If none matches, they still return the response Meta expects and log the unmatched id with no token.
- D-16 Dashboard quota widget (new requirement, from Q-8): queries used in the last 24 hours, queries remaining and the budget, all computed from `threads_search_runs`. The widget explains that the 2,200 limit is shared with other apps, so the remaining number is an estimate.
- D-17 DG-1 to DG-11 and DG-13 to DG-15 are accepted as proposed, with three changes: DG-3 stores `name` as a nullable column; with DG-9, keywords can still be added before connecting and search starts after connecting; DG-11 also adds a `keyword` filter to the query string.
- D-18 DG-12: the three empty-state variants plus a "no results for this filter" state. All UI copy and status texts are in English.
- D-19 DG-16 / UX-3: "Run scout" starts a real run. At most one per 15 minutes; its queries count toward the budget; it is blocked when the remaining budget is too small.
- D-20 UX-1, UX-2, UX-4, UX-5, UX-6, UX-7 and UX-8 are accepted.
- D-21 (MI-1) `posts.show` shows real posts (text, author, keyword, Threads permalink) with the AI blocks hidden.
- D-22 (MI-2) Posts are stored unfiltered; there are no AI columns now.
- D-23 (MI-3) No notifications. The search job dispatches a `ThreadsPostsStored` event after it stores new posts.
- D-24 (MI-4) The Dashboard gets the quota widget and real "latest posts" and found counts; the AI stats stay mock.
- D-25 (MI-5) This spec owns the search interval and the first-search lookback settings.
- D-26 (Q-16, option A) For real posts, "Mark as relevant" is hidden in the posts rows, the Dashboard latest posts and the post detail, and "Suggested response" is hidden on the post detail. Both return with the AI-filtering and template specs. No columns, no routes.
- D-27 (Q-17, option B) "Posts found today" and the Dashboard's daily chart use the browser's IANA time zone; storage stays UTC. Transport: a **cookie** named `timezone`, written by the frontend on every signed-in page load. A cookie is the only option that also reaches the first full page load, which can't carry a custom header, and it keeps time zones out of URLs. It is excluded from cookie encryption, like `appearance` (`bootstrap/app.php`). The server checks it against the IANA identifier list and falls back to UTC when it is missing or invalid.
- D-28 (UX-9) While a run is queued or running, the Dashboard and the Posts page poll every 10 seconds. Each poll is an Inertia partial reload of only the search status and the posts. Polling stops when no run is active. When the run ends, the toast "Search finished: {n} new posts." appears.

## Acceptance criteria (EARS)

Exact English copy for toasts, banners, notices and empty states is in "UI states"; criteria refer to it.

### Connecting Threads (OAuth)

- AC-1 (US-1, event-driven) WHEN a signed-in user without a `connected` Threads connection presses "Connect Threads" on the Threads settings tab, the system shall redirect the browser to the Threads authorization URL with the app's client id, the redirect URI `{APP_URL}/auth/threads/callback`, `response_type=code`, `scope=threads_basic,threads_keyword_search` and a newly generated `state` value. — source: S-1, S-7
- AC-102 (US-1, state-driven) WHILE the browser is being redirected to Threads after "Connect Threads" was pressed, the system shall show the button disabled with a spinner. — source: DG-2 (D-17)
- AC-2 (US-1, ubiquitous) The system shall generate each OAuth `state` value with at least 128 bits of randomness, bind it to the current user's session, and accept it for one callback only. — source: S-1, S-11
- AC-3 (US-1, unwanted) IF the callback arrives with a `state` that is missing, does not match the value bound to the current session, or was already used, THEN the system shall make no Threads API call, leave the connection unchanged, and redirect to the Threads settings tab with the toast T-3. — source: S-1, S-11
- AC-4 (US-1, unwanted) IF the callback arrives with an `error` parameter (for example `error=access_denied` when the user declines), THEN the system shall leave the connection unchanged and redirect to the Threads settings tab with the toast T-2. — source: S-1, S-7
- AC-5 (US-1, event-driven) WHEN the callback arrives with a valid `state` and a `code`, the system shall exchange the code for a short-lived token, exchange that token for a long-lived token, read the account `id`, `username` and `name` from `/me`, and save the connection with status `connected`. — source: S-1, S-2, S-7, S-8, DG-3 (D-17)
- AC-67 (US-1, unwanted) IF the Threads account returned by `/me` is `connected` to another Scout user, THEN the system shall discard the new token, leave both users' connections unchanged, and redirect to the Threads settings tab with the toast T-5. — source: D-10
- AC-6 (US-1, unwanted) IF the code exchange fails (the code is invalid, older than 1 hour, or already used, or the request fails), THEN the system shall leave the connection unchanged and redirect to the Threads settings tab with the toast T-4. — source: S-1, S-7
- AC-7 (US-1, unwanted) IF the long-lived token exchange or the `/me` call fails after a successful code exchange, THEN the system shall discard the short-lived token, leave the connection unchanged, and redirect to the Threads settings tab with the toast T-4. — source: S-1
- AC-8 (US-1, ubiquitous) The system shall never write the short-lived token to the database, the session, the cache, queued job payloads or logs. — source: S-1
- AC-9 (US-1, event-driven) WHEN a long-lived token is saved, the system shall set the token expiry to the exchange time plus the `expires_in` seconds returned by Threads, and the token issue time to the exchange time. — source: S-1, S-8
- AC-10 (US-1, event-driven) WHEN the connection is saved, the system shall redirect to the Threads settings tab with the toast T-1 and show the connected `@username`, the name, the connection date and the access expiry date. — source: S-5 (`ThreadsConnectionCard.vue`), DG-3 (D-17)

### Connection state, disconnecting and reconnecting

- AC-94 (US-2, event-driven) WHEN a user with a `connected` connection presses "Disconnect" on the Threads settings tab, the system shall show the confirmation dialog DLG-1 before disconnecting. — source: DG-2 (D-17)
- AC-11 (US-2, event-driven) WHEN the user confirms the disconnect dialog, the system shall delete the stored access token, set the connection to `disconnected` with reason `user`, and redirect to the Threads settings tab with the toast T-6. — source: S-1
- AC-91 (US-2, event-driven) WHEN a disconnect by the user succeeds, the system shall show on the Threads settings tab the notice N-5 on how to also remove the app in Threads. — source: UX-4 (D-20)
- AC-12 (US-2, state-driven) WHILE a user's connection is `disconnected` or absent, the system shall send no Threads API request on that user's behalf. — source: S-1, S-3 §5
- AC-13 (US-2, event-driven) WHEN a user whose connection is `disconnected` completes the OAuth flow again, with the same or a different Threads account, the system shall update that same connection with the new token, Threads user id, username, name and expiry, set it to `connected`, clear the disconnect reason, and keep the user's keywords and found posts. — source: S-1, D-11
- AC-14 (US-2, ubiquitous) The system shall keep the user's keywords and found posts when a connection becomes `disconnected` for any reason. — source: S-1 (uninstall: "delete the token and mark the connection disconnected")
- AC-15 (US-3, state-driven) WHILE a user's connection is `disconnected`, the system shall show on the Threads settings tab the state "Threads is disconnected", the reason text R-1 to R-4 for its disconnect reason, and a "Reconnect Threads" button. — source: S-1, S-3 §5, D-5
- AC-68 (US-3, state-driven) WHILE a user's connection is `disconnected`, the system shall show the banner B-1 with the reason text and a "Reconnect Threads" link to the Threads settings tab at the top of every page of the signed-in app. — source: D-5
- AC-93 (US-3, state-driven) WHILE a user's connection is `connected` and its token expires within 3 days, the system shall show on the Threads settings tab the warning N-6 with a "Reconnect Threads" button. — source: UX-6 (D-20)
- AC-16 (US-3, unwanted) IF any Threads API call made with a user's long-lived token returns OAuth error code 190, THEN the system shall delete that token and set the connection to `disconnected` with reason `token_invalid`. — source: S-1
- AC-17 (US-3, unwanted) IF a stored access token cannot be decrypted, THEN the system shall delete that token and set the connection to `disconnected` with reason `token_invalid`. — source: S-1 (`encrypted` cast)

### Meta callbacks

- AC-18 (US-4, event-driven) WHEN Meta sends `POST /auth/threads/uninstall` with a valid `signed_request`, the system shall delete the access token of every connection whose `threads_user_id` equals the payload's `user_id`, and set those connections to `disconnected` with reason `uninstalled`. — source: S-1, S-9, D-15
- AC-19 (US-4, unwanted) IF a valid uninstall callback's `user_id` matches no connection, THEN the system shall change no data, log the unmatched `user_id` without any token, and respond with HTTP 200. — source: S-9, D-15
- AC-20 (US-4, unwanted) IF a callback's `signed_request` is missing or malformed, names an algorithm other than `HMAC-SHA256`, or its HMAC-SHA256 signature over the encoded payload with the Threads app secret does not match, THEN the system shall respond with HTTP 400, change no data, and log a warning that does not contain the request body. — source: S-1, S-9
- AC-21 (US-4, event-driven) WHEN Meta sends `POST /auth/threads/delete` with a valid `signed_request` whose `user_id` matches connections, the system shall delete those connections and their users' found posts and search runs, keep their keywords and Scout accounts, record a deletion request with a new unique alphanumeric confirmation code, and respond with HTTP 200 and the JSON `{"url": "{status URL}", "confirmation_code": "{code}"}`. — source: S-1, S-9, D-12, D-15
- AC-22 (US-4, unwanted) IF a valid delete callback's `user_id` matches no connection, THEN the system shall log the unmatched `user_id` without any token, record a `completed` deletion request, and respond with the same JSON shape as AC-21. — source: S-9, D-15
- AC-23 (US-4, event-driven) WHEN the deletion of the requested data finishes, the system shall set the deletion request's status to `completed` with the completion time. — source: S-9
- AC-24 (US-4, event-driven) WHEN anyone opens the status URL with a known confirmation code, the system shall show the status block N-8 on the public data-deletion page with the code, the request date and its status (`Pending` or `Completed`). — source: S-9, DG-15 (D-17)
- AC-25 (US-4, unwanted) IF the status URL is opened with an unknown or malformed confirmation code, THEN the system shall show on the data-deletion page the message N-9. — source: S-9, DG-15 (D-17)
- AC-99 (US-4, ubiquitous) The system shall show on the data-deletion page the account-deletion step "Open Account → Profile." — source: DG-15 (D-17), S-5 (`DataDeletion.vue:17`)

### Token refresh (`threads:refresh-tokens`)

- AC-26 (US-5, ubiquitous) The system shall run the `threads:refresh-tokens` console command once a day, without overlapping a previous run, on one server only. — source: S-1
- AC-27 (US-5, event-driven) WHEN `threads:refresh-tokens` runs, the system shall select every `connected` connection whose token expires within 7 days, has not yet expired, and was issued at least 24 hours ago. — source: S-1, S-8
- AC-28 (US-5, event-driven) WHEN the command processes a selected connection, the system shall call the refresh endpoint synchronously and, on success, save the new token, set the expiry to now plus the returned `expires_in` seconds, and set the token issue time to now. — source: S-1, S-8
- AC-29 (US-5, unwanted) IF refreshing one connection fails for any reason, THEN the system shall continue with the remaining selected connections. — source: S-1
- AC-30 (US-5, unwanted) IF the refresh endpoint returns OAuth error code 190, THEN the system shall delete the token and set the connection to `disconnected` with reason `token_invalid`. — source: S-1
- AC-31 (US-5, unwanted) IF the refresh request fails with a network error, a timeout or an HTTP 5xx response, THEN the system shall log a warning with the connection id and leave the connection unchanged, so that the next daily run retries it. — source: S-1
- AC-32 (US-5, unwanted) IF the refresh endpoint returns an error other than code 190 and other than HTTP 5xx, THEN the system shall log an error with the connection id and the Threads error code and leave the connection unchanged. — source: S-1 (per-connection isolation)
- AC-33 (US-5, event-driven) WHEN `threads:refresh-tokens` finds a `connected` connection whose token has already expired, the system shall delete the token and set the connection to `disconnected` with reason `expired`. — source: S-8 ("can no longer be refreshed")

### Keywords and search settings

- AC-34 (US-6, event-driven) WHEN a user adds a keyword, the system shall trim it, collapse inner whitespace to single spaces, convert it to lower case, and save it for that user only. — source: S-1, S-5 (`TagInput.vue:22`)
- AC-106 (US-6, event-driven) WHEN the user presses Enter in the keyword input, the system shall save the keyword at once, without a separate "Save settings" step. — source: DG-4 (D-17)
- AC-35 (US-6, unwanted) IF an added or renamed keyword is empty after trimming, contains control characters, or is longer than 100 characters, THEN the system shall reject it with a validation message under the keyword field. — source: S-1, D-3
- AC-36 (US-6, unwanted) IF an added or renamed keyword equals, after normalisation, another keyword the user already has, THEN the system shall reject it with the message "This keyword already exists." — source: S-5 (`TagInput.vue:24`)
- AC-37 (US-6, unwanted) IF the user already has as many keywords as the cap for their search interval (min(20, floor(2,000 / runs per day)): 6 at 5 minutes, 20 otherwise), THEN the system shall reject a new keyword with the message "You can have up to {cap} keywords with a {interval}-minute search interval." — source: D-1, D-2, D-3
- AC-38 — removed: merged into AC-37 by D-3.
- AC-96 (US-8, state-driven) WHILE the user has as many keywords as the cap for their search interval, the system shall disable the new-keyword input and show the notice N-3. — source: DG-5 (D-17)
- AC-39 (US-6, event-driven) WHEN a user renames a keyword, the system shall apply the rules of AC-34 to AC-36 and reset the keyword's search window as AC-49 describes for a new keyword. — source: S-1
- AC-40 (US-6, event-driven) WHEN a user deletes a keyword, the system shall exclude it from every search run that starts after the deletion. — source: S-1
- AC-103 (US-6, event-driven) WHEN a user deletes a keyword, the system shall keep the posts it found, each with the keyword text it was found by. — source: D-13
- AC-41 (US-6, unwanted) IF a user requests to rename or delete a keyword that belongs to another user, THEN the system shall respond with HTTP 404 and change no data. — source: S-1 ("the user manages their own keywords")
- AC-42 (US-8, ubiquitous) The system shall show next to the keyword list the notice N-2 with the user's planned daily queries, the 2,000 budget, the keyword count and the runs per day. — source: S-3 §4, DG-5 (D-17)
- AC-43 (US-6, state-driven) WHILE the user has no `connected` Threads connection, the system shall accept keyword changes. — source: S-5, D-17 (DG-9)
- AC-69 (US-6, state-driven) WHILE the user has no `connected` Threads connection, the system shall show above the keywords the notice N-1 with a link to the Threads settings tab. — source: DG-9 (D-17)
- AC-70 (US-8, event-driven) WHEN a user saves the search settings on the Scraping tab, the system shall store their search interval (5, 15, 30 or 60 minutes; default 15) and their first-search lookback (none, 1 day or 3 days; default 1 day). — source: D-1, D-6, D-25
- AC-44 (US-8, unwanted) IF the user picks a search interval whose keyword cap is lower than their current number of keywords, THEN the system shall reject the change with the message "A {interval}-minute interval allows up to {cap} keywords. Remove keywords first." — source: D-3
- AC-89 (US-6, ubiquitous) The system shall show under the keyword input the hint N-4 that Threads search doesn't translate keywords. — source: UX-1 (D-20), S-2 §6
- AC-95 (US-6, event-driven) WHEN the user hovers over or focuses a keyword, the system shall show the time of its last search and the number of posts that search returned, or "Not searched yet". — source: DG-6 (D-17)
- AC-92 (US-6, state-driven) WHILE every search of a keyword during the last 7 days or more returned zero posts, the system shall mark that keyword with the hint N-7. — source: UX-5 (D-20), S-6
- AC-100 (US-6, ubiquitous) The system shall show no "Posts per run" and no "Regions / languages" field on the Scraping tab. — source: DG-7, DG-8 (D-17)
- AC-90 (US-7, optional) WHERE the own-posts-only flag `services.threads.own_posts_only` is enabled, the system shall show the notice N-10 on the Posts page and the Scraping tab. — source: UX-2 (D-20), S-2 §1

### Search runs

- AC-45 (US-7, state-driven) WHILE a user's connection is `connected`, the user has at least one keyword, and their search is not paused, the system shall queue one scheduled search run for that user every N minutes, where N is the user's search interval. — source: S-1, D-1
- AC-46 (US-7, ubiquitous) The system shall execute search runs only in queued jobs, never inside an HTTP request. — source: S-1, S-2 §7
- AC-47 (US-7, state-driven) WHILE a search run for a user is queued or in progress, the system shall not queue another search run for that user. — source: S-1 (rate limit)
- AC-48 (US-7, event-driven) WHEN a search run executes, the system shall send one `keyword_search` request per keyword with `q` set to the keyword, `search_type=RECENT`, `limit=100`, `fields=id,text,permalink,timestamp,username`, `since` set to that keyword's window start, and `until` set to the run's start time, without requesting further pages. — source: S-1, S-2 §6, S-6, D-9
- AC-49 (US-7, event-driven) WHEN a user adds or renames a keyword, the system shall set that keyword's search window start to the time of the change minus the user's first-search lookback (0, 1 or 3 days). — source: D-6
- AC-50 (US-7, event-driven) WHEN a keyword query succeeds, including with an empty result, the system shall set that keyword's next window start to the query's `until` minus 10 minutes. — source: S-1, D-7
- AC-51 (US-7, unwanted) IF a keyword query fails for any reason, THEN the system shall leave that keyword's window start unchanged, so that the next run covers the missed period. — source: S-1
- AC-52 (US-7, event-driven) WHEN a keyword query returns posts, the system shall store each new post, unfiltered, with its Threads post id, text, permalink, timestamp, username, the matched keyword and the owning user. — source: S-1, D-22
- AC-53 (US-7, unwanted) IF a returned post's Threads post id is already stored for the same user, THEN the system shall skip it and leave the stored post, including its matched keyword, unchanged. — source: S-1
- AC-54 (US-7, unwanted) IF a keyword query returns an empty array, THEN the system shall record zero results for that keyword and continue with the next keyword. — source: S-1, S-2 §7, S-6 (sensitive keywords return an empty array)
- AC-55 (US-7, unwanted) IF a keyword query returns OAuth error code 190, THEN the system shall end the run without querying the remaining keywords. — source: S-1 (the connection is handled by AC-16)
- AC-56 (US-7, unwanted) IF a keyword query fails with a network error, a timeout or an HTTP 5xx response, THEN the system shall log a warning with the keyword id and continue with the next keyword. — source: S-1
- AC-57 (US-7, unwanted) IF a keyword query returns an error that is neither code 190, nor a rate-limit error under AC-58, nor HTTP 5xx, THEN the system shall log an error with the keyword id and the Threads error code and continue with the next keyword. — source: S-1
- AC-58 (US-8, unwanted) IF a keyword query returns error code 4, 17, 32, 341 or 613, HTTP 429, or an error message containing "limit" (case-insensitive), THEN the system shall end the run without querying the remaining keywords, record the outcome `paused_quota`, and pause that user's search for 1 hour. — source: D-8
- AC-74 (US-8, event-driven) WHEN a rate-limit error starts a pause, the system shall log the full Threads error body, without the access token, once for that pause. — source: D-8, V-1
- AC-73 (US-8, state-driven) WHILE a user's search is paused, the system shall start no scheduled or manual search run for that user. — source: D-8
- AC-59 (US-8, state-driven) WHILE the number of `keyword_search` requests the system sent for a user in the last 24 hours is at or above 2,000, the system shall send no further `keyword_search` requests for that user. — source: S-1, S-2 §7, D-2
- AC-60 (US-7, event-driven) WHEN a search run ends, the system shall record its trigger (`scheduled` or `manual`), start and end time, the number of queries sent, the number of new posts stored, and its outcome (`completed`, `partial`, `paused_quota`, `budget_reached` or `stopped_disconnected`). — source: S-3 §5, D-16, D-19
- AC-78 (US-7, event-driven) WHEN a search run has stored at least one new post, the system shall dispatch the `ThreadsPostsStored` event with the user id and the ids of the new posts. — source: D-23
- AC-104 (US-7, ubiquitous) The system shall send no notifications about found posts. — source: D-23

### Manual run ("Run scout")

- AC-71 (US-11, event-driven) WHEN a user presses "Run scout" on the Dashboard or the Posts page and none of the conditions in AC-72 applies, the system shall queue a manual search run for that user and show the toast T-10. — source: D-19
- AC-72 (US-11, unwanted) IF the user has no `connected` connection, has no keywords, has a run queued or in progress, started a manual run less than 15 minutes ago, has search paused, or has fewer queries left in the last-24-hours budget than keywords, THEN the system shall refuse the manual run and show the matching reason text M-1 to M-6 next to the disabled "Run scout" button. — source: D-19, D-2
- AC-111 (US-11, state-driven) WHILE the user's search run is queued or running and the Dashboard or the Posts page is open, the system shall request an Inertia partial reload of only `searchStatus` and the page's posts (`latestPosts` on the Dashboard, `posts` on the Posts page) every 10 seconds. — source: D-28
- AC-112 (US-11, event-driven) WHEN a partial reload shows that no run is queued or running, the system shall stop polling. — source: D-28
- AC-113 (US-11, event-driven) WHEN polling observes that a run has ended, the system shall show the toast T-11 with the number of new posts that run stored. — source: D-28

### Found posts list

- AC-61 (US-9, event-driven) WHEN a user opens `/posts`, the system shall list only that user's found posts, ordered by post timestamp, newest first, 10 per page. — source: S-1, S-4 (Posts · filters + pagination: "Newest first"), S-5 (`usePosts.ts:38`), D-14
- AC-62 (US-9, ubiquitous) The system shall show for each found post its text excerpt (or "(no text)" when the post has none), `@username`, matched keyword, post time (relative, with the absolute time on hover) and found time, and an "Open in Threads" link that opens the permalink in a new tab. — source: S-1, S-4, S-5 (`PostsTable.vue`), DG-10 (D-17)
- AC-82 (US-9, ubiquitous) The system shall show no STATUS or SCORE column, relevance filter, "Highest score" sort, "Filtered and scored by AI" subtitle or "Remote · contract opportunity" line on the Posts page. — source: DG-10 (D-17), D-14
- AC-63 (US-9, event-driven) WHEN a user submits a search term on `/posts`, the system shall list only that user's found posts whose text or username contains the term. — source: S-5 (`usePosts.ts`)
- AC-83 (US-9, event-driven) WHEN a user picks a keyword in the keyword filter on `/posts`, the system shall list only that user's found posts whose stored keyword text equals the picked keyword. — source: D-17 (DG-11)
- AC-97 (US-9, ubiquitous) The system shall keep `search`, `keyword` and `page` in the URL query of `/posts`, so that the back button and shared links restore them. — source: DG-11 (D-17)
- AC-64 (US-9, unwanted) IF the requested page number is greater than the last page, THEN the system shall show the last page. — source: S-5 (`usePosts.ts` `setPage` clamps)
- AC-98 (US-9, state-driven) WHILE an Inertia visit for a search, filter or page change on `/posts` is in progress, the system shall show the existing table skeleton. — source: DG-14 (D-17), S-4 (Posts · Loading)
- AC-65 (US-9, state-driven) WHILE the user has no found posts and no `connected` connection, the system shall show the empty state E-1 with a "Connect Threads" button. — source: DG-12 (D-18)
- AC-85 (US-9, state-driven) WHILE the user has no found posts, a `connected` connection and no keywords, the system shall show the empty state E-2 with an "Add keywords" button that opens the Scraping tab. — source: DG-12 (D-18)
- AC-86 (US-9, state-driven) WHILE the user has no found posts, a `connected` connection and at least one keyword, the system shall show the empty state E-3 with the time of the last search. — source: DG-12 (D-18), S-4 (Posts · Empty)
- AC-87 (US-9, state-driven) WHILE a search term or keyword filter is active and no posts match, the system shall show the empty state E-4 with a "Clear filters" button. — source: D-18, S-5 (`PostsTable.vue:62`)
- AC-88 (US-7, state-driven) WHILE the user has a Threads connection, the system shall show above the posts table one search status line ST-1 to ST-6 that matches the latest run and pause state. — source: DG-13 (D-17)
- AC-66 (US-9, event-driven) WHEN the daily `threads:prune-posts` command runs, the system shall delete every found post whose found time is more than 90 days in the past. — source: D-4

### Post detail

- AC-79 (US-9, event-driven) WHEN a user opens `/posts/{id}` for one of their found posts, the system shall show its text, `@username`, matched keyword, post time and an "Open in Threads" link to its permalink. — source: D-21, S-4 (Post Detail: "@devfounder · 4 minutes ago")
- AC-80 (US-9, unwanted) IF the requested post does not exist or belongs to another user, THEN the system shall respond with HTTP 404. — source: D-21, NFR-6
- AC-81 (US-9, ubiquitous) The system shall show no relevance chip and no "Why AI marked it relevant" block on the post detail page. — source: D-21
- AC-110 (US-9, ubiquitous) The system shall show no "Mark as relevant" toggle in the Posts rows, the Dashboard latest posts or the post detail page, and no "Suggested response" block on the post detail page. — source: D-26

### Dashboard

- AC-75 (US-10, ubiquitous) The system shall show on the Dashboard a "Threads searches" widget with the queries sent in the last 24 hours, the queries remaining (2,000 minus the queries sent, never below 0) and the 2,000 budget, all computed from the app's own search-run records. — source: D-16
- AC-76 (US-10, ubiquitous) The system shall show in the quota widget the explanation N-11 that the 2,200 limit is shared with other apps and that the remaining number is an estimate. — source: D-16
- AC-77 (US-9, ubiquitous) The system shall show on the Dashboard the real number of posts found in total, posts found in the last 7 days, posts found today, the found-posts series per day (days and "today" both start at 00:00 in the time zone chosen by AC-107 and AC-108), and the 5 most recently found posts under the heading "Latest posts". — source: D-24, D-27
- AC-109 (US-9, event-driven) WHEN a page of the signed-in app loads, the system shall write the browser's IANA time zone identifier to the `timezone` cookie. — source: D-27
- AC-107 (US-9, event-driven) WHEN the Dashboard request carries a `timezone` cookie that is a valid IANA time zone identifier, the system shall compute "posts found today" and the per-day series in that time zone. — source: D-27
- AC-108 (US-9, unwanted) IF the Dashboard request has no `timezone` cookie, or its value is not a valid IANA time zone identifier, THEN the system shall compute "posts found today" and the per-day series in UTC. — source: D-27
- AC-105 (US-9, ubiquitous) The system shall keep the "Relevant posts" card, the relevant-posts chart series and the AI filtering alert on the Dashboard on their current mock data. — source: D-24

## UI states

All copy is English (D-18). `{…}` marks values filled in at runtime.

| ID        | Screen                      | State                              | What the user sees                                                                                                                                                                                                                                         | AC                   |
| --------- | --------------------------- | ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| —         | Threads tab                 | Not connected                      | Badge "Not connected"; "You will be redirected to Threads to authorize read access. We never post on your behalf."; button "Connect Threads"                                                                                                               | AC-1                 |
| —         | Threads tab                 | Redirecting                        | "Connect Threads" disabled with spinner                                                                                                                                                                                                                    | AC-102               |
| —         | Threads tab                 | Connected                          | Badge "Connected"; Account `@{username}`, Name `{name}` (row hidden when null), Connected `{date}`, Access expires `{date}`; button "Disconnect"                                                                                                           | AC-10                |
| N-6       | Threads tab                 | Connected, expires within 3 days   | "Threads access expires on {date} and couldn't be renewed automatically. Reconnect to keep search running." + "Reconnect Threads"                                                                                                                          | AC-93                |
| —         | Threads tab                 | Disconnected                       | Badge "Disconnected"; "Threads is disconnected." + reason R-n; button "Reconnect Threads"                                                                                                                                                                  | AC-15                |
| R-1       | reason `user`               | —                                  | "You disconnected Threads."                                                                                                                                                                                                                                | AC-15, AC-68         |
| R-2       | reason `uninstalled`        | —                                  | "Access was removed in Threads."                                                                                                                                                                                                                           | AC-15, AC-68         |
| R-3       | reason `token_invalid`      | —                                  | "Threads rejected the saved access."                                                                                                                                                                                                                       | AC-15, AC-68         |
| R-4       | reason `expired`            | —                                  | "Threads access expired."                                                                                                                                                                                                                                  | AC-15, AC-68         |
| DLG-1     | Threads tab                 | Disconnect confirmation            | Title "Disconnect Threads?"; "Search stops until you reconnect. Your keywords and found posts are kept."; buttons "Cancel", "Disconnect"                                                                                                                   | AC-94                |
| N-5       | Threads tab                 | After disconnect                   | "To revoke access completely, open Threads → Settings → Account → Website permissions and remove Opportunity Scout."                                                                                                                                       | AC-91                |
| B-1       | Every signed-in page        | Connection disconnected            | Banner "Threads is disconnected. Search is stopped. {reason R-n}" + link "Reconnect Threads"                                                                                                                                                               | AC-68                |
| T-1       | Toast (success)             | Connected                          | "Threads account connected."                                                                                                                                                                                                                               | AC-10                |
| T-2       | Toast (error)               | Declined                           | "Threads access was not granted."                                                                                                                                                                                                                          | AC-4                 |
| T-3       | Toast (error)               | State check failed                 | "We couldn't verify the Threads connection. Please try again."                                                                                                                                                                                             | AC-3                 |
| T-4       | Toast (error)               | Exchange or `/me` failed           | "Connecting Threads failed. Please try again."                                                                                                                                                                                                             | AC-6, AC-7           |
| T-5       | Toast (error)               | Account taken                      | "This Threads account is already connected to another Opportunity Scout account."                                                                                                                                                                          | AC-67                |
| T-6       | Toast (success)             | Disconnected                       | "Threads account disconnected."                                                                                                                                                                                                                            | AC-11                |
| T-7       | Toast (success)             | Keyword added / renamed / removed  | "Keyword added." / "Keyword updated." / "Keyword removed."                                                                                                                                                                                                 | AC-34, AC-39, AC-40  |
| T-8       | Toast (success)             | Search settings saved              | "Search settings saved."                                                                                                                                                                                                                                   | AC-70                |
| T-9       | Toast (info)                | Disconnect without a connection    | "Threads is not connected."                                                                                                                                                                                                                                | RT-4                 |
| T-10      | Toast (success)             | Manual run queued                  | "Search started."                                                                                                                                                                                                                                          | AC-71                |
| T-11      | Toast (success)             | Run ended (seen by polling)        | "Search finished: {n} new posts."                                                                                                                                                                                                                          | AC-113               |
| —         | Dashboard, Posts page       | Run queued or running              | "Run scout" shows "Running…" and is disabled; status line ST-6; posts refresh every 10 seconds                                                                                                                                                             | AC-111, AC-112       |
| N-1       | Scraping tab                | Not connected                      | "Threads is not connected. Keywords are saved but not searched until you connect." + link "Connect Threads"                                                                                                                                                | AC-69                |
| N-2       | Scraping tab                | Usage                              | "Uses {planned} of 2,000 daily Threads searches ({keywords} keywords × {runs} runs a day)."                                                                                                                                                                | AC-42                |
| N-3       | Scraping tab                | Keyword cap reached                | "Keyword limit reached ({cap} keywords for a {interval}-minute interval)."                                                                                                                                                                                 | AC-96                |
| N-4       | Scraping tab                | Language hint                      | "Threads search doesn't translate. Add each keyword in every language you need, for example freelancer, фрілансер."                                                                                                                                        | AC-89                |
| N-7       | Scraping tab                | Keyword with no results for 7 days | "No results for 7 days. Threads hides results for some keywords it treats as sensitive."                                                                                                                                                                   | AC-92                |
| —         | Scraping tab                | Keyword status tooltip             | "Last searched {time} · {n} posts" or "Not searched yet"                                                                                                                                                                                                   | AC-95                |
| —         | Scraping tab                | Search settings                    | Select "Search interval": "Every 5 minutes", "Every 15 minutes", "Every 30 minutes", "Every hour"; select "First search of a new keyword": "No lookback", "Last 1 day", "Last 3 days"                                                                      | AC-70                |
| N-10      | Posts page, Scraping tab    | Own-posts-only flag on             | "Until Meta approves search access, results include only your own Threads posts."                                                                                                                                                                          | AC-90                |
| E-1       | Posts page                  | No posts, not connected            | Title "Connect Threads to start"; "Opportunity Scout searches Threads on your behalf. Connect your account to start finding posts."; button "Connect Threads"                                                                                              | AC-65                |
| E-2       | Posts page                  | No posts, connected, no keywords   | Title "Add keywords to start"; "Add the words people use when they post work requests."; button "Add keywords"                                                                                                                                             | AC-85                |
| E-3       | Posts page                  | No posts, connected, keywords      | Title "No posts yet"; "Opportunity Scout hasn't found any posts yet. Last search: {time or 'not run yet'}."                                                                                                                                                | AC-86                |
| E-4       | Posts page                  | Filters match nothing              | Title "No matching posts"; "No posts match your search or keyword filter."; button "Clear filters"                                                                                                                                                         | AC-87                |
| ST-1      | Posts page status line      | Normal                             | "Last search: {time} · Next search: {time}"                                                                                                                                                                                                                | AC-88                |
| ST-2      | Posts page status line      | Paused (rate limit)                | "Paused until {time}: Threads search limit reached."                                                                                                                                                                                                       | AC-88                |
| ST-3      | Posts page status line      | Budget used up                     | "Daily search budget used up. Search resumes as earlier searches leave the 24-hour window."                                                                                                                                                                | AC-88                |
| ST-4      | Posts page status line      | Disconnected                       | "Stopped: Threads is disconnected."                                                                                                                                                                                                                        | AC-88                |
| ST-5      | Posts page status line      | Last run partly failed             | "The last search partly failed. The next search retries."                                                                                                                                                                                                  | AC-88                |
| ST-6      | Posts page status line      | Run queued or running              | "Searching Threads…"                                                                                                                                                                                                                                       | AC-88                |
| M-1 … M-6 | "Run scout" disabled reason | —                                  | M-1 "Connect Threads to run a search." · M-2 "Add keywords to run a search." · M-3 "A search is already running." · M-4 "You can run a search again at {time}." · M-5 "Search is paused until {time}." · M-6 "Not enough searches left in today's budget." | AC-72                |
| —         | Post detail                 | Real post                          | "Back to posts"; `@{username}` · {post time}; text; keyword chip; button "Open in Threads"; no relevance chip, no "Why AI marked it relevant", no "Mark as relevant", no "Suggested response"                                                              | AC-79, AC-81, AC-110 |
| —         | Dashboard                   | Quota widget                       | Title "Threads searches"; "{used} used in the last 24 hours" · "{remaining} left" · "Budget 2,000"                                                                                                                                                         | AC-75                |
| N-11      | Dashboard                   | Quota explanation                  | "Threads allows 2,200 searches per 24 hours for your account, shared with other apps you use with Threads. Opportunity Scout plans with 2,000, so the number left is an estimate."                                                                         | AC-76                |
| N-8       | Data-deletion page          | Known code                         | "Deletion request {code}" · "Requested {date}" · "Status: Pending" or "Status: Completed on {date}"                                                                                                                                                        | AC-24                |
| N-9       | Data-deletion page          | Unknown code                       | "No deletion request with this code was found."                                                                                                                                                                                                            | AC-25                |

## Query budget

This section shows how the search interval × the number of keywords stays under the Threads limit (S-1, S-6, D-2, D-3).

- One search run sends exactly one `keyword_search` request per keyword (`limit=100`, no pagination, D-9).
- Runs per day: `R = 1440 / interval_minutes`.
- Keyword cap: `cap = min(20, floor(2000 / R))`.
- Planned daily queries: `P = K × R ≤ cap × R ≤ 2,000`, where `K` is the user's number of keywords.
- Enforced at save time (AC-37, AC-44) and at run time with a rolling 24-hour count of requests actually sent (AC-59). The count covers scheduled and manual runs (AC-72).
- Meta counts only queries that return results and shares the 2,200 quota with other apps (S-6). Our count includes every request sent, so it errs on the safe side. Quota spent by other apps is handled by AC-58.

| Interval | Runs per day (R) | floor(2,000 / R) | Keyword cap | Planned queries at the cap | Headroom for manual runs  |
| -------- | ---------------- | ---------------- | ----------- | -------------------------- | ------------------------- |
| 5 min    | 288              | 6                | 6           | 1,728                      | 272 (45 manual runs of 6) |
| 15 min   | 96               | 20               | 20          | 1,920                      | 80 (4 manual runs of 20)  |
| 30 min   | 48               | 41               | 20          | 960                        | 1,040                     |
| 60 min   | 24               | 83               | 20          | 480                        | 1,520                     |

Check: at 5 minutes, 7 keywords would need 2,016 queries, over the budget, so the cap is 6. A manual run is refused when the queries left are fewer than the keyword count (AC-72). Manual runs that use up the headroom can make later scheduled runs end with `budget_reached` (AC-59) until earlier queries leave the 24-hour window.

## Edge cases

- EC-1 The user declines authorization on Threads → covered by AC-4
- EC-2 The callback URL is reloaded or reached again with the back button (state and code already used) → covered by AC-3
- EC-3 The user stays on the Threads consent screen for more than 1 hour, so the code expires → covered by AC-6
- EC-4 The session expires during OAuth; the user logs in again and the callback arrives without the stored `state` → covered by AC-3
- EC-5 Double click on "Connect Threads", or two tabs: only the most recent `state` is valid → covered by AC-2, AC-3, AC-102
- EC-6 A third party sends the victim a callback link carrying the attacker's `code` (login CSRF) → covered by AC-3
- EC-7 The short-lived exchange succeeds but the long-lived exchange or `/me` fails → covered by AC-7
- EC-8 The same Threads account is already connected to another Scout user → covered by AC-67
- EC-9 A user reconnects with a different Threads account → covered by AC-13
- EC-10 A private Threads profile's permission lapses after 90 days (S-8), and the next call returns error 190 → covered by AC-16
- EC-11 `APP_KEY` is rotated and stored tokens can't be decrypted → covered by AC-17
- EC-12 The scheduler or app is down for more than 7 days, and a token expires before it is refreshed → covered by AC-33
- EC-13 A refresh succeeds at Meta but saving the new token fails, and the next call with the old token returns error 190 → covered by AC-16
- EC-14 An uninstall or delete callback arrives for a Threads user id with no connection → covered by AC-19, AC-22
- EC-15 Meta resends the same callback → covered by AC-18 (no change on repeat), AC-21 (a new code each time)
- EC-16 A keyword that Threads treats as sensitive returns an empty array, which looks the same as "no posts" → covered by AC-54, AC-92
- EC-17 Loose matches: word forms and hashtags (`фрілансер` → `фрілансера`, `розробка` → `#розробка`) → covered by AC-52 (stored unfiltered; filtering is a non-goal)
- EC-18 No translation: `freelancer` does not find `фрілансера` → covered by AC-34, AC-89
- EC-19 The same post matches two keywords in one run or in later runs → covered by AC-53
- EC-20 A query returns exactly 100 posts, so more may exist in the window → covered by AC-48 (accepted: no pagination, D-9)
- EC-21 A post is indexed by Threads after its time window was already searched → covered by AC-50 (10-minute overlap)
- EC-22 A stored post is later edited or deleted on Threads → covered by AC-53 (stored record unchanged; no re-check)
- EC-23 A returned post has no text (media only) or no valid permalink → covered by AC-52, AC-62, UI-5
- EC-24 Very long post text, emoji, RTL or mixed scripts → covered by AC-62, UI-4
- EC-25 A keyword with a leading `#`, emoji or Cyrillic letters → covered by AC-34
- EC-26 A keyword is deleted while a run that includes it is in progress → covered by AC-40 (only runs that start later are affected), AC-103
- EC-27 The user deletes their Scout account → covered by MC-4
- EC-28 Other apps use up the shared quota → covered by AC-58, AC-76
- EC-29 A keyword is renamed to one that already exists → covered by AC-36
- EC-30 The user picks the 5-minute interval while having more than 6 keywords → covered by AC-44
- EC-31 The queue worker is stopped and runs pile up → covered by AC-47; visibility: AC-88
- EC-32 The user disconnects while a run is in progress → covered by AC-12
- EC-33 Threads ids (for example `29580006461583503`) exceed JavaScript's safe integer range → covered by "Data model" (stored and sent as strings)
- EC-34 The `since` value must be at least `1688540400` and less than `until` (S-6) → covered by AC-49 (window start is always in the past), AC-50
- EC-35 Before App Review, search returns only the connected user's own posts, which development and tests rely on → covered by AC-48, AC-90
- EC-36 A non-quota error whose message contains "limit" (for example an invalid parameter) is treated as a rate limit and pauses search for 1 hour → covered by AC-58 (accepted by D-8); confirm the real error body: V-1
- EC-37 "Run scout" pressed twice, or in two tabs → covered by AC-47, AC-72
- EC-38 "Run scout" when fewer queries are left than keywords → covered by AC-72
- EC-39 Keywords added before connecting: the first search covers from the time each keyword was added, minus the lookback → covered by AC-43, AC-49
- EC-40 The user changes the lookback after adding a keyword → covered by AC-49 (the window was set when the keyword was added or renamed)
- EC-41 The delete callback runs, then the user connects again → covered by AC-21 (keywords kept), AC-5 (new connection)
- EC-42 A 1-hour pause ends between two scheduled runs → covered by AC-45, AC-73 (the next scheduled run after the pause starts normally)
- EC-43 "Posts found today" and the per-day series depend on a time zone → covered by AC-107, AC-108, AC-109
- EC-47 First visit: the `timezone` cookie isn't set yet when the first full page load reaches the server → covered by AC-108 (UTC for that request); later requests carry the cookie (AC-109)
- EC-48 The cookie holds a forged, outdated or overlong value (for example `Mars/Base` or 500 characters) → covered by AC-108, UI-12
- EC-49 The user travels, or changes the computer's time zone → covered by AC-107, AC-109 (the next page load writes the new zone)
- EC-50 A run ends with an outcome other than `completed` (for example `paused_quota`) while polling → covered by AC-113 (the toast reports the posts stored so far), AC-88 (status line shows the outcome)
- EC-51 The user leaves the page while polling → covered by AC-111 (polling only while the Dashboard or Posts page is open)
- EC-44 The keyword filter and posts of a deleted keyword: filter options are the user's current keywords, so those posts are reachable only without a keyword filter → covered by AC-83, AC-103
- EC-45 A post detail link for a post deleted by the 90-day cleanup or by the delete callback → covered by AC-80
- EC-46 A run never records its end (worker crash) → covered by AC-47; the plan must make sure such a run stops blocking later runs (see the note under `threads_search_runs`)

## Non-functional requirements

- NFR-1 (security) The system shall store Threads access tokens only encrypted at rest with Laravel's `encrypted` cast. — source: S-1, S-2 §7
- NFR-2 (security) The system shall not write access tokens, authorization codes, the app secret or raw `signed_request` values to logs, exception reports, queued job payloads or Inertia props. — source: S-1, S-2 §7
- NFR-3 (security) The system shall read the Threads app id, app secret, redirect URI, authorization URL, Graph API base URL and the own-posts-only flag only from `config/services.php` under `services.threads`, which takes them from environment variables. — source: S-1, D-20 (UX-2)
- NFR-4 (security) The system shall compare OAuth `state` values and `signed_request` signatures in constant time. — source: S-9, S-11
- NFR-5 (security) The system shall make every Threads API call through one client class that uses the Laravel HTTP client. — source: S-1
- NFR-6 (privacy) The system shall show found posts, keywords, search runs and connection details only to the user who owns them. — source: S-1
- NFR-7 (accessibility) The system shall show the Threads connection status, the banner reason and the "Run scout" disabled reason as text, not as color alone. — source: S-5 (`ThreadsConnectionCard.vue:40` badge text)
- NFR-8 (observability) WHEN `threads:refresh-tokens` finishes, the system shall write one log entry with the number of connections refreshed, failed and disconnected. — source: S-1
- NFR-9 (observability) WHEN a search run ends, the system shall write one log entry with the user id, the trigger, the number of queries sent, the number of new posts and the outcome. — source: S-1
- NFR-10 (observability) WHEN `threads:prune-posts` finishes, the system shall write one log entry with the number of posts deleted. — source: D-4
- NFR-11 (privacy) The system shall store no Threads user id and no username in data-deletion request records. — source: UX-7 (D-20)
- NFR-12 (security) IF a client exceeds 10 connect or callback requests per minute per user, 60 Meta callback requests per minute per IP, 30 keyword writes per minute per user, or 10 manual-run requests per minute per user, THEN the system shall respond with HTTP 429. — source: UX-8 (D-20)

## Data model

This is the contract the implementation plan has to meet; S-1 asked for it explicitly. Laravel defaults apply unless stated: `id` is an unsigned big-integer primary key, and timestamps are `created_at` and `updated_at`, nullable. All times are stored in UTC.

### `threads_connections` (new)

One row per Scout user. The row is kept, with no token, when the connection becomes `disconnected`. It is deleted by the delete callback (D-12) and with the user's account (MC-4).

| Column                | Type                | Null | Default     | Notes                                                                                                                                                                      |
| --------------------- | ------------------- | ---- | ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`                  | bigint unsigned, PK | no   | auto        |                                                                                                                                                                            |
| `user_id`             | bigint unsigned     | no   | —           | FK → `users.id`, **on delete cascade**; **unique**                                                                                                                         |
| `threads_user_id`     | varchar(64)         | no   | —           | Threads user id as a string; **index**. Not unique at database level, because `disconnected` rows keep it; "one `connected` user per Threads account" is enforced by AC-67 |
| `username`            | varchar(255)        | no   | —           | from `/me`                                                                                                                                                                 |
| `name`                | varchar(255)        | yes  | null        | from `/me` (D-17, DG-3)                                                                                                                                                    |
| `access_token`        | text                | yes  | null        | long-lived token, `encrypted` cast; null when disconnected                                                                                                                 |
| `token_expires_at`    | timestamp           | yes  | null        | AC-9, AC-28                                                                                                                                                                |
| `token_issued_at`     | timestamp           | yes  | null        | 24-hour refresh rule (AC-27)                                                                                                                                               |
| `status`              | varchar(16)         | no   | `connected` | enum: `connected`, `disconnected`                                                                                                                                          |
| `disconnect_reason`   | varchar(32)         | yes  | null        | enum: `user`, `uninstalled`, `token_invalid`, `expired`; null while connected                                                                                              |
| `search_paused_until` | timestamp           | yes  | null        | 1-hour rate-limit pause (AC-58, AC-73)                                                                                                                                     |
| `connected_at`        | timestamp           | no   | —           | time of the last successful OAuth                                                                                                                                          |
| `disconnected_at`     | timestamp           | yes  | null        |                                                                                                                                                                            |
| timestamps            |                     |      |             |                                                                                                                                                                            |

Indexes: unique(`user_id`); index(`threads_user_id`, `status`) for callbacks and AC-67; index(`status`, `token_expires_at`) for the refresh selection (AC-27).

### `threads_keywords` (new)

| Column                | Type            | Null | Default | Notes                                                                                                       |
| --------------------- | --------------- | ---- | ------- | ----------------------------------------------------------------------------------------------------------- |
| `id`                  | bigint unsigned | no   | auto    |                                                                                                             |
| `user_id`             | bigint unsigned | no   | —       | FK → `users.id`, **on delete cascade**                                                                      |
| `keyword`             | varchar(100)    | no   | —       | normalised (AC-34); compared byte for byte (binary collation), so `cafe` ≠ `café`                           |
| `search_window_start` | timestamp       | no   | —       | next `since`; set on add or rename (AC-49), advanced by AC-50                                               |
| `last_searched_at`    | timestamp       | yes  | null    | last successful query (AC-95)                                                                               |
| `last_result_count`   | int unsigned    | yes  | null    | posts returned by the last successful query (AC-95)                                                         |
| `zero_results_since`  | timestamp       | yes  | null    | set by the first empty result after a non-empty one, cleared by a non-empty result, reset on rename (AC-92) |
| timestamps            |                 |      |         |                                                                                                             |

Indexes: **unique(`user_id`, `keyword`)** (AC-36).

### `threads_posts` (new)

| Column               | Type            | Null | Default | Notes                                                                        |
| -------------------- | --------------- | ---- | ------- | ---------------------------------------------------------------------------- |
| `id`                 | bigint unsigned | no   | auto    |                                                                              |
| `user_id`            | bigint unsigned | no   | —       | FK → `users.id`, **on delete cascade**                                       |
| `threads_keyword_id` | bigint unsigned | yes  | null    | FK → `threads_keywords.id`, **on delete set null** (D-13)                    |
| `threads_post_id`    | varchar(64)     | no   | —       | Threads media id as a string                                                 |
| `text`               | text            | yes  | null    | media-only posts have no text                                                |
| `permalink`          | varchar(2048)   | yes  | null    | null when it fails UI-5                                                      |
| `username`           | varchar(255)    | no   | —       | author, without `@`                                                          |
| `keyword`            | varchar(100)    | no   | —       | snapshot of the matched keyword, kept when the keyword is renamed or deleted |
| `posted_at`          | timestamp       | no   | —       | the Threads `timestamp` field, converted to UTC                              |
| `created_at`         | timestamp       | yes  | —       | "found at"; retention is counted from it (D-4)                               |
| `updated_at`         | timestamp       | yes  | —       |                                                                              |

No AI columns (D-22). Indexes: **unique(`user_id`, `threads_post_id`)**, the deduplication key (AC-53); index(`user_id`, `posted_at`) for the posts list (AC-61); index(`user_id`, `keyword`) for the keyword filter (AC-83); index(`user_id`, `created_at`) for Dashboard counts (AC-77); index(`created_at`) for the 90-day cleanup (AC-66). The text search (AC-63) is a `LIKE` scan within one user's rows.

### `threads_search_runs` (new)

Feeds the rolling 24-hour query count (AC-59, AC-72, AC-75), the manual-run cooldown (AC-72) and the search status line (AC-88).

| Column         | Type            | Null | Default | Notes                                                                                  |
| -------------- | --------------- | ---- | ------- | -------------------------------------------------------------------------------------- |
| `id`           | bigint unsigned | no   | auto    |                                                                                        |
| `user_id`      | bigint unsigned | no   | —       | FK → `users.id`, **on delete cascade**                                                 |
| `trigger`      | varchar(16)     | no   | —       | enum: `scheduled`, `manual`                                                            |
| `queued_at`    | timestamp       | no   | —       |                                                                                        |
| `started_at`   | timestamp       | yes  | null    | null while queued                                                                      |
| `finished_at`  | timestamp       | yes  | null    | null while queued or running                                                           |
| `queries_sent` | int unsigned    | no   | 0       | incremented per request sent                                                           |
| `posts_stored` | int unsigned    | no   | 0       | new posts only                                                                         |
| `outcome`      | varchar(32)     | yes  | null    | enum: `completed`, `partial`, `paused_quota`, `budget_reached`, `stopped_disconnected` |
| timestamps     |                 |      |         |                                                                                        |

Indexes: index(`user_id`, `started_at`) for the rolling count and the last run; index(`user_id`, `trigger`, `queued_at`) for the manual cooldown. Rolling count = sum of `queries_sent` over the user's runs with `started_at` in the last 24 hours. Rows from the last 24 hours must be kept; older rows can be pruned. Note for the plan: a run that never records `finished_at` (worker crash) must stop counting as "queued or in progress" after a bounded time, or AC-47 would block all later runs.

### `threads_data_deletion_requests` (new)

| Column              | Type            | Null | Default   | Notes                          |
| ------------------- | --------------- | ---- | --------- | ------------------------------ |
| `id`                | bigint unsigned | no   | auto      |                                |
| `confirmation_code` | varchar(32)     | no   | —         | alphanumeric, **unique** (S-9) |
| `status`            | varchar(16)     | no   | `pending` | enum: `pending`, `completed`   |
| `requested_at`      | timestamp       | no   | —         |                                |
| `completed_at`      | timestamp       | yes  | null      |                                |
| timestamps          |                 |      |           |                                |

No Threads user id or username (NFR-11).

### `users` (existing, changed)

| Column                       | Type              | Null | Default | Notes                         |
| ---------------------------- | ----------------- | ---- | ------- | ----------------------------- |
| `search_interval_minutes`    | smallint unsigned | no   | 15      | allowed: 5, 15, 30, 60 (D-1)  |
| `first_search_lookback_days` | tinyint unsigned  | no   | 1       | allowed: 0 (none), 1, 3 (D-6) |

No other existing table changes.

### Relationships

- `User` hasOne `ThreadsConnection`; `ThreadsConnection` belongsTo `User`.
- `User` hasMany `ThreadsKeyword`; `ThreadsKeyword` belongsTo `User`.
- `User` hasMany `ThreadsPost`; `ThreadsPost` belongsTo `User`.
- `ThreadsKeyword` hasMany `ThreadsPost`; `ThreadsPost` belongsTo `ThreadsKeyword` (nullable).
- `User` hasMany `ThreadsSearchRun`; `ThreadsSearchRun` belongsTo `User`.
- `ThreadsDataDeletionRequest` has no relationships.

### Event

- `ThreadsPostsStored` — payload: `userId` (int), `postIds` (int[], our `threads_posts.id` values). Dispatched once per run that stored at least one new post (AC-78). It has no listeners in this spec.

## API

### Our routes

All web routes return Inertia pages or redirects. Flash toasts use the existing `Inertia::flash('toast', ['type' => ..., 'message' => ...])` pattern (S-5, `ProfileController.php:41`), with the copy in "UI states". In route names, the last segment is camelCase so that Wayfinder generates valid identifiers (frontend insight 2026-09-18).

Shared prop (every authenticated Inertia response, `HandleInertiaRequests::share`): `threadsConnection: null | {status: 'connected'|'disconnected', disconnectReason: 'user'|'uninstalled'|'token_invalid'|'expired'|null, username: string}`. It drives the banner B-1 (AC-68).

`searchStatus` (used by RT-13 and RT-16): `{runState: 'idle'|'queued'|'running', lastRunId: number|null, lastRunFinishedAt: ISO 8601|null, lastRunPostsStored: number|null, lastRunOutcome: 'completed'|'partial'|'paused_quota'|'budget_reached'|'stopped_disconnected'|null, nextRunAt: ISO 8601|null, pausedUntil: ISO 8601|null, manualRunBlockedReason: 'not_connected'|'no_keywords'|'run_in_progress'|'cooldown'|'paused'|'budget'|null, manualRunAvailableAt: ISO 8601|null}`.

Post shape (used by RT-13, RT-14, RT-16): `{id: number, threadsPostId: string, text: string|null, permalink: string|null, username: string, keyword: string, postedAt: ISO 8601, foundAt: ISO 8601}`.

| #     | Method | Path                                                      | Name                             | Middleware                                                                                                                          | Request and validation                                                                                                                                                  | Response                                                                                                                                                                                                                                                                                                                                                          | Errors                                                                                           |
| ----- | ------ | --------------------------------------------------------- | -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| RT-1  | GET    | `/settings/threads` (exists, becomes a controller route)  | `settings.threads`               | `auth`, `verified`                                                                                                                  | —                                                                                                                                                                       | Inertia `scout-settings/Threads`, props `connection: null \| {username, name: string\|null, status, disconnectReason, connectedAt: ISO 8601, tokenExpiresAt: ISO 8601\|null}`                                                                                                                                                                                     | —                                                                                                |
| RT-2  | POST   | `/auth/threads/connect`                                   | `threads.connect`                | `auth`, `verified`, `throttle:10,1`                                                                                                 | none; generates `state` (AC-2)                                                                                                                                          | external redirect to the authorization URL: HTTP 409 with `X-Inertia-Location` for Inertia visits, HTTP 302 otherwise                                                                                                                                                                                                                                             | 429                                                                                              |
| RT-3  | GET    | `/auth/threads/callback` (registered in Meta)             | `threads.callback`               | `auth`, `verified`, `throttle:10,1`                                                                                                 | query `state` required string ≤ 255; `code` string ≤ 2048, required without `error`; `error`, `error_reason`, `error_description` strings ≤ 255, never displayed (UI-1) | redirect to `settings.threads` + toast T-1 (AC-10)                                                                                                                                                                                                                                                                                                                | redirect to `settings.threads` + toast T-2, T-3, T-4 or T-5 (AC-3, AC-4, AC-6, AC-7, AC-67); 429 |
| RT-4  | DELETE | `/auth/threads/connection`                                | `threads.disconnect`             | `auth`, `verified`                                                                                                                  | —                                                                                                                                                                       | redirect to `settings.threads` + toast T-6 (AC-11)                                                                                                                                                                                                                                                                                                                | no `connected` connection: redirect + toast T-9, no change                                       |
| RT-5  | POST   | `/auth/threads/uninstall` (registered in Meta)            | `threads.uninstall`              | no `auth`, no session, CSRF check excluded (Meta can't send a token; authenticity comes from the signature), `throttle:60,1` per IP | form field `signed_request` required string ≤ 8192 (UI-2)                                                                                                               | HTTP 200, empty body (AC-18, AC-19)                                                                                                                                                                                                                                                                                                                               | 400 (AC-20); 429                                                                                 |
| RT-6  | POST   | `/auth/threads/delete` (registered in Meta)               | `threads.dataDeletion`           | same as RT-5                                                                                                                        | same as RT-5                                                                                                                                                            | HTTP 200 `application/json` `{"url": "{APP_URL}/data-deletion?code={code}", "confirmation_code": "{code}"}` (AC-21, AC-22)                                                                                                                                                                                                                                        | 400 (AC-20); 429                                                                                 |
| RT-7  | GET    | `/data-deletion` (exists, becomes a controller route)     | `data-deletion`                  | public                                                                                                                              | query `code` optional, 1–32 alphanumeric characters (UI-7)                                                                                                              | Inertia `legal/DataDeletion`, props `deletionRequest: null \| {confirmationCode, status: 'pending'\|'completed', requestedAt, completedAt\|null}`, `codeNotFound: boolean` (AC-24, AC-25)                                                                                                                                                                         | —                                                                                                |
| RT-8  | GET    | `/settings/scraping` (exists, becomes a controller route) | `settings.scraping`              | `auth`, `verified`                                                                                                                  | —                                                                                                                                                                       | Inertia `scout-settings/Scraping`, props `keywords: [{id, keyword, lastSearchedAt\|null, lastResultCount\|null, zeroResultsSince\|null}]`, `searchSettings: {intervalMinutes: 5\|15\|30\|60, firstSearchLookbackDays: 0\|1\|3}`, `searchBudget: {dailyBudget: 2000, plannedDailyQueries, runsPerDay, keywordCap, keywordCount}`, `ownPostsOnly: boolean`          | —                                                                                                |
| RT-9  | POST   | `/settings/keywords`                                      | `settings.keywords.store`        | `auth`, `verified`, `throttle:30,1`                                                                                                 | `keyword` required string, normalised (AC-34), max 100, rules AC-35 to AC-37                                                                                            | redirect back (303) + toast T-7                                                                                                                                                                                                                                                                                                                                   | 422 with error bag key `keyword`; 429                                                            |
| RT-10 | PATCH  | `/settings/keywords/{keyword}`                            | `settings.keywords.update`       | same as RT-9                                                                                                                        | `keyword` as RT-9; uniqueness ignores itself (AC-39)                                                                                                                    | redirect back + toast T-7                                                                                                                                                                                                                                                                                                                                         | 404 when not the user's own (AC-41); 422                                                         |
| RT-11 | DELETE | `/settings/keywords/{keyword}`                            | `settings.keywords.destroy`      | `auth`, `verified`, `throttle:30,1`                                                                                                 | —                                                                                                                                                                       | redirect back + toast T-7 (AC-40, AC-103)                                                                                                                                                                                                                                                                                                                         | 404 when not the user's own (AC-41)                                                              |
| RT-12 | PATCH  | `/settings/search-settings`                               | `settings.searchSettings.update` | `auth`, `verified`                                                                                                                  | `interval_minutes` required integer, one of 5, 15, 30, 60, cap check (AC-44); `first_search_lookback_days` required integer, one of 0, 1, 3                             | redirect back + toast T-8 (AC-70)                                                                                                                                                                                                                                                                                                                                 | 422 with error bag keys `interval_minutes`, `first_search_lookback_days`                         |
| RT-13 | GET    | `/posts` (exists, becomes a controller route)             | `posts.index`                    | `auth`, `verified`                                                                                                                  | query `page` integer ≥ 1 (beyond last: AC-64); `search` string ≤ 100, nullable (UI-8); `keyword` string ≤ 100, nullable (UI-10)                                         | Inertia `Posts`, props `posts: {data: [post], currentPage, lastPage, perPage: 10, total}`, `filters: {search: string, keyword: string\|null}`, `keywordOptions: string[]` (user's current keywords), `keywordCount: number`, `searchStatus`, `ownPostsOnly: boolean`                                                                                              | 422 for an over-long `search` or `keyword`                                                       |
| RT-14 | GET    | `/posts/{post}` (exists, becomes a controller route)      | `posts.show`                     | `auth`, `verified`                                                                                                                  | `{post}` = our post id                                                                                                                                                  | Inertia `PostDetail`, props `post` (AC-79)                                                                                                                                                                                                                                                                                                                        | 404 (AC-80)                                                                                      |
| RT-15 | POST   | `/search-runs`                                            | `searchRuns.store`               | `auth`, `verified`, `throttle:10,1`                                                                                                 | none                                                                                                                                                                    | redirect back + toast T-10 (AC-71)                                                                                                                                                                                                                                                                                                                                | redirect back + error toast with M-1 to M-6 (AC-72); 429                                         |
| RT-16 | GET    | `/dashboard` (exists, becomes a controller route)         | `dashboard`                      | `auth`, `verified`                                                                                                                  | cookie `timezone`: IANA identifier ≤ 64 characters; missing or invalid → UTC (AC-107, AC-108, UI-12)                                                                    | Inertia `Dashboard`, props `threadsQuota: {usedLast24Hours: number, remaining: number, dailyBudget: 2000, threadsDailyLimit: 2200}`, `stats: {postsFound: number, foundLast7Days: number, foundToday: number}`, `activity: [{date: 'YYYY-MM-DD', found: number}]` (last 30 days), `latestPosts: [post]` (5 newest by found time), `searchStatus` (AC-75 to AC-77) | —                                                                                                |

Notes for these routes:

- RT-3 depends on the session cookie being sent on the top-level redirect from Threads. `SESSION_SAME_SITE` must stay `lax` (current default, `config/session.php:202`); `strict` would make AC-3 reject every callback.
- The `#_` that Threads appends to the redirect (S-7) is a URL fragment; browsers never send it to the server.
- Polling (AC-111) uses Inertia partial reloads of RT-13 with `only: ['searchStatus', 'posts']` and of RT-16 with `only: ['searchStatus', 'latestPosts']`. Both controllers must compute these props independently of the others, so that a partial reload doesn't compute the rest of the page.
- The `timezone` cookie (AC-109) is read by RT-16. It is a plain-text cookie, `path=/`, `SameSite=Lax`, and must be added to the `encryptCookies(except: [...])` list in `bootstrap/app.php`, next to `appearance`; otherwise Laravel would discard the browser-written value.
- Route names `threads.*` generate `@/routes/threads`. `delete` is not used as a name segment because it is a reserved word in JavaScript.

### Threads API calls (ThreadsClient)

Base URLs come from `services.threads` (NFR-3). The hand check used `threads.net` and `graph.threads.net` (S-2). Meta's current docs show `threads.com` and `graph.threads.com` (S-6, S-7, S-8). The value checked by hand is the default.

| #    | Purpose                                             | Method and URL                                                  | Parameters                                                                                                                                              | Response fields used                                                                                       | Errors handled → action                                                                                                                                                                         |
| ---- | --------------------------------------------------- | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| TA-1 | Authorization (browser redirect, not a client call) | GET `https://threads.net/oauth/authorize`                       | `client_id`, `redirect_uri`, `scope=threads_basic,threads_keyword_search`, `response_type=code`, `state`                                                | callback query: `code`, `state`; or `error=access_denied`, `error_reason=user_denied`, `error_description` | declined → AC-4; state mismatch → AC-3                                                                                                                                                          |
| TA-2 | Code → short-lived token (valid 1 h)                | POST `https://graph.threads.net/oauth/access_token` (form body) | `client_id`, `client_secret`, `code`, `grant_type=authorization_code`, `redirect_uri`                                                                   | `access_token`, `user_id`                                                                                  | error body `{error_type, code, error_message}`, for example code 400 "Matching code was not found or was already used" → AC-6; network, timeout, 5xx → AC-6                                     |
| TA-3 | Short-lived → long-lived token (60 days)            | GET `https://graph.threads.net/access_token`                    | `grant_type=th_exchange_token`, `client_secret`, `access_token` (short-lived)                                                                           | `access_token`, `expires_in`                                                                               | any error → AC-7                                                                                                                                                                                |
| TA-4 | Profile                                             | GET `https://graph.threads.net/v1.0/me`                         | `fields=id,username,name`, `access_token`                                                                                                               | `id`, `username`, `name`                                                                                   | during connect: any error → AC-7; account connected to another user → AC-67                                                                                                                     |
| TA-5 | Refresh long-lived token                            | GET `https://graph.threads.net/refresh_access_token`            | `grant_type=th_refresh_token`, `access_token` (long-lived, ≥ 24 h old, not expired)                                                                     | `access_token`, `expires_in`                                                                               | 190 → AC-30; network, timeout, 5xx → AC-31; other → AC-32                                                                                                                                       |
| TA-6 | Keyword search                                      | GET `https://graph.threads.net/v1.0/keyword_search`             | `q`, `search_type=RECENT`, `limit=100`, `since`, `until` (Unix seconds), `fields=id,text,permalink,timestamp,username`, `access_token`; no paging (D-9) | `data[].id`, `.text`, `.permalink`, `.timestamp`, `.username`                                              | 190 → AC-16 + AC-55; rate limit (code 4, 17, 32, 341 or 613, HTTP 429, or a message containing "limit") → AC-58 + AC-74; network, timeout, 5xx → AC-56; other 4xx → AC-57; empty `data` → AC-54 |

The Graph error body is `{"error": {"message", "type", "code", "error_subcode", "fbtrace_id"}}`; the client must also recognise the TA-2 format. The checks run in this order: 190 first, then rate limit, then 5xx, then other errors. Error 190 covers expired, revoked and invalid tokens. The access token travels in the query string, so exceptions that include the request URL must not reach logs unredacted (NFR-2). No endpoint or header reports the remaining `keyword_search` quota (D-8); the general Threads call limit (4,800 × impressions) is separate and not tracked.

## Module contracts

- MC-1 threads → scout-settings (Threads tab): `GET /settings/threads` passes the `connection` prop (RT-1), and the connect and disconnect routes return there with a flash toast. Trigger: page visit, end of OAuth, disconnect. On failure: AC-3, AC-4, AC-6, AC-7, AC-67. — source: S-1, S-5
- MC-2 threads → scout-settings (Scraping tab): `GET /settings/scraping` passes `keywords`, `searchSettings`, `searchBudget` and `ownPostsOnly` (RT-8); writes go through RT-9 to RT-12. Trigger: page visit, keyword or settings change. On failure: AC-35 to AC-37, AC-41, AC-44. — source: S-1, S-5, D-25
- MC-3 threads → posts: `GET /posts` and `GET /posts/{post}` pass the props in RT-13 and RT-14; `POST /search-runs` starts a manual run (RT-15). Trigger: page visit, search, filter, page change, "Run scout". On failure: AC-64, AC-72, AC-80, AC-87. — source: S-1, S-4, S-5, D-14, D-19, D-21
- MC-4 account → threads: deleting the Scout account (`DELETE /account/profile`, `profile.destroy`, `routes/account.php:16`) deletes the user's connection, keywords, found posts and search runs through foreign-key cascades. Trigger: account deletion. On failure: the whole deletion fails, so nothing is left behind. — source: S-5, S-10
- MC-5 Meta → threads: `POST /auth/threads/uninstall` and `POST /auth/threads/delete` with a form field `signed_request` = `base64url(HMAC-SHA256 signature) "." base64url(JSON payload {algorithm, issued_at, user_id, ...})`, signed with the Threads app secret. The delete response is `{url, confirmation_code}`. Trigger: the user removes the app in Threads or requests deletion. On failure: AC-20; unmatched id: AC-19, AC-22. — source: S-1, S-9, D-15
- MC-6 threads → legal (data-deletion page): the status URL `{APP_URL}/data-deletion?code={confirmation_code}` and the `deletionRequest` and `codeNotFound` props (RT-7). Trigger: a visit from Meta's status link. On failure: AC-25. — source: S-9, S-5
- MC-7 scheduler → threads: `routes/console.php` schedules `threads:refresh-tokens` and `threads:prune-posts` daily with `withoutOverlapping()` and `onOneServer()` (cache store `database`, which supports locks), and dispatches each user's scheduled search run every N minutes, where N is the user's interval. Trigger: the scheduler. On failure: AC-29, AC-31, AC-51. — source: S-1, D-1, D-4
- MC-8 threads → dashboard: `GET /dashboard` passes `threadsQuota`, `stats`, `activity`, `latestPosts` and `searchStatus` (RT-16); the "Relevant posts" card, the relevant series and the AI alert keep their mock data. Trigger: page visit. On failure: none (database reads only). — source: D-16, D-24
- MC-9 threads → future consumers (notifications, AI filtering): the `ThreadsPostsStored` event with `userId` and `postIds` (Data model, "Event"). Trigger: AC-78. On failure: none in this spec, since there are no listeners. — source: D-23
- MC-10 threads → every signed-in page (app layout): the shared `threadsConnection` prop drives the banner B-1. Trigger: every Inertia response for a signed-in user. On failure: none (database read). — source: D-5

## Inputs and provenance

Data inputs:

| Input                                                           | Origin                                                   | Trust                                     |
| --------------------------------------------------------------- | -------------------------------------------------------- | ----------------------------------------- |
| `code`, `state`, `error`, `error_reason`, `error_description`   | Threads OAuth redirect (browser query string)            | untrusted                                 |
| `signed_request`                                                | Meta server-to-server POST (uninstall, delete)           | untrusted until the signature is verified |
| Access tokens, `user_id`, `expires_in`                          | Threads API responses                                    | trusted after TLS; secret                 |
| `username`, `name`, post `text`, `permalink`, `timestamp`, `id` | Threads API responses (content written by third parties) | untrusted                                 |
| Threads error bodies                                            | Threads API responses                                    | untrusted                                 |
| Keyword text                                                    | user, Scraping tab                                       | untrusted                                 |
| Search interval, first-search lookback                          | user, Scraping tab                                       | untrusted                                 |
| `search`, `keyword`, `page`                                     | user, `/posts` query string                              | untrusted                                 |
| `{post}` route parameter                                        | user, `/posts/{post}`                                    | untrusted                                 |
| `code` (confirmation code)                                      | anyone, `/data-deletion` query string                    | untrusted                                 |
| `timezone` cookie                                               | browser, written by the frontend                         | untrusted                                 |
| App id, app secret, URLs, own-posts-only flag                   | `.env` → `config/services.php`                           | trusted, secret                           |

Requirement sources:

| ID   | Source                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ---- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| S-1  | Request of 2026-10-22: "Connecting Threads via OAuth … Meta callbacks … Token refresh: a scheduled command, NOT queued jobs … ThreadsClient … Keyword search … Rate limit: 2,200 queries per user per rolling 24 hours"                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| S-2  | `.ai/docs/plans/2026-10-22-1956-threads-api-preparation.md` §1, §6 "Results of the manual check (2026-10-22)" and §7 "Constraints to design around"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| S-3  | `.ai/docs/plans/2026-10-22-2009-threads-integration-concept.md` §4 "Limits in the UI", §5 "Connection state"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| S-4  | `design/Opportunity_Scout_Design_Canvas_UPDATED_v5.html` artboards: "Settings · Scraping" (l. 1775), "Settings · AI Filtering" (l. 1853), "Settings · Integrations / Telegram" (l. 1940), "Posts · filters + pagination" (l. 1277), "Post Detail" (l. 1625), "Posts · Empty" (l. 2030), "Posts · Loading" (l. 2078)                                                                                                                                                                                                                                                                                                                                                              |
| S-5  | Existing behaviour: `routes/web.php:14-35`, `routes/account.php:16`, `resources/js/components/settings/ThreadsConnectionCard.vue`, `resources/js/components/settings/ScrapingSettingsForm.vue:24-45`, `resources/js/components/TagInput.vue:22-24`, `resources/js/components/posts/PostsTable.vue:62,108`, `resources/js/composables/usePosts.ts:38`, `resources/js/data/settings.ts`, `resources/js/pages/Dashboard.vue`, `resources/js/pages/PostDetail.vue`, `resources/js/pages/legal/DataDeletion.vue`, `app/Http/Controllers/Settings/ProfileController.php:41`, `config/session.php:202`, `bootstrap/app.php` (`encryptCookies(except: ['appearance', 'sidebar_state'])`) |
| S-6  | Threads API, Keyword Search: https://developers.facebook.com/docs/threads/keyword-search/ (fetched 2026-10-22): parameters, `limit` default 25 / max 100, `since` ≥ 1688540400, 2,200 queries per rolling 24 h shared across apps, empty-result queries not counted, sensitive keywords return an empty array, no pagination mentioned                                                                                                                                                                                                                                                                                                                                           |
| S-7  | Threads API, Get Access Tokens and Permissions: https://developers.facebook.com/docs/threads/get-started/get-access-tokens-and-permissions (fetched 2026-10-22): authorize parameters, `state`, denial parameters, code valid 1 h and single use, code exchange and its error format                                                                                                                                                                                                                                                                                                                                                                                             |
| S-8  | Threads API, Long-Lived Tokens: https://developers.facebook.com/docs/threads/get-started/long-lived-tokens (fetched 2026-10-22): exchange and refresh endpoints, 60-day validity, refresh needs ≥ 24 h age and an unexpired token, private-profile permissions valid 90 days                                                                                                                                                                                                                                                                                                                                                                                                     |
| S-9  | Meta, Data Deletion Callback: https://developers.facebook.com/documentation/development/create-an-app/app-dashboard/data-deletion-callback (fetched 2026-10-22): POST `signed_request`, base64url + HMAC-SHA256 with the app secret, payload `user_id`, response `{url, confirmation_code}`, human-readable status. Facebook-focused; Threads specifics unverified (D-15, V-2)                                                                                                                                                                                                                                                                                                   |
| S-10 | `resources/js/pages/legal/DataDeletion.vue`: "This permanently removes your account, Threads connection, collected posts and settings."                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| S-11 | RFC 6749 §10.12 (CSRF protection with a non-guessable `state` bound to the user agent): https://datatracker.ietf.org/doc/html/rfc6749#section-10.12                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| S-12 | The user's answers to Q-1 to Q-15, DG-1 to DG-16, UX-1 to UX-8 and MI-1 to MI-5, relayed by the coordinator on 2026-10-22, recorded as D-1 to D-25; and the answers to Q-16, Q-17 and UX-9, relayed on 2026-10-23, recorded as D-26 to D-28                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| S-13 | `researcher` report on Q-8, relayed 2026-10-22 (no URLs relayed): the limit-exceeded error isn't documented; no quota endpoint or header exists for `keyword_search`; `threads_publishing_limit` covers publishing only; the general call limit is 4,800 × impressions                                                                                                                                                                                                                                                                                                                                                                                                           |
| S-14 | `researcher` report on Q-15, relayed 2026-10-22 (no URLs relayed): no Threads doc describes the uninstall or delete callbacks; the generic doc says `user_id` is an "app-scoped ID"; probably equal to the `/me` id, unverified                                                                                                                                                                                                                                                                                                                                                                                                                                                  |

## Untrusted inputs

- UI-1 OAuth callback `error`, `error_reason`, `error_description` — risk: reflected XSS, phishing text injected into the UI — IF the callback contains any of these parameters, THEN the system shall show only its own fixed message (T-2) and never display the parameter values.
- UI-2 `signed_request` body — risk: spoofed origin, forged payload, oversized body, unsupported algorithm — IF the `signed_request` is longer than 8,192 characters, THEN the system shall reject it with HTTP 400 before decoding it.
- UI-3 Keyword text — risk: stored XSS, control characters, oversized values sent as `q` to Threads — IF a keyword contains control characters or exceeds 100 characters, THEN the system shall reject it with a validation message (AC-35).
- UI-4 Post `text`, `username` and `name` from Threads — risk: stored XSS, misleading markup, prompt injection when a future AI filter reads the text (MC-9) — IF this text contains HTML or script markup, THEN the system shall display it as literal text.
- UI-5 Post `permalink` from Threads — risk: `javascript:` URLs, links to other sites — IF a permalink is not an `https` URL on `threads.com`, `www.threads.com`, `threads.net` or `www.threads.net`, THEN the system shall store the post without a permalink and show no "Open in Threads" link for it.
- UI-6 Threads API response items — risk: missing or malformed fields, unexpected types — IF a returned item lacks `id` or a parseable `timestamp`, THEN the system shall skip that item and log a warning with the keyword id.
- UI-7 Confirmation `code` on `/data-deletion` — risk: enumeration, injection — IF the code is not 1 to 32 alphanumeric characters, THEN the system shall show the message N-9 without querying the database.
- UI-8 `search` on `/posts` — risk: oversized input, SQL `LIKE` wildcard abuse — IF the search term contains `%` or `_`, THEN the system shall match those characters literally.
- UI-9 Search interval and lookback values — risk: values outside the allowed sets that break the budget — IF `interval_minutes` is not 5, 15, 30 or 60, or `first_search_lookback_days` is not 0, 1 or 3, THEN the system shall reject the request with HTTP 422.
- UI-10 `keyword` filter on `/posts` — risk: oversized input, injection — IF the `keyword` filter is longer than 100 characters, THEN the system shall reject the request with HTTP 422.
- UI-11 Threads error bodies — risk: secrets or huge payloads written to logs — IF an error body is logged (AC-74), THEN the system shall remove any access token from it before writing it.
- UI-12 `timezone` cookie — risk: forged values, oversized input, exceptions from an unknown zone — IF the cookie value is longer than 64 characters or is not in the IANA time zone identifier list, THEN the system shall ignore it and use UTC without raising an error.

## Verification notes

Manual checks to run during implementation; they do not block approval.

- V-1 Rate-limit error: when a tester account reaches the limit, record the real HTTP status, error code, subcode and message, and narrow AC-58 if it differs (D-8).
- V-2 Callback `user_id`: remove the app with the tester account (`@elrondhalfelven`) and log the decoded `signed_request` payload (no signature, no token) once, to confirm that `user_id` equals the `/me` id (`29580006461583503`) (D-15).
- V-3 Token refresh: the refresh endpoint hasn't been checked by hand yet (S-2 §6). Check it once a test token is at least 24 hours old.

## Design review

Design gaps:

- DG-1 Settings tabs (all three Settings artboards) — the design has no Threads tab and no connection screen; the code has a "Threads" tab with `ThreadsConnectionCard` — proposed: use the code's tab and card as the baseline — [accepted → AC-10, AC-15, AC-94, AC-102]
- DG-2 Threads tab — missing states: disconnected by the system with a reason, redirecting, OAuth failure toasts, disconnect confirmation — [accepted → AC-3, AC-4, AC-6, AC-7, AC-15, AC-94, AC-102]
- DG-3 Threads tab — "Name" row not in the stored fields — proposed: store `name` from `/me`, nullable — [accepted → AC-5, AC-10, AC-13]
- DG-4 Settings · Scraping — keywords as a comma-separated field versus per-keyword CRUD — proposed: tag input that saves each add, rename and delete at once, inline errors, "N / max" counter — [accepted → AC-34 to AC-37, AC-39 to AC-41, AC-106]
- DG-5 Settings · Scraping — no quota usage indicator or over-budget state — [accepted → AC-42, AC-96]
- DG-6 Settings · Scraping — no per-keyword status — [accepted → AC-95]
- DG-7 Settings · Scraping — "Posts per run" has no counterpart — proposed: remove — [accepted → AC-100]
- DG-8 Settings · Scraping — "Regions / languages" has no counterpart — proposed: remove and show the language hint — [accepted → AC-100, AC-89]
- DG-9 Settings · Scraping — no state while Threads is not connected — proposed: a notice; keywords can still be added and search starts after connecting — [accepted → AC-43, AC-69]
- DG-10 Posts · filters + pagination — AI columns, filter, sort and copy without data — proposed: hide them; show excerpt, @username, keyword, post and found time — [accepted → AC-62, AC-82]
- DG-11 Posts · filters + pagination — filters and page not in the URL — proposed: keep `search`, `keyword` (new filter) and `page` in the URL — [accepted → AC-63, AC-83, AC-97]
- DG-12 Posts · Empty — only one empty state — proposed: three variants plus "no results for this filter", all copy in English — [accepted → AC-65, AC-85, AC-86, AC-87]
- DG-13 Posts list — no search status — [accepted → AC-88]
- DG-14 Posts · Loading — loading applies only to search, filter and page changes — [accepted → AC-98]
- DG-15 Data-deletion page — no status view; outdated "Settings → Profile" step — [accepted → AC-24, AC-25, AC-99]
- DG-16 "Run scout" is a mock — proposed: see UX-3 — [accepted → AC-71, AC-72]

UX proposals:

- UX-1 Hint that Threads search doesn't translate keywords — [accepted → AC-89]
- UX-2 Limited-mode notice before App Review, behind a config flag — [accepted → AC-90, NFR-3]
- UX-3 "Run scout" queues a real run: at most one per 15 minutes, counted in the budget, blocked when the budget left is too small — [accepted → AC-71, AC-72]
- UX-4 Tell the user how to remove the app in Threads after disconnecting — [accepted → AC-91]
- UX-5 Sensitive-keyword hint after 7 days of zero results — [accepted → AC-92]
- UX-6 Expiry warning when the token expires within 3 days — [accepted → AC-93]
- UX-7 Deletion status records keep no Threads user id or username — [accepted → NFR-11]
- UX-8 Throttle limits: connect and callback 10 per minute per user, Meta callbacks 60 per minute per IP, keyword writes 30 per minute per user — [accepted → NFR-12]
- UX-9 Live run progress: while a run is queued or running, the Dashboard and the Posts page reload `searchStatus` and the posts list every 10 seconds, and show the toast "Search finished: {n} new posts." when the run ends — benefit: after "Run scout", the user sees results without reloading — cost: periodic requests while a run is active — [accepted → AC-111, AC-112, AC-113]

Module interactions to confirm:

- MI-1 Post detail with real posts — [accepted → AC-79, AC-80, AC-81, RT-14]
- MI-2 Posts stored unfiltered; no AI columns now — [accepted → AC-52, Data model]
- MI-3 No notifications; the `ThreadsPostsStored` event is dispatched — [accepted → AC-78, AC-104, MC-9]
- MI-4 Dashboard: quota widget, real latest posts and found counts; AI stats stay mock — [accepted → AC-75, AC-76, AC-77, AC-105, MC-8]
- MI-5 This spec owns the interval and lookback settings — [accepted → AC-70, RT-12, `users` columns]

## Open questions

- Q-1 How often does search run, and can the user choose? — [resolved → AC-37, AC-44, AC-45, AC-70] (D-1)
- Q-2 What are the keyword limits per user? — [resolved → AC-35, AC-37] (D-3)
- Q-3 What daily query budget do we plan with? — [resolved → AC-37, AC-42, AC-59, AC-72, AC-75] (D-2)
- Q-4 How long are found posts kept? — [resolved → AC-66] (D-4)
- Q-5 Where does the user see that the connection is disconnected? — [resolved → AC-15, AC-68] (D-5)
- Q-6 How far back does a new keyword's first search go? — [resolved → AC-49, AC-70] (D-6)
- Q-7 Should search windows overlap? — [resolved → AC-50] (D-7)
- Q-8 What is the rate-limit error, and how long do we pause? — [resolved → AC-58, AC-73, AC-74, AC-75, AC-76] (D-8, D-16)
- Q-9 Do we follow paging cursors? — [resolved → AC-48] (D-9)
- Q-10 Can one Threads account be connected to two Scout users? — [resolved → AC-67] (D-10)
- Q-11 What happens on reconnecting with a different Threads account? — [resolved → AC-13] (D-11)
- Q-12 What does the delete callback remove? — [resolved → AC-21] (D-12)
- Q-13 What happens to posts when their keyword is deleted? — [resolved → AC-103] (D-13)
- Q-14 Where do found posts appear? — [resolved → AC-61, AC-62, AC-82] (D-14)
- Q-15 Is the callback `user_id` the `/me` id? — [resolved → AC-18, AC-19, AC-21, AC-22] (D-15, V-2)
- Q-16 What happens to the "Mark as relevant" toggle (Posts rows, post detail) and the "Suggested response" block (post detail) for real posts? Neither is an AI block: the toggle is user-driven (`types/threads.ts` comment), and the response comes from the template, which is out of scope. Nothing in this spec stores either. — options: A) hide both until a later spec / B) keep the toggle as a stored per-post flag (adds a column and a route) and hide the suggested response / C) keep both as client-only mocks — why it matters: a toggle that forgets its state on reload misleads the user; B changes the data model and routes — [resolved → AC-110] (D-26)
- Q-17 Which time zone sets the day boundary for "Posts found today" and the per-day series on the Dashboard? The app has no per-user time zone, and storage is UTC. — options: A) UTC / B) the browser's time zone, sent with the request / C) a time zone set per user in the account settings — why it matters: the counts change around midnight; C adds a setting — [resolved → AC-77, AC-107, AC-108, AC-109] (D-27)

## Changelog

- 2026-10-22 23:08 — created (draft)
- 2026-10-22 23:57 — revised with the user's answers (D-1 to D-25): resolved Q-1 to Q-15; accepted DG-1 to DG-16, UX-1 to UX-8 and MI-1 to MI-5. Added: decisions record, UI states with English copy, Dashboard quota widget and props, manual run, post detail, `ThreadsPostsStored` event, `threads:prune-posts`, search settings on `users`, verification notes V-1 to V-3. Recomputed the query budget with 2,000. Removed AC-38 (merged into AC-37). New: Q-16, Q-17, UX-9. Status stays draft.
- 2026-10-23 00:10 — revised with the user's answers D-26 to D-28: Q-16 resolved (A: hide "Mark as relevant" and "Suggested response" → AC-110); Q-17 resolved (B: browser time zone through the `timezone` cookie, UTC fallback → AC-77, AC-107 to AC-109, UI-12, EC-47 to EC-49); UX-9 accepted (10-second Inertia partial-reload polling, toast T-11 → AC-111 to AC-113, EC-50, EC-51; `searchStatus` gains `lastRunId` and `lastRunPostsStored`). Nothing open or pending. Status stays draft until the user approves.
- 2026-10-23 — approved by the user
