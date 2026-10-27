# Implementation Plan: Posts list filter state in the URL

## 1. Goal & scope

Goal: the posts list state (status, sort, search text, page) on `/posts` is
read from the query string on load (`status`, `sort`, `q`, `page`). It is
validated and clamped, the address bar is rewritten to the clean form, and
every later change is written back with Inertia's client-side replace (no
request, no new history entry, scroll and component state kept). The last
list query is saved in the tab's sessionStorage, and both "Back to posts"
links on the post detail page use it. URL sync is an opt-in option of
`usePosts`; only the posts page turns it on.

**Non-goals**

- Does not change any specification (the spec is read-only input).
- No backend change: `routes/web.php`, controllers and PHP tests stay as they are.
- No visual change. Markup, classes and labels stay the same.
- No change to Dashboard "View all", the sidebar, or the Dashboard's use of `usePosts`.
- No localStorage, cookies or saving across tabs or sessions.
- No restoring from storage on a plain `/posts` load (AC-17).
- No new dependencies, and no JS test runner.
- `UsePostsReturn` stays unchanged. `PostsFilters.vue`, `PostsTable.vue` and `Pagination.vue` are not edited.

Spec: `/Users/mtakumi/Projects/threads-scraper/specs/2026-10-20-posts-filters-url-state.md` (SPEC-2026-10-20-posts-filters-url-state, status: draft).

## 2. Context read

Files read:

- `CLAUDE.md`, `AGENTS.md` (Laravel Boost guidelines). There is no `.ai/rules` directory and no `.claude/sdd-engineering.json`, so default config applies.
- `.ai/insights/frontend.md`
- `resources/js/composables/usePosts.ts`, `resources/js/pages/Posts.vue`, `resources/js/pages/PostDetail.vue`, `resources/js/pages/Dashboard.vue`
- `resources/js/components/posts/PostsFilters.vue`, `resources/js/components/posts/PostsTable.vue` (the post link is `<Link :href="postsRoutes.show(post.id)">`)
- `resources/js/composables/useCurrentUrl.ts` (it parses `usePage().url` with `new URL(..., origin)`), `resources/js/composables/useAppearance.ts` (its `typeof window` guards)
- `resources/js/app.ts` (no SSR entry), `resources/js/routes/posts/index.ts`, `resources/js/wayfinder/index.ts` (both generated)
- `routes/web.php` (`Route::inertia('posts', 'Posts')->name('posts.index')`), `package.json`
- `node_modules/@inertiajs/core` 3.7.1: `Router.replace`, `performClientVisit`, `page.set`, `fireBeforeEvent`; `node_modules/@inertiajs/vue3` `swapComponent`
- `design/Opportunity_Scout_Design_Canvas_UPDATED_v5.html:1651`: only the label "← Back to posts"

Relevant insights (`.ai/insights/frontend.md`):

> "Laravel Wayfinder groups generated TS route helpers by the route name's first dot-segment ... `resources/js/routes`, `resources/js/actions`, `resources/js/wayfinder` are gitignored and regenerate on `npm run dev`/`build`."

> "Inertia v3's `defineOptions({ layout: {...} })` is not a layout override when the value is a plain object — it merges as extra PROPS onto whichever layout component..."
> (This one is not affected, but `Posts.vue` keeps its existing `defineOptions` unchanged.)

Facts from the code that shape the plan:

- `router.replace(params: ClientSideVisitOptions)` queues a client visit. It sends no request, and props default to the current props.
- It sets `flash: {}`.
- It writes `page.url = params.url` through `history.replaceState`. When `preserveScroll` is true it restores scroll; otherwise it calls `Scroll.reset()`.
- The `@inertiajs/vue3` `swapComponent` sets `key.value = options.preserveState ? key.value : Date.now()`. So `preserveState: false` remounts the page component.
- `fireBeforeEvent` runs only in `router.visit` and prefetch, never for client visits.
- `@vueuse/core` 12 `useDebounceFn` has no `flush`/`cancel`.
- The Wayfinder `queryParams({ mergeQuery })` starts from `window.location.search`. For each key it deletes that key family, then appends the non-null values in object order. `undefined`/`null` values are dropped.

