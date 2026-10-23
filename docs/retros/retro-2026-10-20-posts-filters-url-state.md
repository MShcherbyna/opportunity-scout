# Retro: posts-filters-url-state (2026-10-20)

Scope: spec + plan phases only. Implementation (implementer, check-runner, reviewers) has not run yet.
Session `6715d9a0-e05d-477f-a95c-021b508fb4f5`. No previous retro in `docs/retros/`.

## Stats

| #   | agent                                  | description                | model    | start (UTC) | dur  | turns | in  | cache_w | cache_r | out   | tool calls | errors |
| --- | -------------------------------------- | -------------------------- | -------- | ----------- | ---- | ----- | --- | ------- | ------- | ----- | ---------- | ------ |
| 0   | main                                   | (orchestrator)             | opus-5-5 | 16:21:18    | 737s | 14    | 38  | 58768   | 957313  | 9676  | 11         | 0      |
| 1   | sdd-engineering:spec-creator           | Spec: posts filters in URL | opus-5-5 | 16:21:35    | 259s | 12    | 26  | 96264   | 747275  | 5110  | 61         | 4      |
| 2   | sdd-engineering:implementation-planner | Plan: posts filters in URL | opus-5-5 | 16:26:56    | 355s | 17    | 36  | 120574  | 1312440 | 12659 | 79         | 2      |

**Total** in=100 cache_write=275606 cache_read=3017028 out=27445; agents spawned: 2

Costly tokens (in + cache_write + out): planner 133,269 · spec-creator 101,400 · main 68,482.

> **Script caveat:** each `tool_use` is counted twice. The raw transcripts show about 30 real calls for the spec-creator and about 40 for the planner. Every "read ×2 inside one agent" line is double counting, not a real re-read, and the real error count is 2 + 1 (not 4 + 2). Raw lists (tool calls per agent, in-agent duplicate reads) are left out for that reason. The cross-agent duplicates below are real.

Files read by 2+ agents: `.claude/sdd-engineering.json` (missing), `.ai/insights/frontend.md`, `usePosts.ts`, `Posts.vue`, `PostDetail.vue`, `Dashboard.vue`, `package.json`, `PostsFilters.vue`, `routes/posts/index.ts`, `node_modules/@inertiajs/core/dist/index.js`.

Tool errors (deduplicated):

- spec-creator / Read `.claude/sdd-engineering.json`: file does not exist
- spec-creator / Read `.ai/rules/index.md`: file does not exist
- planner / Read `.claude/sdd-engineering.json`: file does not exist

Cost outliers: fewer than 3 subagents, so there is no median. Permission denials or stalls: none.

## Order of agents

Strictly sequential. Each step depended on the one before, so nothing could run in parallel.

1. spec-creator round 1, 16:21:35 to 16:23:36: research, then handed back 11 questions.
2. Orchestrator asked the user 4 questions with AskUserQuestion. The other 7 went into the spec as "Proposed".
3. spec-creator round 2, to 16:25:54: wrote the spec (18 ACs).
4. implementation-planner round 1, 16:26:56 to 16:30:06: handed back 6 questions, 5 recommendations and the execution-mode question.
5. Orchestrator asked the user 4 questions (multi-agent, accept R1–R5, cancel the pending URL write on Back, use defaults).
6. planner round 2, to 16:32:50: wrote `docs/plans/posts-filters-url-state_en.md`.

## Hard

- **No question tool in subagents.** Both agents needed two rounds and a hand-back ("I have no way to ask the user directly from here"). The orchestrator could relay only 4 of the spec-creator's 11 questions per AskUserQuestion call, so 7 decisions (Q3, Q4, Q5, Q7, Q9, Q10, Q11) stayed "Proposed (not confirmed)" and carried into the plan.
- **No `search-docs` (Boost MCP) in subagents.** The task explicitly required it. Both agents worked around it by reading the minified Inertia `dist/index.js` (spec-creator, offsets 1380 and 3399; planner, offsets 1060 and 1440 plus about 8 greps). The required doc check is now deferred to the implementer.
- **Missing project config.** There is no `.claude/sdd-engineering.json` and no `.ai/rules/index.md`, which caused 3 failed reads. Both agents fell back to default paths, and the planner said "I didn't inspect what `vp check` covers".
- **Execution-mode question asked late.** It is only asked in planner round 1, which forced a second planner round. It could have been asked before the planner started.

