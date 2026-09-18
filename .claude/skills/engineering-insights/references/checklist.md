# Entry Format & Quality Checklist

## Format

Append at the end of the relevant `##` section:

```
### YYYY-MM-DD
<One or two sentence concrete, actionable finding — state what happened or
what to do, not a vague warning.>
Evidence: `relative/path/to/file.ext:LINE` — <short description of what's there>
```

Omit the `Evidence:` line only when there's genuinely no specific file/line to
cite (e.g. a tool version quirk, an Open Questions entry).

## The obviousness test

Before writing an entry, ask: "would a competent engineer reading this file or
PR immediately see this on their own?" If yes, skip it. Non-obvious means it
required actually running the code, reading a library's source or issue
tracker, hitting and diagnosing a bug, or discovering a mismatch between docs
and actual behavior.

Every entry should be specific enough that an agent reading it cold knows
exactly what to do or avoid, without needing to re-investigate.

**Qualifies for an entry:**
- An environment-specific error or behavior that isn't documented anywhere
  (e.g. a difference between local and staging config).
- The exact command/arguments needed for a task that isn't the obvious default
  (e.g. a non-standard test or build invocation).
- An undocumented assumption a module or service makes.
- Code that's fragile, auto-generated, or must never be touched a certain way.

**Does not qualify:**
- A one-time edit or a decision scoped to a single task.
- Anything already covered by existing documentation, the vendor skills, or
  `.ai/rules`.

## Vague vs. useful

**Vague (don't write this):**
> Be careful with Promise.all, it can fail unexpectedly.

**Useful (write this instead):**
> Promise.all rejects and discards all results the moment one promise rejects.
> If you need partial results from N independent async calls, use
> Promise.allSettled and filter by status, not Promise.all wrapped in a single
> try/catch.

---

**Vague:**
> Zustand state can get out of sync sometimes.

**Useful:**
> A Zustand selector that returns a new object/array literal on every call
> (e.g. `state => ({a: state.a, b: state.b})`) breaks referential equality and
> re-renders every subscriber on every store update, even unrelated ones.
> Select primitives individually or memoize the selector.

---

**Vague:**
> Watch out for N+1 queries.

**Useful (illustrative template — write real entries with real file:line from this repo):**
> 2026-09-12 — A controller loaded `Thread::all()` then accessed
> `$thread->author->name` while mapping Inertia props, causing an N+1 (1 +
> count(threads) queries). Adding `->with('author')` dropped the query count
> from 51 to 2 for a 50-thread page (confirmed with Boost's `database-query`
> tool).
> Evidence: `app/Http/Controllers/ThreadController.php:41`

---

**Vague:**
> Queued jobs might not run in local dev.

**Useful (illustrative template):**
> `QUEUE_CONNECTION=sync` locally means jobs run inline on dispatch — no worker
> needed. On staging `QUEUE_CONNECTION=database`, so a job dispatched from a
> request silently never runs unless a queue worker process is active. If a
> job's side effects "don't happen" on staging, check the worker before
> debugging the job logic itself.

## Common failure modes to avoid

- Writing an entry for a one-off typo or config value with no transferable
  insight.
- Restating what the code already makes obvious (e.g. "this controller has a
  store method").
- Editing or deleting a prior entry instead of adding a new dated one.
- Filing a durable convention here instead of via `record-rule` / `.ai/rules`.
