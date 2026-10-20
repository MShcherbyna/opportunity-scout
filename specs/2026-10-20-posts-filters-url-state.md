# Spec: Persist posts list filter state in the URL

Spec ID: SPEC-2026-10-20-posts-filters-url-state
Status: draft
Supersedes: none

## Problem and user

The user of the Threads scout app reviews collected posts on the posts page
(`/posts`, route `posts.index`). The status filter, sort, search text and
current page live only in the page's in-memory state today, so:

- "Back to posts" on the post detail page returns to an unfiltered page 1;
- reloading `/posts` resets every filter;
- a filtered list cannot be shared or bookmarked as a link.

Current state (read from the code, 2026-10-20):

- The posts list state (status `all | relevant | maybe | rejected`, sort
  `newest | score`, search text, page) is held by the shared posts-list
  composable. Changing status, sort or search resets the page to 1; setting a
  page clamps it to `[1, totalPages]`, with `totalPages >= 1`.
- Data is mocked (128 posts) and filtered client-side, synchronously;
  `totalPages` is known immediately at initialisation. The loading indicator
  is a simulated 350 ms delay only.
- The search input emits a change on every keystroke (no debounce).
- The Dashboard uses the same composable (5 posts, status `relevant`) and must
  keep its own URL untouched.
- The post detail page has two "Back to posts" links (header link and the
  "Post not found" empty state); both point to plain `/posts`. Dashboard
  "View all" also points to plain `/posts`.
- Backend is out of scope: `posts.index` is a plain Inertia route that
  ignores the query string.

## Goals / Non-goals

Goals:

- The posts list state is reflected in, and restorable from, the `/posts`
  query string (`status`, `sort`, `q`, `page`).
- URL sync is an explicit opt-in of the composable; only the posts page
  enables it.
- "Back to posts" returns to the last-used list state in the same tab.
- Malformed or stale parameters never break the page.

Non-goals:

- Any backend change (server-side filtering, validation, route changes).
- Restoring filters from storage on a plain `/posts` load (decided: the URL
  is the only source on load).
- Persisting filters across tabs or browser sessions (no localStorage,
  cookies or user settings).
- Visual changes; the UI looks exactly as today.
- Changing Dashboard "View all" or sidebar navigation targets.
- Adding history entries per filter change (filter changes replace the
  current entry).

## User stories

- US-1: As a user on the Dashboard, I want its URL to stay unchanged, so
  that the posts preview widget never alters the address bar.
- US-2: As a user, I want reloading `/posts` to keep my filters, sort,
  search and page, so that I don't lose my place.
- US-3: As a user, I want the address bar to show a clean link of my current
  list state, so that I can share or bookmark a filtered list.
- US-4: As a user opening an edited, outdated or malformed link, I want the
  page to load with sensible defaults instead of failing, so that bad links
  are harmless.
- US-5: As a user reading a post, I want "Back to posts" to return me to the
  list exactly as I left it, so that I can continue reviewing.
- US-6: As a user, I want the browser Back and Forward buttons to keep
  working between the list and a post, so that navigation behaves as
  expected.

## Acceptance criteria (EARS)

Decisions marked **Proposed (not confirmed by user)** follow the
specification agent's recommendation and may be overridden; see
`## Open questions`.

- AC-1: WHERE URL sync is not enabled on the posts-list composable (the
  default), the composable (shall) neither read nor write the URL, browser
  history or sessionStorage.
  Traces: US-1
  Verify: manual - open `/dashboard`, wait for the posts widget to load: the
  address bar stays `/dashboard`, `history.length` unchanged, no new
  sessionStorage key.

- AC-2: WHEN the posts page initialises with URL sync enabled, the list
  (shall) take its initial status, sort, search text and page from the
  `status`, `sort`, `q` and `page` query parameters of the current URL, after
  applying AC-3 to AC-7.
  Traces: US-2, US-3
  Verify: e2e/manual - open `/posts?status=maybe&sort=score&q=saas&page=2`:
  status select shows "Maybe", sort shows "Highest score", search box shows
  `saas`, pagination highlights page 2 (or the clamped page per AC-5).

