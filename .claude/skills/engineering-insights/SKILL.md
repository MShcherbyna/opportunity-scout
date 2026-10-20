---
name: engineering-insights
description: "Captures non-obvious engineering learnings — what works, what doesn't, codebase-specific gotchas, library quirks, recurring bugs and fixes, session notes, open questions — as dated, evidence-backed entries appended to two committed markdown files split by area: .ai/insights/frontend.md for resources/**, .ai/insights/backend.md for everything else (app/**, routes/**, database/**, tests/**, config/**). Use mid-session when something genuinely non-obvious is discovered (a hard-diagnosed bug, undocumented library behavior, a surprising codebase quirk), at the end of any session that hit a real problem or discovery (skip trivial sessions), and at session start to read and summarize the most relevant prior entries. Not for durable, vetted conventions — use Boost's .ai/rules / record-rule for those."
---

# Engineering Insights

Two append-only, dated logs of non-obvious things learned while working in this
codebase. This is raw session capture, not vetted policy — it is explicitly a
draft for future spot-checking. It is a different mechanism from Boost's
`.ai/rules` (durable, approved conventions recorded via `record-rule`/the
`infer-conventions` skill). Never write a durable "this is how we do X" rule
here — that belongs in `.ai/rules`. This skill records what someone _discovered_,
dated, so the next session (human or agent) doesn't rediscover it the hard way.

## The two files

| File                       | Covers                                                                                              |
| -------------------------- | --------------------------------------------------------------------------------------------------- |
| `.ai/insights/frontend.md` | Anything under `resources/**` (Vue components, pages, composables, layouts, CSS, Inertia page code) |
| `.ai/insights/backend.md`  | Everything else: `app/**`, `routes/**`, `database/**`, `tests/**`, `config/**`, `bootstrap/**`      |

Route by the path glob of the code the finding is _about_, not by "what kind of
task was this". A single session can produce entries in both files — split them,
don't force everything into one file for convenience.

If a finding genuinely spans both (e.g. an Inertia prop shape mismatch between
a controller and the Vue page consuming it), file it where the root cause lives
(usually backend.md for a data/serialization root cause, frontend.md for a
rendering/consumption root cause) and add a one-line cross-reference in the
other file instead of duplicating the full entry.

## When to write an entry

Write entries after sessions that ran more than ~30 minutes and hit a real
problem, discovery, or fix. Skip trivial sessions — pure config tweaks, typo
fixes, copy edits, or (a concrete tell, not just a time estimate) sessions with
fewer than ~3 real back-and-forth exchanges with no actual investigation.
Writing nothing is the correct outcome for those.

Self-trigger mid-session the moment you notice something non-obvious, rather
than waiting until the end: a bug that took real diagnosis, a library behaving
differently than its docs claim, a codebase quirk that would surprise a
competent engineer reading the code cold.

This is not a session transcript replay. Extract the insight, not the
conversation — a good entry stands alone without the surrounding chat.

Before writing anything, apply the obviousness test and the vague-vs-useful
check in `references/checklist.md`. If it's obvious to anyone reading the code,
don't write it.

## The seven sections

Every insight file has exactly these headings, in this order. Append new
entries at the _end_ of the relevant section — never reorder or rewrite
existing entries.

1. **What Works** — a pattern, approach, or library usage that worked well here.
2. **What Doesn't Work** — a pattern or approach that looked reasonable but failed.
3. **Codebase Patterns** — non-obvious conventions specific to this app (not
   generic Laravel/Vue best practice — that belongs in the vendor skills or
   `.ai/rules`).
4. **Tool & Library Notes** — quirks of a specific dependency, its version, or
   its config in this project.
5. **Recurring Errors & Fixes** — a bug/error that has shown up more than once
   (or is likely to), with its fix.
6. **Session Notes (datestamped)** — free-form dated notes that don't fit the
   other categories cleanly.
7. **Open Questions** — something unresolved worth flagging for a human or a
   future session.

## Entry format

See `references/checklist.md` for the full template, the vague-vs-useful
example bank, and the obviousness test. In short: every entry is dated, states
a concrete and actionable finding, and cites `path/to/file:line` when the
finding traces to specific code.

## Append-only discipline

- Never edit or delete an existing entry, even a wrong or outdated one.
- A correction is a _new_, dated entry — write it under the same section (or
  Session Notes) and say what changed and why, referencing the old entry's date
  if useful. Do not go back and edit the original.

**Mechanical safeguards (do not skip these, even in a hurry):**

- Never rewrite either insight file from memory or reconstruct it wholesale.
  Always re-read the target file immediately before editing it, in this same
  turn — its content may have changed since you last saw it (another session,
  another agent, or a manual edit).
- Use a targeted, anchored edit (append after an existing line you just read,
  e.g. the section heading or the last entry in that section) — never an
  operation that replaces the whole file's contents. If the file doesn't exist
  yet, create it with the skeleton headers, don't guess at prior content.
- After writing, `git diff -- .ai/insights/` and check the diff is 100%
  additions (`+` lines only). If any existing line shows as removed or
  changed, stop — do not proceed — revert your edit and redo it as a proper
  append instead.

## Workflow

1. Identify which glob(s) this session touched → determine target file(s)
   using the routing table above.
2. Draft candidate entries from what you actually discovered this session.
3. Run each candidate through the obviousness test and vague-vs-useful check
   in `references/checklist.md`. Drop anything that doesn't clear the bar.
4. **Deduplicate before writing**: read the target file's relevant section
   first. If a candidate overlaps with an existing entry's content, drop the
   candidate — don't write a near-duplicate. If it's a genuine correction or
   update to an existing entry, write it as a new dated entry that references
   the old one (see Append-only discipline below); never edit the old entry.
5. **Cap and prioritize**: if more than ~3-5 candidates survive from one
   session, keep the highest-priority ones and drop the rest rather than
   flooding the file. Priority order, highest first: (1) a correction to
   something the user explicitly pointed out, (2) a recurring or error
   pattern, (3) a new pattern or tool/library note, (4) an open question.
6. For each surviving entry, append it to the end of the correct section in
   the correct file(s), using the format in `references/checklist.md`.
7. After writing, run `git diff -- .ai/insights/` and confirm the diff is a
   pure addition (no removed or rewritten lines) before considering the task done.

## What not to record here

- Vague warnings ("be careful with X") — see the checklist for concrete
  rewrites.
- Anything obvious from reading the code.
- Durable team conventions/decisions — those go through `record-rule` into
  `.ai/rules`, not here.
- Secrets, credentials, tokens, or anything from `.env`.