## 3. Affected modules

Single module: the frontend (`resources/js`).

- `resources/js/lib/postsListQuery.ts`: **new**. Pure helpers: allowlists, parsing (AC-3 to AC-7), normalisation (AC-10), query string conversion, and guarded sessionStorage read/write (AC-14, AC-16).
- `resources/js/composables/usePosts.ts`: **modified**. Adds the `syncWithUrl?: boolean` option to `UsePostsOptions`. Adds the initial state from the URL, normalisation in `onMounted`, the watcher, the debounce, the flush on router `before`, and the cleanup. `UsePostsReturn` is unchanged.
- `resources/js/pages/Posts.vue`: **modified**. Calls `usePosts({ perPage: 10, syncWithUrl: true })`.
- `resources/js/pages/PostDetail.vue`: **modified**. Both "Back to posts" links use `postsRoutes.index({ query: readStoredPostsListQuery() })`.
- `.ai/insights/frontend.md`: **modified**, only if a trap is confirmed (step 6), and only through the `engineering-insights` skill.

## 4. Contracts touched

- **Public composable contract:** `UsePostsOptions` gains an optional `syncWithUrl?: boolean` (default `false`). `UsePostsReturn` is **unchanged**. `PostSortOption` and `PostStatusFilter` are unchanged and stay exported from `usePosts.ts`.
- **New exported types** in `lib/postsListQuery.ts`:
    - `PostsListState`: `{ status: PostStatusFilter; sort: PostSortOption; search: string; page: number }`
    - `PostsListQuery`: `{ status?: string; sort?: string; q?: string; page?: number }`, the URL query with defaults left out.
- **URL contract (client-only):** query keys `status`, `sort`, `q`, `page` on `/posts`. Defaults are left out, and the keys are written in that order after any unmanaged parameters.
- **sessionStorage contract:** key `posts-list-query`. The value is the managed query string without `?` (for example `status=maybe&sort=score&q=saas&page=2`); an empty string means defaults.
- **Generated code:** `resources/js/routes/**` and `resources/js/wayfinder/**` are generated by Wayfinder (gitignored). They are consumed only, never edited. No API, DB or backend contract changes.

## 5. Skills for implementer

The general skills come from the `engineering-paved-path` plugin. The project's own skills are listed after them.

- **engineering-paved-path:react-frontend-architecture** (applied to Vue; project conventions win). Governs steps 1 to 4.
    - Pure, React/Vue-free business rules (parsing, validation, normalisation) go in a plain module (`lib/postsListQuery.ts`) shared by its two consumers. The stateful wiring stays in the composable.
    - Name a function `use…` only if it calls Vue or Inertia composition APIs. The helpers in `lib/` must not be named `use…`.
- **engineering-paved-path:typescript-expert**. Governs steps 1 and 2.
    - Narrow untrusted strings with type guards against `as const` allowlists, never with `as` casts.
    - Use `import type` for the types shared between `lib/postsListQuery.ts` and `usePosts.ts`, so the two files have no runtime import cycle.
- **engineering-paved-path:security**. Governs steps 1 and 3.
    - Treat URL and storage values as untrusted: allowlists, a digits-only page, a length cap on `q`, `q` shown only as text.
    - The back link path is always `posts.index`; stored data supplies only validated query values, which rules out an open redirect.
- **Project skill `inertia-vue-development`** (must be activated). Governs steps 2 and 3.
    - Use `router.replace` (client-side visit) with `preserveState: true` and `preserveScroll: true`.
    - Use `router.on('before', …)` and call the unsubscribe function it returns.
    - Keep `<Link :href="RouteDefinition">` usage as is.
- **Project skill `wayfinder-development`** (must be activated). Governs steps 2 and 3.
    - Build URLs with `postsRoutes.index.url({ mergeQuery })` and `postsRoutes.index({ query })`; never concatenate query strings.
    - Never edit generated files.