- AC-3: IF the `status` or `sort` parameter is absent, empty or not exactly
  one of its allowed values (status `all | relevant | maybe | rejected`; sort
  `newest | score`; case-sensitive), THEN the list (shall) use the default
  (`all`, `newest`) for that parameter.
  Traces: US-4
  Verify: manual - `/posts?status=garbage&sort=Score` shows "All relevance"
  and "Newest first", no console error.

- AC-4: IF the `page` parameter is not a string of ASCII digits only
  representing an integer >= 1, THEN the list (shall) use page 1; a
  digits-only value with leading zeros (e.g. `02`) (shall) be read as its
  integer value. **Proposed (not confirmed by user)** for the digits-only
  rule.
  Traces: US-4
  Verify: unit/manual - `page` values `-3`, `0`, `abc`, `1.5`, `2.0`, `+2`,
  `1e3`, empty all yield page 1; `02` yields page 2.

- AC-5: IF the requested page (from the URL at initialisation) is greater
  than the number of pages for the resulting filters, THEN the list (shall)
  show the last page, using the same clamping rule as an in-page page change
  (`[1, totalPages]`).
  Traces: US-4, US-5
  Verify: manual - `/posts?status=maybe&page=999` shows the last page of
  "Maybe" posts and the URL `page` equals that page number (or is omitted
  when it is 1).

- AC-6: IF a managed parameter (`status`, `sort`, `q`, `page`) appears more
  than once in the URL, THEN the list (shall) use its first occurrence.
  **Proposed (not confirmed by user).**
  Traces: US-4
  Verify: manual - `/posts?status=maybe&status=rejected` shows "Maybe"; after
  AC-8 normalisation the URL contains `status=maybe` once.

- AC-7: IF the `q` parameter is longer than 200 characters, THEN the list
  (shall) use only its first 200 characters as the search text.
  **Proposed (not confirmed by user).**
  Traces: US-4
  Verify: manual - open `/posts?q=` + 300 characters: the search box holds
  200 characters and the URL `q` is 200 characters long.

- AC-8: WHEN the posts page has initialised with URL sync enabled, the posts
  page (shall) replace the current URL with its normalised form: managed
  parameters reduced to their effective values per AC-3 to AC-7 and AC-10,
  each at most once, in the order `status`, `sort`, `q`, `page`, without
  adding a history entry; query parameters it does not manage and the URL
  hash (shall) be kept unchanged (**Proposed (not confirmed by user)** for
  keeping unrelated parameters and hash).
  Traces: US-3, US-4
  Verify: manual - `/posts?status=garbage&page=-3` becomes `/posts`;
  `/posts?utm=x&status=maybe#top` becomes `/posts?utm=x&status=maybe#top`;
  `history.length` unchanged.

- AC-9: WHEN the status, sort or page of the list changes on the posts page,
  the posts page (shall) update the URL query to the new normalised state by
  replacing the current history entry, with no request to the server, no
  change to the page props, no scroll reset, and with Inertia's recorded
  page URL equal to the address-bar URL afterwards.
  Traces: US-2, US-3, US-6
  Verify: e2e/manual - change status, sort and page: the network panel shows
  no request, `history.length` is unchanged, the scroll position is kept,
  and Inertia's current page URL (`usePage().url`) equals
  `location.pathname + location.search`.

- AC-10: The URL query written by the posts page (shall) omit every
  parameter at its default value: `status=all`, `sort=newest`, a search text
  that is empty or whitespace-only, and `page=1`; a non-empty search text
  (shall) be written exactly as typed (no trimming). **Proposed (not
  confirmed by user)** for the whitespace-only and as-typed rules.
  Traces: US-3
  Verify: manual - with all filters at defaults the URL is exactly `/posts`;
  status Maybe + sort score + search ` saas` + page 2 gives
  `/posts?status=maybe&sort=score&q=+saas&page=2` (or `%20saas`).

- AC-11: WHILE the user is typing in the search box, the posts page (shall)
  filter the list on every keystroke as today but write the URL and
  sessionStorage only after 300 ms without a further search change.
  **Proposed (not confirmed by user).**
  Traces: US-3
  Verify: manual - type `saas` quickly: the list updates per keystroke, the
  URL changes once, about 300 ms after the last key.

