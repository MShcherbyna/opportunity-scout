# Preparation Plan: Threads API connection and keyword search

- Created: 2026-10-22 19:56
- Status: in progress (sections 3–6 done 2026-10-22, except the items left open)
- Request: List the steps to do before we implement the Threads API connection and fetching posts by keyword: Meta app setup, permissions, tokens, review.

## 1. Goal and key constraint

Get everything ready to start coding the Threads integration: a working Meta app, a test account, a confirmed OAuth flow and a confirmed `keyword_search` call.

Until Meta approves `threads_keyword_search` in App Review, keyword search returns **only the authenticated user's own posts**. Searching public posts needs approval, and the review needs a screencast of the working OAuth flow and search UI. So the OAuth flow and search are built first, tested against our own posts, and submitted for review afterwards.

## 2. Accounts

- [x] A Threads account for development and testing (a separate one is better). It must be public.
- [x] A Meta for Developers account at [developers.facebook.com](https://developers.facebook.com), registered as a developer with phone and email confirmed.
- [ ] A Business Portfolio (Meta Business Manager). Business Verification needs it, and Advanced Access needs Business Verification.

## 3. Create the Meta app

- [x] My Apps → **Create App** → use case **"Access the Threads API"**.
- [x] Use cases → Customize. Enable only these permissions:
    - `threads_basic` (required);
    - `threads_keyword_search` (search).

    Requesting permissions the app doesn't need is a common reason for rejection.

- [x] Settings → Basic: copy the **Threads App ID** and **Threads App Secret**. They are separate from the main Facebook App ID.
- [ ] Fill in the **Privacy Policy URL** and **Terms of Service URL**, and set an icon and a category.
- [x] Add these callback URLs:
    - **Redirect Callback URL**, e.g. `https://<domain>/auth/threads/callback`;
    - **Uninstall Callback URL**;
    - **Delete Callback URL**.

## 4. HTTPS for local development

Meta accepts only HTTPS redirect URIs, and the local stack runs on `http://localhost:8091`.

- [x] Pick one:
    - a tunnel (`ngrok` or `cloudflared`) to port 8091;
    - a staging deployment with HTTPS.
- [x] Make `APP_URL` in `.env` match the redirect URL configured in the app.

## 5. Testers

- [x] App Roles → Roles → **Add Threads Tester** → enter the Threads username.
- [x] Accept the invite in the Threads app: Settings → Account → **Website permissions → Invites**.

OAuth does not work for test accounts until the invite is accepted.

## 6. Check the flow by hand before coding

Use Postman or the Graph API Explorer.

- [x] Authorize: `https://threads.net/oauth/authorize?client_id=...&redirect_uri=...&scope=threads_basic,threads_keyword_search&response_type=code`.
- [x] Exchange the `code` for a short-lived token (1 hour): `POST https://graph.threads.net/oauth/access_token`.
- [x] Exchange it for a long-lived token (60 days): `GET https://graph.threads.net/access_token?grant_type=th_exchange_token`.
- [ ] Refresh a long-lived token (not checked yet: a token can be refreshed only once it is at least 24 hours old): `GET https://graph.threads.net/refresh_access_token?grant_type=th_refresh_token`.
- [x] Test search: `GET https://graph.threads.net/v1.0/keyword_search?q=...&search_type=RECENT&fields=id,text,permalink,timestamp,username`.

### Results of the manual check (2026-10-22)

- Tunnel: ngrok to port 8091, `APP_URL` set to the ngrok URL. The free ngrok URL changes on every restart; then update it in Meta and `.env`.
- Tester `@elrondhalfelven` (user id `29580006461583503`). The short-lived and long-lived tokens (`expires_in` 5184000 = 60 days) and `GET /me` work.
- `keyword_search` before App Review returns only the tester's own posts. Tested with five own posts:
    - Cyrillic works.
    - Matching is fuzzy: `фрілансер` finds `фрілансера`; `розробка` and `#розробка` both find a post that has the word only as a hashtag. Expect loose matches and filter on our side.
    - No translation: `freelancer` doesn't find `фрілансера`. Keywords are needed per language.
    - Mid-text words match (`Laravel`). An unrelated control post didn't show up in other searches.
    - `RECENT` and `TOP` returned the same results.

## 7. Constraints to design around

- **Rate limit: 2,200 queries per user per rolling 24 hours**, shared across all apps. This caps fetch frequency × number of keywords. Queries that return no results don't count toward it.
- Keywords Meta treats as sensitive or offensive silently return an empty array.
- Store tokens encrypted (`encrypted` cast). Refresh them with a scheduled job before the 60 days run out.
- Call the API from queued jobs, not inside HTTP requests.
- Narrow searches with `since`/`until` so the same posts aren't fetched again, and deduplicate by post `id`.
- Meta allows `threads_keyword_search` only in business apps that help users manage their social media presence. **It is prohibited in consumer apps.** Describe the use case as a business tool: monitoring opportunities and requests for freelancers or agencies.

## 8. App Review (Advanced Access)

- [ ] Complete **Business Verification**.
- [ ] Submit `threads_keyword_search` for review with a use case description and a **screencast** showing: Threads OAuth login → account linking → keyword search for public posts in our UI.

## 9. Order of work

1. Sections 2–6.
2. Implementation: OAuth connection of a Threads account, token storage and refresh, a `ThreadsClient`, a search job, storing posts. Test against our own posts until approval.
3. Record the screencast → App Review.
4. After approval, search covers all public posts.

Next: write a specification for the Threads connection and keyword search (`spec-creator`), then an implementation plan.

## Sources

- [Keyword Search – Threads API](https://developers.facebook.com/docs/threads/keyword-search/)
- [Permissions Reference](https://developers.facebook.com/docs/permissions)
- [Threads API overview](https://developers.facebook.com/documentation/threads/overview)
- [Create an App with Meta](https://developers.facebook.com/documentation/development/create-an-app)