- **Project skill `engineering-insights`**. Governs step 6: append to `.ai/insights/frontend.md` only through the skill, with a dedupe check first.

## 6. Architecture constraints

- **Layering:**
    - Pages (`resources/js/pages`) stay thin: they only pass the option or build the href.
    - Stateful logic lives in `resources/js/composables/usePosts.ts`.
    - Pure logic goes in `resources/js/lib/`, which is an existing folder. Don't add base folders.
- **Style** (from `usePosts.ts`, and requested by the user):
    - Types with `export type`.
    - A JSDoc block above each exported function.
    - No unnecessary inline comments.
    - Braces on every control structure, and explicit return types on exported functions.
    - 4-space indent, single quotes, trailing commas, as the formatter (`vp check`) enforces.
- **Naming:**
    - `lib/` files are camelCase (`flashToast.ts`, `utils.ts`), so the new file is `postsListQuery.ts`.
    - Use descriptive names (`isPostStatusFilter`, `readStoredPostsListQuery`).
- **Opt-in isolation (AC-1):**
    - When `syncWithUrl` is false, the composable must not call `usePage()`, `router.*`, `window.location`, `history` or sessionStorage, and must not register `router.on`.
    - The default behaviour must stay exactly as it is today.
- **Protected paths:**
    - Lockfiles (`package-lock.json`, `composer.lock`) are not touched, and no dependency changes.
    - Generated `resources/js/routes/**`, `resources/js/actions/**` and `resources/js/wayfinder/**` are not hand-edited. Regenerate them with `php artisan wayfinder:generate --with-form`, or with `npm run dev`/`build`, if they are missing.
- **Specs** (`specs/**`) and `design/**` are read-only.
- **Forbidden commands:** none configured. No migrations are needed.
- **Inertia v3 rules from CLAUDE.md:** check the API with Boost `search-docs` (packages: `inertia`) before writing; Axios is not available, and none is needed.
- **No conflict found** between the skills and the project rules.

## 7. Steps

The lanes use multi-agent mode. Step 1 comes first. After it, Lane A (steps 2 and 3) and Lane B (step 4) can run in parallel, because they touch different files and depend only on step 1's exports. Step 5 (insights) runs after both lanes. The checks in section 8 then run through check-runner.

### Step 0 (Lane A, before step 1): preparation, no file changes

1. Activate the `inertia-vue-development` and `wayfinder-development` skills.
2. Run the Boost `search-docs` tool with `packages: ["inertia"]` and queries `["client side visits replace", "router replace preserveState", "router events before", "manual visits history"]`.
3. Confirm these against the installed `@inertiajs/core` 3.7.1:
    - the v3 signature `router.replace({ url, preserveState, preserveScroll })`;
    - that it sends no request and keeps props;
    - the `router.on('before', cb)` return value (an unsubscribe function).
4. If the docs differ from section 2, follow the docs and the installed source, and note the difference in the hand-off.

### Step 1 (Lane A): new `resources/js/lib/postsListQuery.ts`

Governing skills: typescript-expert, security, react-frontend-architecture.

Imports: `import type { PostSortOption, PostStatusFilter } from '@/composables/usePosts';`, type-only.

Exports, each with a JSDoc block:

- **`POSTS_LIST_STATUS_OPTIONS`** = `['all', 'relevant', 'maybe', 'rejected'] as const satisfies readonly PostStatusFilter[]`.
- **`POSTS_LIST_SORT_OPTIONS`** = `['newest', 'score'] as const satisfies readonly PostSortOption[]`.
- **`POSTS_LIST_SEARCH_MAX_LENGTH`** = `200`.
- **`export type PostsListState`** and **`export type PostsListQuery`** as defined in section 4.
- **`DEFAULT_POSTS_LIST_STATE`**: `{ status: 'all', sort: 'newest', search: '', page: 1 }`.
- **`parsePostsListQuery(params: URLSearchParams): PostsListState`**. Use `params.get()`, which returns the first occurrence (AC-6).
    - `status` / `sort`: accept only an exact, case-sensitive match against the allowlist, using type-guard functions; otherwise the default (AC-3). An empty first value also falls back to the default.
    - `page`: accept only if it matches `/^\d+$/`, then `Number.parseInt(value, 10)`. Use page 1 if the result is `< 1` or not finite (AC-4); for example `02` gives 2, while `0`, `-3`, `+2`, `2.0`, `1e3`, empty and `abc` give 1. Don't clamp to totalPages here; the composable does that.
    - `q`: missing gives `''`. Otherwise keep the first 200 **Unicode code points** (`Array.from(value).slice(0, 200).join('')`) (AC-7). Don't trim.