- AC-12: WHEN the user leaves the posts page while a debounced search write
  is pending, the posts page (shall) perform that write before leaving, so
  the URL entry and the stored query hold the latest search text.
  **Proposed (not confirmed by user).**
  Traces: US-5, US-6
  Verify: manual - type `saas` and click a post within 300 ms; "Back to
  posts" and browser Back both return to a list with `q=saas`.

- AC-13: WHEN a filter change (status, sort or search) resets the page to 1,
  the URL written for that change (shall) omit `page`.
  Traces: US-3
  Verify: manual - on page 3, change sort: the URL loses `page` and shows
  `sort=score`.

- AC-14: WHEN the posts page writes the list query to the URL (including
  initial normalisation and an empty query), the posts page (shall) also
  store that managed query as the last list query in the tab's
  sessionStorage, replacing any earlier value.
  Traces: US-5
  Verify: manual - after filtering, sessionStorage holds the same managed
  query as the URL; after resetting filters it holds an empty query.

- AC-15: WHEN the post detail page renders a "Back to posts" link (the
  header link and the "Post not found" link), the link (shall) target
  `/posts` with the stored last list query, built through the generated
  route helper for `posts.index`, keeping only managed parameters that pass
  AC-3, AC-4, AC-6, AC-7 and AC-10; with no stored query it (shall) target
  `/posts`. **Proposed (not confirmed by user)** for including the "Post not
  found" link.
  Traces: US-5
  Verify: manual - filter, open a post, hover/click "Back to posts": it opens
  `/posts?status=maybe&sort=score&q=saas&page=2` with that state shown; open
  `/posts/99999` and the empty-state link does the same.

- AC-16: IF sessionStorage is unavailable or any read or write to it fails
  (disabled storage, quota exceeded, security error, malformed value), THEN
  the posts page and the post detail page (shall) keep working with no
  uncaught error, the list (shall) still sync with the URL, and "Back to
  posts" (shall) target plain `/posts`.
  Traces: US-5
  Verify: unit/manual - with sessionStorage access throwing (e.g. stubbed),
  filtering updates the URL, no console error, back link is `/posts`.

- AC-17: WHEN the posts page loads from a URL with no managed parameters
  (e.g. via Dashboard "View all" or the sidebar), the list (shall) show the
  defaults regardless of any stored last list query.
  Traces: US-2, US-4
  Verify: manual - filter, open Dashboard, click "View all": the list shows
  defaults and the URL is `/posts`.

- AC-18: WHEN the user presses the browser Back button on a post detail page
  opened from the posts list, the posts page (shall) show the list state
  encoded in the URL of the restored history entry, and Forward (shall)
  return to the same post.
  Traces: US-6
  Verify: e2e/manual - filter to page 2, open a post, Back: URL and list show
  the page-2 filtered state; Forward reopens the post.

## Edge cases

- `status=garbage&page=-3` together: both fall back, URL becomes `/posts`
  (AC-3, AC-4, AC-8).
- Case variants such as `status=Relevant` are unknown values, so default
  (AC-3).
- Duplicate keys, including an empty first value (`status=&status=maybe`):
  first occurrence wins, it is empty, so default (AC-6, AC-3).
- Very large `page` (`99999999999999999999`): digits-only, then clamped to
  the last page (AC-4, AC-5).
- Page clamping before data is known: with mock data the total is known at
  initialisation; if a future data source loads asynchronously, clamping and
  the normalised URL write happen once the total is known, never against an
  unknown total (AC-5, AC-8).
- Stale stored query pointing past the last page (posts changed in the
  meantime): the back link carries it, the posts page clamps it on load
  (AC-15, AC-5).
- Tampered or corrupted sessionStorage value: invalid parameters are dropped
  when building the link; a value that cannot be parsed at all is treated
  as missing (AC-15, AC-16).
- Search change while on page 3: page resets to 1 and `page` leaves the URL
  (AC-13), with the write debounced (AC-11).
- Whitespace-only search `"   "`: no `q` in the URL (AC-10).
- Search containing `&`, `#`, `+`, `%` or non-Latin text: written URL-encoded
  and read back unchanged (AC-2, AC-10).