## Easy

- Reading the Inertia source paid off: targeted grep plus offset reads turned up concrete traps with line numbers (raw `replaceState` desyncs `page.url`; a remount without `preserveState`; flushing on `router.on('before')`; vueuse 12 `useDebounceFn` has no `flush`).
- The task brief was detailed (validation rules, verification list), so the spec needed only one write and the plan mapped all 18 ACs.

## Duplicated

- 10 source files were read by both agents. The overlap is mostly unavoidable: the planner needs the code. The **Inertia history analysis** is real duplicated research, though. The spec already cited `index.js` lines 1410–1505, 1603–1606 and 3336–3440, and the planner re-derived them with about 10 greps and reads.
- The orchestrator pasted the full task text into the spec-creator prompt. That was fine, since there was no spec yet. Later hand-offs passed paths plus decisions, as they should.

## Missed

- **`search-docs` was never run** by anyone, although the task brief requires it (item 3).
- **The 19 manual browser checks are assigned to check-runner**, which only runs commands and has no browser. Nobody in the multi-agent pipeline owns the browser checks.
- **7 decisions are unconfirmed** going into implementation, so rework is possible if the user disagrees.
- What `npm run check` (`vp check`) covers is unknown.
- AC-12's timing (the debounced URL write must land before the next visit pushes its history entry) has no API guarantee. The planner flagged it, but only a manual browser check will settle it.

## Outliers & permission stalls

- No median (only 2 subagents). The planner was the most expensive agent (133k costly tokens, 12.7k out). That bought value: three implementation traps the spec had not identified (a `preserveState` remount loop, flushing on `before` rather than unmount, no `flush` in `useDebounceFn`).
- No permission denials.

## AC coverage

- Spec has 18 ACs. None are missing from the plan, and the plan has none the spec lacks.
- No verifier or review reports exist yet, so implementation coverage is not assessed.

## Model/cost fit

- Opus for spec and plan is appropriate (judgement-heavy, with source-level research).
- For the coming phase, check-runner is mechanical and is a candidate for a cheaper model.
- No parallelism was missed in this phase.

## Recommendations

1. **Planner prompt / plan template:** add a "Browser verification" step owned by the orchestrator (Claude in Chrome) or the user, and stop assigning manual checks to `check-runner`. Otherwise AC-9, AC-12 and AC-18 go unverified.
2. **Orchestrator workflow step:** ask the execution-mode question, plus any obvious decision points, before spawning the planner. This saves a full planner round (about 50k costly tokens here).
3. **Orchestrator relay:** when an agent hands back more than 4 questions, use 2–3 AskUserQuestion calls instead of defaulting the rest. This avoids 7 unconfirmed decisions flowing into implementation.
4. **Agent tool lists (`spec-creator`, `implementation-planner`, `implementer`):** grant `mcp__laravel-boost__search-docs`, or have the orchestrator run `search-docs` once and pass the result in. This satisfies the task requirement and cuts the minified-source grepping.
5. **Add `.claude/sdd-engineering.json`** with `specsDir`, `plansDir`, `retrosDir` and the check commands (`npm run types:check`, `npm run check`). This removes 3 failed reads per run and the "unknown `vp check`" gap.
6. **spec-creator output:** keep a short "Source evidence" block (file:line facts). Tell the planner in its prompt to reuse it rather than re-derive it, which removes about 10 duplicated greps and reads of `dist/index.js`.
7. **`session_stats.py`:** de-duplicate `tool_use` blocks by id. It currently doubles tool calls, in-agent re-reads and errors, which makes the "duplicate reads" section misleading.
