# Frontend Engineering Insights

Raw, append-only session notes for `resources/**` (Vue/Inertia frontend).
Maintained by the `engineering-insights` skill
(`.claude/skills/engineering-insights/`). Never edit or delete existing
entries — corrections are new dated entries. This is not a vetted rules
store; see `.ai/rules` (if present) for approved, durable conventions.

## What Works

## What Doesn't Work

## Codebase Patterns

## Tool & Library Notes

### 2026-09-18

Inertia v3's `defineOptions({ layout: {...} })` is not a layout override when
the value is a plain object — it merges as extra PROPS onto whichever layout
component `app.ts`'s `layout()` callback already resolved for that page name
(Inertia's `useLayoutProps` feature). To change which layout component wraps
a page, add a prefix case to the `switch (true)` in `resources/js/app.ts`; to
add new header content, extend the prop chain through `AppLayout` →
`AppSidebarLayout` → `AppSidebarHeader` instead.
Evidence: `node_modules/@inertiajs/vue3/dist/index.js` (search
`isPropsObject(layoutValue, isComponent)`), `resources/js/app.ts:12-23`

### 2026-09-18

Laravel Wayfinder groups generated TS route helpers by the route name's
first dot-segment: a route named `X.Y` produces
`resources/js/routes/X/index.ts` exporting a `Y` function (e.g.
`settings.aiFiltering` → `@/routes/settings` exporting `aiFiltering()`). A
dash in the LAST segment (e.g. `settings.ai-filtering`) would generate an
invalid JS identifier — use camelCase there instead; the URL itself can
still use dashes. Run `php artisan wayfinder:generate --with-form` after
adding routes — `resources/js/routes`, `resources/js/actions`,
`resources/js/wayfinder` are gitignored and regenerate on `npm run
dev`/`build`.
Evidence: `resources/js/routes/settings/index.ts`, `routes/web.php`
(settings.scraping/aiFiltering/integrations routes)

## Recurring Errors & Fixes

### 2026-09-18

A Vue SFC `<template>` interpolation containing a literal `{{`/`}}` inside
its string, e.g. `{{ '{{position}}' }}` to display the text `{{position}}`,
breaks the compiler tokenizer with "Unterminated string constant" — but only
at `vite build` time, not `vue-tsc` or lint. Define the literal string in
`<script setup>` as a constant/array and interpolate the variable instead;
never put literal double braces in template markup.
Evidence: `resources/js/components/settings/ScrapingSettingsForm.vue`
(`templateVariables` constant)

## Session Notes (datestamped)

## Open Questions