- Hash-only navigation (`#top`) on the posts page: the query is kept,
  because Inertia's recorded URL matches the address bar (AC-9, AC-8).
- Leaving the page within 300 ms of typing (AC-12).
- Two tabs with different filters: each tab's back link uses its own
  last query (sessionStorage is per tab) (AC-14, AC-15).
- Direct visit to `/posts/{id}` in a fresh tab: no stored query, so the back
  link is `/posts` (AC-15).
- Filter changes never add history entries, so Back from `/posts?...` goes to
  the page visited before the posts page (AC-9).
- The Dashboard and posts page mounted one after another in the same tab: the
  Dashboard neither reads nor overwrites the stored query (AC-1).

## Non-functional requirements

- Performance: the search-driven URL write runs at most once per 300 ms
  pause; no filter interaction triggers a server request (0 requests in the
  network panel) (AC-9, AC-11).
- History hygiene: `history.length` does not grow while changing filters,
  sort, search or page on `/posts` (AC-9).
- Reliability: no uncaught exception in the console for any query-string
  value or sessionStorage state covered in `## Edge cases`.
- Accessibility: no change in focus, labels or keyboard behaviour; typing in
  the search box does not lose focus or caret position when the URL is
  updated (verify manually).
- Quality gates: `npm run types:check` and `npm run check` finish with 0
  errors.

Implementation constraints (from the request; for the planner and
implementer):

- URL sync is an opt-in composable option (e.g. `syncWithUrl?: boolean`,
  default `false`), enabled only on the posts page; the Dashboard must not
  enable it.
- The public `UsePostsReturn` contract stays unchanged; the filters, table
  and pagination components need no rewrite.
- URL updates use Inertia's client-side replace (user decision, Q1): a
  replace with state and scroll preserved, no server request, props
  unchanged. Before writing it, confirm the exact Inertia v3 API with
  `search-docs` (package: inertia).
- The back link is built with the Wayfinder helper for `posts.index`
  (`postsRoutes.index({ query: ... })`), never by concatenating URL strings.
- Every sessionStorage read and write is guarded so failures are swallowed
  (AC-16).
- Debouncing may use the already-installed `@vueuse/core` (user decision,
  Q6); no new dependencies.
- Activate the `inertia-vue-development` and `wayfinder-development` skills.
- Follow the style of the existing composable: types via `export type`, a
  JSDoc block above the function, no unnecessary inline comments.
- If a non-obvious trap is hit (e.g. Inertia history state), record it in
  `.ai/insights/frontend.md` with the `engineering-insights` skill.

## Inputs and provenance

| Input                         | Source                                                                 | Consumer                             | Failure at boundary                                                                  |
| ----------------------------- | ---------------------------------------------------------------------- | ------------------------------------ | ------------------------------------------------------------------------------------ |
| `status`, `sort`, `q`, `page` | address bar query string (typed, shared link, reload, history restore) | posts page list state                | invalid/missing/duplicate values fall back per AC-3 to AC-7                          |
| list state changes            | filters, sort, search box, pagination on the posts page                | URL (replace) + sessionStorage       | storage failure ignored (AC-16); URL write never issues a server request (AC-9)      |
| last list query               | tab's sessionStorage, written by the posts page                        | "Back to posts" links on post detail | missing/unreadable/tampered: plain `/posts` or invalid params dropped (AC-15, AC-16) |
| Inertia history state         | Inertia's own history entries                                          | browser Back/Forward                 | entry URL holds the list query; posts page re-reads it on restore (AC-18)            |

Decisions and sources:

- User decisions (2026-10-20, via coordinator): Q1 Inertia client-side
  replace; Q2 normalise URL on load (AC-8); Q6 `@vueuse/core` allowed
  (already in `package.json`, `^12.8.2`); Q8 URL is the only source on
  load, sessionStorage feeds only the back link (AC-17).
- Inertia behaviour (from `@inertiajs/core` 3.7.1 package source read on
  2026-10-20): a raw `history.replaceState` leaves Inertia's recorded page
  URL stale (`/posts`), and Inertia rewrites the address bar to that stale
  URL on remember-state writes and on popstate with empty state (hash-only
  navigation). Its client-side replace visit makes no request, keeps
  props, and keeps the recorded URL in sync. Scroll-position saves keep the
  current URL. To be reconfirmed with `search-docs` at implementation.