- **`toPostsListQuery(state: PostsListState): PostsListQuery`** (AC-10). Build the object in the key order `status`, `sort`, `q`, `page`:
    - `status` is `undefined` when `'all'`, and `sort` is `undefined` when `'newest'`.
    - `q` is `undefined` when `search.trim() === ''`; otherwise it is the search **as typed**, with no trimming and no length cap on write (user decision).
    - `page` is `undefined` when `1`.
    - Property order matters: Wayfinder appends in object order (AC-8).
- **`postsListQueryToSearchParams(query: PostsListQuery): URLSearchParams`**: appends only the defined values, in the same order. This is used for the storage value.
- **`readStoredPostsListQuery(): PostsListQuery`**:
    - Return `{}` if `typeof window === 'undefined'`.
    - Otherwise, in `try/catch`, call `window.sessionStorage.getItem('posts-list-query')`. Return `{}` if the value is not a string or the call throws (AC-16).
    - Otherwise return `toPostsListQuery(parsePostsListQuery(new URLSearchParams(value)))`. This re-validates tampered or stale values (AC-15). A stale page is left alone; the posts page clamps it on load.
- **`storePostsListQuery(query: PostsListQuery): void`**:
    - Guard with `typeof window`.
    - In `try/catch`, call `window.sessionStorage.setItem('posts-list-query', postsListQueryToSearchParams(query).toString())`, so an empty query is stored as `''` (AC-14).
    - Swallow every error silently, with no `console.error` (AC-16).
- Keep the storage key as a non-exported `const` in this file.

### Step 2 (Lane A, after step 1): modify `resources/js/composables/usePosts.ts`

Governing skills: inertia-vue-development, wayfinder-development, typescript-expert.

**Options and JSDoc**

- Add `syncWithUrl?: boolean;` to `UsePostsOptions`. **Do not change `UsePostsReturn`.**
- Extend the JSDoc above `usePosts`. Describe that `syncWithUrl` (default `false`):
    - reads the initial state from the current Inertia page URL;
    - keeps the `/posts` query string and the tab's last list query in sessionStorage in sync;
    - is meant only for the posts page.
- State in the JSDoc that when it is on, the URL wins over `initialStatusFilter` (user decision).

**Initial state (synchronous, in setup, only when `syncWithUrl` is true)**

- Call `usePage()` from `@inertiajs/vue3`. Parse `new URL(page.url, typeof window !== 'undefined' ? window.location.origin : 'http://localhost').searchParams` with `parsePostsListQuery`, following the `useCurrentUrl.ts` pattern.
- Initialise the `statusFilter`, `sort` and `search` refs from that result (the URL wins over `initialStatusFilter`).
- When sync is off, keep the current initialisation exactly.
- After `totalPages` and `setPage` are defined, call `setPage(initial.page)` to apply the existing clamp `[1, totalPages]` (AC-5).
    - Data is mocked and synchronous, so `totalPages` is known here.
    - Don't call `setStatusFilter`, `setSort` or `setSearch` for initialisation: they reset the page.

**Normalised query**

- Add `const listQuery = computed(() => toPostsListQuery({ status: statusFilter.value, sort: sort.value, search: search.value, page: page.value }))`.
- It is internal and must not be returned.

**Private `writeListQueryToUrl()`**

1. `const query = listQuery.value`
2. `storePostsListQuery(query)` (AC-14)
3. `const nextUrl = postsRoutes.index.url({ mergeQuery: { status: query.status, sort: query.sort, q: query.q, page: query.page } }) + window.location.hash`. Pass all four keys, even the `undefined` ones, so their stale values are deleted. `undefined` values are dropped and unmanaged parameters are kept (R1, AC-8).
4. If `nextUrl === window.location.pathname + window.location.search + window.location.hash`, return (R4).
5. Otherwise call `router.replace({ url: nextUrl, preserveState: true, preserveScroll: true })`.
    - **Both flags are mandatory.** Without `preserveState` the page remounts, which re-reads and re-writes the URL and drops focus in the search box. Without `preserveScroll` the scroll position resets.
    - Pass no `props`, so the current props are kept.
    - Import `postsRoutes` from `@/routes/posts` and `router`/`usePage` from `@inertiajs/vue3`.

**Debounce (AC-11)**

- Use a module-local `let pendingUrlWrite: ReturnType<typeof setTimeout> | undefined`, the same pattern as `loadingTimeout`/`simulateLoading`. Use no vueuse, because `useDebounceFn` has no flush.
- Private helpers:
    - `scheduleUrlWrite()`: clear any timer, then set a 300 ms timer that clears the pending marker and calls `writeListQueryToUrl()`.
    - `cancelPendingUrlWrite(): boolean`: clear the timer and return whether one was pending.
    - `flushPendingUrlWrite()`: if `cancelPendingUrlWrite()` returns true, call `writeListQueryToUrl()`.

**Watcher**

- Add `watch(listQuery, (next, previous) => { if (next.q !== previous.q) { scheduleUrlWrite(); return; } cancelPendingUrlWrite(); writeListQueryToUrl(); })`.
- This single watcher keeps a page reset caused by typing from writing the URL before the debounce (AC-11, AC-13). A status, sort or page change writes at once, with the latest search included (AC-9).
- Register it only when sync is on, after the initial `setPage` call, so the initialisation doesn't trigger it.

**Mount**

- `onMounted(() => writeListQueryToUrl())`: initial normalisation plus storage (AC-8, AC-14). It runs after Inertia's first history write.
- Also register `const stopListeningForVisits = router.on('before', () => { flushPendingUrlWrite(); })` (AC-12).
    - The callback must not return `false`, or the visit would be cancelled.
    - Client visits don't fire `before`, so there is no loop.

**Unmount**

- In `onUnmounted`:
    - Call `stopListeningForVisits()`.
    - If `cancelPendingUrlWrite()` returns true, call only `storePostsListQuery(listQuery.value)`. **Never call `router.replace` after unmount**: on popstate or on a finished visit, the current history entry is no longer the posts entry (user decision on Q2).
- Keep the existing `onUnmounted(() => clearTimeout(loadingTimeout))`.

**Isolation and style**

- When `syncWithUrl` is false, none of the wiring above runs: no `usePage`, `router`, `window` or storage access (AC-1). Put it all behind one `if (options.syncWithUrl) { … }`, or a private, non-exported setup function called from that branch.
- Keep the style: no inline comments beyond what is truly needed, and JSDoc only where it adds meaning.

### Step 3 (Lane A, after step 2): modify `resources/js/pages/Posts.vue`

Governing skill: inertia-vue-development.

- Change the call to `usePosts({ perPage: 10, syncWithUrl: true })`. Nothing else changes: same template, same `defineOptions`.

### Step 4 (Lane B, after step 1, can run in parallel with steps 2 and 3): modify `resources/js/pages/PostDetail.vue`

Governing skills: wayfinder-development, security.

- Import `readStoredPostsListQuery` from `@/lib/postsListQuery`.
- In `<script setup>`, add `const backToPostsHref = postsRoutes.index({ query: readStoredPostsListQuery() });`.
    - Compute it once at setup; sessionStorage is not reactive.
    - An empty object gives `/posts` with no `?`.