- Wayfinder route helper (generated): drops `undefined`/`null` query values,
  so omitted defaults produce `/posts` with no `?`.
- Design: `design/Opportunity_Scout_Design_Canvas_UPDATED_v5.html` shows only
  the "Back to posts" label; no visual change is in scope.
- Insights: `.ai/insights/frontend.md` has no entry on URL or history state;
  the Wayfinder entry notes generated route helpers are rebuilt on dev/build.

```mermaid
sequenceDiagram
    participant U as User
    participant P as Posts page
    participant H as URL / Inertia history
    participant S as sessionStorage (tab)
    participant D as Post detail page

    U->>P: open /posts?status=maybe&page=-3
    P->>H: read query
    P->>P: validate (AC-3..AC-7), clamp page (AC-5)
    P->>H: replace with normalised URL (AC-8)
    P-->>S: store last list query (AC-14, errors ignored)
    U->>P: change sort / page / search
    P->>H: replace URL, no server request (AC-9; search debounced AC-11)
    P-->>S: store last list query
    U->>D: open a post (pending write flushed, AC-12)
    D-->>S: read last list query (errors -> none, AC-16)
    D->>D: build back link via posts.index helper (AC-15)
    alt click "Back to posts"
        U->>P: /posts?<stored query>
    else browser Back
        H->>P: restore entry URL (AC-18)
    end
    P->>H: read query, validate, clamp
```

## Untrusted inputs

- URL query parameters (`status`, `sort`, `q`, `page`) are attacker-
  controllable via shared links. They are treated as data only: `status` and
  `sort` are accepted only from fixed allowlists; `page` only as a
  digits-only integer and then clamped; `q` is cut to 200 characters, shown
  only as text (never rendered as HTML) and matched as a plain substring
  (never compiled into a pattern or evaluated). Unmanaged parameters are
  passed through untouched and never read.
- The stored last list query in sessionStorage may be stale, corrupted or
  edited by the user. It is re-validated with the same rules before being
  used in the back link, and any read/parse failure is treated as "no
  stored query".
- The back link always targets the `posts.index` route; stored data can only
  contribute validated query values, never the path, host or scheme (no open
  redirect).
- No secrets or personal data are written to the URL or storage beyond the
  user's own search text.

## Open questions

Decided by the user (2026-10-20): Q1 (Inertia client-side replace), Q2
(normalise on load, AC-8), Q6 (`@vueuse/core` allowed), Q8 (URL only source
on load, AC-17).

Proposed (not confirmed by user); applied in the AC above, user may
override:

- [NEEDS CLARIFICATION] Q3: Keep unrelated query parameters and the hash
  when rewriting the URL? - Proposed: keep them; only `status`, `sort`, `q`,
  `page` are managed (AC-8).
- [NEEDS CLARIFICATION] Q4: Which value wins for a duplicated managed
  parameter? - Proposed: the first occurrence (AC-6).
- [NEEDS CLARIFICATION] Q5: What exactly is debounced, and how long? -
  Proposed: only the URL and sessionStorage writes, 300 ms; the list keeps
  filtering per keystroke; a pending write is flushed when leaving the page
  (AC-11, AC-12).
- [NEEDS CLARIFICATION] Q7: Which links restore the last list query? -
  Proposed: both "Back to posts" links on the post detail page (header and
  "Post not found"); Dashboard "View all" and sidebar stay plain `/posts`
  (AC-15, AC-17).
- [NEEDS CLARIFICATION] Q9: Which `page` formats are accepted? - Proposed:
  digits-only strings >= 1 (`02` -> 2); `+2`, `2.0`, `1e3`, `0`, `-3`,
  `abc`, empty -> 1 (AC-4).
- [NEEDS CLARIFICATION] Q10: Cap the length of `q` from the URL/storage? -
  Proposed: cut to the first 200 characters (AC-7).
- [NEEDS CLARIFICATION] Q11: How is whitespace in search handled in the
  URL? - Proposed: whitespace-only counts as empty and is omitted; a
  non-empty search is written exactly as typed (AC-10).