- Replace `:href="postsRoutes.index()"` with `:href="backToPostsHref"` on **both** links: the "Post not found" empty-state `Link` and the header `Link`. The second one is an unconfirmed proposal (Q7, AC-15).
- Change no markup, classes or labels. `Dashboard.vue` is **not** touched.

### Step 5 (Lane A, after the lanes merge and the manual checks): insights

Governing skill: engineering-insights.

- If the browser checks confirm a non-obvious Inertia history-state trap, invoke the `engineering-insights` skill. Add at most 2 entries to `.ai/insights/frontend.md` under "Tool & Library Notes" or "What Doesn't Work".
- Candidates:
    - Client `router.replace` without `preserveState: true` remounts the page (the vue3 `swapComponent` key is `Date.now()`).
    - A pending URL write has to be flushed on `router.on('before')`, not on unmount, or it overwrites the next page's history entry.
    - `router.replace` clears `flash`.
- Dedupe first, and edit only that one place in the file (don't rewrite the file). If nothing surprising happened, record nothing.

## 8. Acceptance checks

**Automated**

- No `checks` are configured (there is no `.claude/sdd-engineering.json`). The user set these commands, and they also match the `package.json` scripts. Both must finish with 0 errors:
    - `npm run types:check` (`vue-tsc --noEmit`)
    - `npm run check` (`vp check`: lint and format). If formatting fails, use `npm run check:fix`, then re-run `npm run check`.
- No PHP files change, so Pint and PHPUnit are not required.
- No new automated tests: there is no JS test runner, and adding one is a new dependency (user decision). AC-4 and AC-16 are checked by hand.

**Manual browser checks** (from the spec)
Use Boost `get-absolute-url` to get the app URL and `browser-logs` to check for errors. If changes don't show up, ask the user to run `npm run dev` / `composer run dev`.

1. **AC-1:** open `/dashboard` and wait for the widget. The address bar stays `/dashboard`, `history.length` is unchanged, and there is no `posts-list-query` key in sessionStorage. Clear sessionStorage first.
2. **AC-2:** open `/posts?status=maybe&sort=score&q=saas&page=2`. The selects show "Maybe" and "Highest score", the search box shows `saas`, and page 2 is highlighted, or the last page per AC-5 with `page` adjusted in the URL.
3. **AC-3:** `/posts?status=garbage&sort=Score` shows "All relevance" and "Newest first", and the URL becomes `/posts`. No console error.
4. **AC-4** (Q9, unconfirmed):
    - `page` set to `-3`, `0`, `abc`, `1.5`, `2.0`, `+2`, `1e3` or empty gives page 1, and `page` is removed from the URL.
    - `page=02` gives page 2 and the URL shows `page=2`.
    - `page=99999999999999999999` gives the last page.
5. **AC-5:** `/posts?status=maybe&page=999` shows the last "Maybe" page, and the URL `page` equals that number.
6. **AC-6** (Q4, unconfirmed): `/posts?status=maybe&status=rejected` shows "Maybe", and the URL has `status=maybe` once. `status=&status=maybe` falls back to the default.
7. **AC-7** (Q10, unconfirmed): `/posts?q=` followed by 300 characters puts 200 characters in the box, and the URL `q` has 200 characters.
8. **AC-8** (Q3, unconfirmed):
    - `/posts?status=garbage&page=-3` becomes `/posts`.
    - `/posts?utm=x&status=maybe#top` stays `/posts?utm=x&status=maybe#top`.
    - `history.length` is unchanged.
9. **AC-9:** change the status, the sort and the page.
    - The network panel shows 0 requests and `history.length` is unchanged.
    - The scroll position is kept.
    - In the console, Inertia's page URL (from Vue devtools or `usePage().url`) equals `location.pathname + location.search`.
10. **AC-10** (Q11, unconfirmed):
    - With all filters at their defaults, the URL is exactly `/posts`.
    - "Maybe" + score + search ` saas` + page 2 gives `/posts?status=maybe&sort=score&q=+saas&page=2`.
    - A search of `"   "` adds no `q`.
11. **AC-11** (Q5, unconfirmed): type `saas` quickly. The list updates on each key, and the URL changes once, about 300 ms after the last key. The search box keeps focus and caret position.
12. **AC-12:** type `saas` and click a post within 300 ms. Both "Back to posts" and browser Back return to a list with `q=saas`.
13. **AC-13:** on page 3, change the sort. `page` disappears from the URL and `sort=score` appears.
14. **AC-14:** after filtering, sessionStorage `posts-list-query` holds the same managed query as the URL. After resetting the filters it holds `''`.
15. **AC-15** (Q7, unconfirmed):
    - Filter, open a post, then hover and click "Back to posts". It opens the stored query with that state shown.
    - `/posts/99999`: the "Post not found" link does the same.
    - A fresh tab on `/posts/1` gives `/posts`.
    - A tampered value (for example `status=evil&page=-1&x=1`) is cleaned in the link.
16. **AC-16:** in DevTools, run `Object.defineProperty(window, 'sessionStorage', { get() { throw new Error('denied') } })` before navigating with Inertia, or block site storage.
    - Filtering still updates the URL and there is no uncaught error.
    - The back link is `/posts`.
17. **AC-17:** filter, go to the Dashboard and click "View all". The list shows the defaults and the URL is `/posts`. The sidebar "Posts" link behaves the same.
18. **AC-18:** filter to page 2, open a post, press Back. The URL and the list show the page-2 filtered state. Forward reopens the post.
19. **Edge cases:**
    - Search containing `&`, `#`, `+`, `%` or Cyrillic text survives a reload unchanged.
    - Clicking an in-page `#hash` keeps the query.
    - Two tabs keep separate back links.

## 9. Risks & open questions

Unconfirmed spec proposals, planned as proposed and **not confirmed by the user**. Implement them as specified; reviewers should not treat them as final product decisions.

- **Q3:** keep unrelated parameters and the hash (AC-8).
- **Q4:** the first duplicate wins (AC-6).
- **Q5:** debounce only the URL and storage writes, 300 ms, flushed when leaving via a visit (AC-11, AC-12).
- **Q7:** both "Back to posts" links use the stored query; "View all" and the sidebar stay plain (AC-15, AC-17).
- **Q9:** digits-only `page` (AC-4).
- **Q10:** cut `q` to 200 characters on read (AC-7).
- **Q11:** whitespace-only search is treated as empty; a non-empty search is written as typed (AC-10).

Risks:

- **Race between the flush and the visit (AC-12):** the flushed `router.replace` is queued asynchronously while the post visit's request is in flight. In practice the replace finishes before the response pushes the new entry, but this is not guaranteed by an API. Check 12 must verify it; if it fails, report back instead of switching to raw `history.replaceState`, which the spec rules out.
- **Back/Forward within 300 ms of typing:** the `/posts` history entry keeps the search text from before the debounce, and only sessionStorage gets the latest one. This is an accepted limitation (user decision on Q2).
- **Flash cleared:** `router.replace` sets `flash: {}`. `/posts` gets no flash today, so this is harmless, but any future flash on `/posts` would be cleared after the first sync.
- **Unmanaged parameters re-encoded:** `URLSearchParams` may rewrite their encoding (`%20` becomes `+`). Their meaning is kept but not their exact bytes (accepted).
- **Search longer than 200 characters typed in the box** is written as typed and shown shorter after a reload (accepted by the user).
- **SSR:** none is configured. The initial read uses `usePage().url` (works with SSR); writes run only in `onMounted` and watchers (client only).

For the architecture-reviewer to check:

- `lib/postsListQuery.ts` imports types from `composables/usePosts.ts` while `usePosts.ts` imports runtime values from `lib/`. This is acceptable only because the first import is type-only.
- Sync code is fully isolated behind `syncWithUrl`.
- No inline cast on untrusted input.

For the security review:

- The allowlist and validation of URL and storage values.
- No open redirect from the back link (the path is fixed to `posts.index`).
- `q` is rendered only as text (`PostsFilters` binds it through `Input` `model-value`).

For the plan-verifier: `UsePostsReturn` is unchanged, `Dashboard.vue` is unchanged, and there are no new dependencies (diff `package.json`).

## 10. Could not determine

- **Inertia docs vs source:** the `search-docs` result for the Inertia v3 client-side `router.replace` could not be fetched by the planner (no Boost tool in this context). The plan relies on the installed `@inertiajs/core` 3.7.1 source; step 0 makes the implementer confirm it.
- **Visit timing:** whether `router.on('before')` fires before Link's own history handling under every Link option. It is confirmed for the default `<Link>` GET visit used by `PostsTable.vue`, but not for prefetch or other options. Flushing early on a prefetch is harmless.
- **`vp check` scope:** the exact checks `vp check` (vite-plus 0.3.0) runs (lint, format, possibly types) were not inspected. The implementer runs it and fixes what it reports.
- **Browser behaviour:** runtime behaviour in a real browser (history queue timing, focus retention) was not observed. It is covered by the manual checks in section 8.

## 11. Requirements review

Answers received, quoted from the coordinator:

- Q1: "use Inertia client-side `router.replace` (preserveState/preserveScroll) for URL updates".
- Q2: "normalize the URL on load".
- Q6: "the already-installed `@vueuse/core` may be used for the debounce". This allows vueuse but doesn't require it. The plan uses plain `setTimeout`, because vueuse 12 `useDebounceFn` cannot flush (AC-12).
- Q8: "on load the URL is the only source, and sessionStorage only feeds the 'Back to posts' link".
- Round-1 Q2 (Back/Forward within 300 ms): "Cancel the pending URL write on unmount, still save to sessionStorage."
- Round-1 Q1 and Q3–Q6: "use defaults. Plan with the spec's proposed options and flag Q3/Q4/Q5/Q7/Q9/Q10/Q11 as unconfirmed. Write a long search to the URL as typed and cut it to 200 only on read. Count characters as Unicode code points. When sync is on, the URL wins over initialStatusFilter. No frontend unit tests; AC-4/AC-16 are checked by hand."

Gaps and conflicts found (handled in the steps):

- A remount loop and lost focus without `preserveState: true` (step 2).
- The flush must use the router `before` event, not unmount (step 2).
- vueuse's debounce has no flush (step 2).
- A watcher on `page` alone would break the debounce (step 2: one watcher on the normalised query).
- `router.replace` clears flash, and unmanaged parameters get re-encoded (section 9).
- There is no JS test runner (section 8).
- The spec is still "draft".

Recommendations and decisions:

- **R1** Wayfinder `mergeQuery` for the replace URL: **accepted**.
- **R2** pure helpers in `resources/js/lib/postsListQuery.ts`, wiring in `usePosts` behind `syncWithUrl`: **accepted**.
- **R3** read the initial state from `usePage().url`: **accepted**.
- **R4** skip the replace when the URL is unchanged but still save to storage: **accepted**.
- **R5** a single sessionStorage key `posts-list-query` holding the managed query string, re-validated on read: **accepted**.

## 12. Execution mode

**multi-agent**. User's choice: "Execution mode: **multi-agent** (implementer → check-runner → architecture-reviewer || plan-verifier)."

Order of work:

1. **implementer, Lane A:** step 0, then step 1.
2. **In parallel after step 1:**
    - **Lane A:** steps 2 and 3 (`usePosts.ts`, `Posts.vue`).
    - **Lane B:** step 4 (`PostDetail.vue`).
    - The lanes touch different files. Lane B depends only on the exports of step 1.
3. **check-runner:** `npm run types:check` and `npm run check`. Then the manual browser checks 1 to 19 in section 8.
4. **In parallel:**
    - **architecture-reviewer:** checks sections 6 and 9.
    - **plan-verifier:** checks sections 3, 4, 6, 7 and 8.
5. **implementer:** step 5 (insights), only if a trap was confirmed.
