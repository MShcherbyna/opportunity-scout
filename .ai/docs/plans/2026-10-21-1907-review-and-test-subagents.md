# Development Plan: test-writer, architecture-reviewer and plan-verifier subagents

- Created: 2026-10-21 19:07
- Status: draft
- Request: Create three new Claude Code subagents in `.claude/agents/`: `test-writer` (writes backend and UI tests using project skills), `architecture-reviewer` (strictly read-only, evidence-based architecture review per the user's full spec), and `plan-verifier` (checks finished code against every plan item and requirement, possibly on a different model / higher effort). Update `.claude/agents/README.md`.

## 1. Goal and acceptance criteria

- [ ] `.claude/agents/test-writer.md`, `.claude/agents/architecture-reviewer.md`, `.claude/agents/plan-verifier.md` exist, have valid YAML frontmatter (`name`, `description` plus the fields in section 4), and follow the house prompt shape: one-paragraph role → "When invoked" numbered flow → rule sections → fenced report format → "Reply in the language the task was written in."
- [ ] `architecture-reviewer` and `plan-verifier` cannot create, modify or delete files: `Write`, `Edit`, `NotebookEdit` are not in their tool list and are in `disallowedTools`; their `Bash` calls go through a `PreToolUse` guard that exits `2` for anything outside the allowlist.
- [ ] `plan-verifier` can run the project's non-mutating checks (`php artisan test`, `vendor/bin/phpunit`, `composer types:check`, `npm run types:check`, `npm run check`, and the read-only format check `vendor/bin/pint --test [--dirty] [--format agent] [paths]`) and nothing that writes to the working tree (no `pint` without `--test`, no `pint --repair`, no `composer test`, no `wayfinder:generate`, no `migrate`, no installs). `architecture-reviewer` (`inspect` profile) cannot run Pint in any form.
- [ ] `test-writer` can only `Write`/`Edit` files under `tests/` and `database/factories/`; its `Bash` guard allows test generation (`php artisan make:test --phpunit`), factory generation (`php artisan make:factory <Name> [--model=X] --no-interaction`), test runs, `composer types:check`, and Pint scoped to `tests/` / `database/factories/` paths, and blocks dependency changes (`composer require`, `npm install`, etc.).
- [ ] `test-writer` lists every created or changed factory in a "Factories added or changed" section of the Test Report, flags changes to an existing factory's `definition()`, and after such a change runs the full affected suite (every test using that factory).
- [ ] `test-writer`, when no frontend test tooling is installed (current state), covers UI behaviour through Inertia HTTP tests (`assertInertia`) and reports the frontend-unit/browser gap instead of installing anything.
- [ ] `architecture-reviewer` prompt covers every point of the user's spec (section 4, Step 4) including severity scale, finding fields, evidence rule, anti-overengineering rule, project-rules-first order and the "Architecture Review Summary".
- [ ] `plan-verifier` runs on a different model than `implementer` (`sonnet`) and returns a traceability matrix with a status per plan item (MET / PARTIAL / NOT MET / NOT VERIFIABLE), evidence per item, a scope check, and a verdict; it does not give generic advice.
- [ ] Each hook script is executable (`chmod +x`), passes `bash -n`, and passes the sample-JSON allow/deny table in Step 7.
- [ ] `.claude/agents/README.md` shows the new agents in the workflow diagram, the overview table, per-agent sections, and the sources table.

## 2. Context

- Modules and files in scope:
    - `.claude/agents/README.md` — agent map: workflow diagram, overview table, per-agent sections, "Sources behind planner and implementer" table. Must be extended.
    - `.claude/agents/planner.md`, `implementer.md`, `researcher.md`, `translator.md` — conventions to copy: frontmatter order `name, description, tools, disallowedTools, model, effort, color, hooks`; `Agent` always disallowed (except `researcher`, which omits it — keep it disallowed in the new agents per README line 24); hooks command form `'"$CLAUDE_PROJECT_DIR"/.claude/hooks/<script>.sh'`; colors in use: blue (planner), green (implementer), cyan (translator); researcher has none.
    - `.claude/hooks/planner-bash-guard.sh` — allowlist Bash guard: reads `.tool_input.command` with `jq`, strips `2>&1` / `>/dev/null`, denies `>`, `$(`, backticks, `<(`, splits on `|| && | ; &`, checks the first word of each segment, exits `2` with a message on stderr. Basis for the new shared guard.
    - `.claude/hooks/planner-write-guard.sh`, `translator-write-guard.sh` — path-guard pattern for `Write|Edit` (`$CLAUDE_PROJECT_DIR`, absolute-path normalisation, `..` rejection). Basis for `test-writer-write-guard.sh`.
    - `tests/Feature/**`, `tests/Unit/**`, `tests/TestCase.php` — PHPUnit test layout; tests use `use RefreshDatabase`, `test_` prefixed methods, factories (`tests/Feature/DashboardTest.php`).
    - `phpunit.xml` — suites `Unit`, `Feature`; `DB_DATABASE=testing`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`.
    - `phpstan.neon` — analyses `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`; **not** `tests/`. So `composer types:check` is irrelevant for test-only changes, but it **does** cover `database/factories/` (relevant since test-writer may edit factories, see Decisions).
    - `package.json` — `check` = `vp check`, `check:fix` = `vp check --fix`, `types:check` = `vue-tsc --noEmit`.
- Installed test stack (facts, `composer.json` / `package.json`): `laravel/framework ^13.17` (13.32 installed), `phpunit/phpunit ^12.5`, `inertiajs/inertia-laravel ^3.0` (3.3.4), `mockery/mockery`, `fakerphp/faker`, `@inertiajs/vue3 ^3`, `vue ^3.5`, `typescript ^5.2`. **Not installed:** vitest, `@vue/test-utils`, `@testing-library/vue`, playwright, `laravel/dusk`, pest.
- Relevant insights:
    - 2026-09-18 — 18 of 39 auth/session feature tests already fail (419 / not authenticated) (`.ai/insights/backend.md`). `test-writer` and `plan-verifier` must record a baseline and separate pre-existing failures from new ones.
    - 2026-09-18 — Wayfinder route helpers are gitignored and regenerated (`.ai/insights/frontend.md`). `plan-verifier` must not regenerate them; a missing helper is reported, not fixed.
    - 2026-09-18 — `Route::inertia` cannot forward route params (`.ai/insights/backend.md`). Useful evidence-level context for the architecture reviewer, not a rule.
- Project rules sources: `CLAUDE.md`, `AGENTS.md`, `README.md`, `.ai/insights/*.md`, `.claude/skills/*`. **`.ai/rules/` does not exist**; there is no `ARCHITECTURE.md`, no ADR directory, no `deptrac.yaml` / `phparkitect.php` (checked with `find`, depth 3). The reviewer prompt must treat these as optional and skip missing ones silently.
- Skills (facts from `.claude/skills/*/SKILL.md`):
    - `testing-best-practices` — "Use the test tools that the project installs. Add a new test dependency, plugin, or browser only after the user asks for it."; "Real-browser tests require `laravel/dusk` … Mention the package only if the user asks"; feature test first; file at the mirrored path (`rules/naming.md`); review checklist (`rules/review.md`); "Report each defect in the code before you write a test."
    - `laravel-best-practices` — `rules/architecture.md`, `routing.md`, `validation.md`, `security.md`, `queue-jobs.md`, `events-notifications.md`, `http-client.md`, `eloquent.md`, `error-handling.md`. Source of project-preferred architecture (actions, constructor injection) for the reviewer.
    - `inertia-vue-development`, `wayfinder-development`, `fortify-development`, `tailwindcss-development` — domain skills.
    - `infer-conventions` — `disable-model-invocation: true`; cannot be preloaded or auto-invoked; only for explicit user requests. None of the new agents uses it.
    - `engineering-insights` — writes to `.ai/insights`; none of the new agents writes insights (they return "insight candidates" like the implementer).
- Existing code to reuse: `planner-bash-guard.sh` logic (segmenting, redirection/substitution denial, `find`/`git`/`composer`/`php artisan` sub-rules); write-guard path normalisation.
- Assumptions:
    - A1: `vp check` (`npm run check`) is read-only because a separate `check:fix` script exists. To confirm in Step 7 by running it on a clean tree and checking `git status` is unchanged.
    - A2: Unknown frontmatter fields are silently ignored and `effort` levels depend on the model (official docs, see Sources); `opus` supports `high`.
    - A3: The parent session runs in auto mode (seen in this repo's workflow), so `permissionMode` in subagents is ignored (README sources table) and tool lists + hooks are the real boundary.
    - A4: Running PHPUnit mutates only the `testing` database, `storage/` logs and PHPUnit's result cache, not tracked files. Accepted side effect for "read-only" verification; to confirm with `git status` in Step 7.

## 3. Decisions and constraints

- **One shared, parametrised read-only Bash guard** `.claude/hooks/readonly-bash-guard.sh <agent-name> <profile>` with profiles `inspect` (same allowlist as `planner-bash-guard.sh`) and `verify` (`inspect` + non-mutating checks). Used by `architecture-reviewer` (`inspect`) and `plan-verifier` (`verify`). — Avoids three copies of the same parser; the agent name in the deny message keeps errors readable. `planner-bash-guard.sh` is left untouched to avoid regressing a working agent (migrating it is out of scope, see Risks).
- **A separate `test-writer-bash-guard.sh`** rather than a third profile. — test-writer needs commands that write (`php artisan make:test`, `vendor/bin/pint` on `tests/` paths), so it is not a read-only profile and mixing it into the read-only script would weaken the meaning of that script.
- **`test-writer` write boundary (user decision 2026-10-21, Q2, changed from the original default):** `Write|Edit` only on paths under `$CLAUDE_PROJECT_DIR/tests/` and `$CLAUDE_PROJECT_DIR/database/factories/`. test-writer has full access to factories: it may create new factories and change existing ones (`definition()` and states). Everything else under `database/` (`migrations/`, `seeders/`) and all app code stays denied. — Factories are test infrastructure; letting test-writer add the states it needs avoids a round-trip to the implementer. Safeguards: (a) changes to an existing factory's `definition()` can change the data other tests get, so they must be listed in the Test Report and the full affected suite must be run afterwards; (b) `database/` is in PHPStan scope, so test-writer runs `composer types:check` after factory changes; (c) if a new factory needs app-code changes (e.g. `HasFactory` on the model, a missing relation or cast), test-writer does not make them and reports them under "Needs from implementer".
- **Strict read-only = omit `Write`, `Edit`, `NotebookEdit` from `tools`, also list them in `disallowedTools`, and guard `Bash` with an allowlist hook.** — Official docs: regex deny-lists on Bash are bypassable; the strictest read-only is no edit tools plus a Bash guard; whether `permissionMode: plan` blocks Bash writes in a subagent is unconfirmed, so it is not relied upon and not set.
- **`Skill` tool:** disallowed for `architecture-reviewer` and `plan-verifier` (they `Read` skill files directly, like the planner) — prevents a skill such as `engineering-insights` from steering a read-only agent toward writes and keeps context small. Allowed for `test-writer`, which invokes domain skills at runtime like the implementer.
- **Preloading:** `test-writer` preloads only `testing-best-practices` via `skills:` because every invocation needs it; other skills are invoked on demand. — Deviation from README's "not preloaded" rule, justified by the guaranteed need; documented in the README sources table.
- **Models / effort:**
    - `test-writer`: `sonnet` / `medium` — same tier as the implementer; test writing is execution against a clear rule set (`testing-best-practices`). Tests are derived from the plan's acceptance criteria and the code's decisions, not from the implementer's report, to reduce shared blind spots.
    - `architecture-reviewer`: `opus` / `high` — cross-module reasoning and trade-off judgement; low call frequency, so cost is acceptable.
    - `plan-verifier`: `opus` / `high` — must differ from the implementer (`sonnet`): LLM judges show self-preference / self-enhancement bias (Panickssery et al.; Zheng et al.), and Anthropic's best practices recommend a fresh model trying to refute the result. No source shows higher effort reduces bias; `high` is a reasoned choice for careful item-by-item checking. Effort `high` confirmed (user decision 2026-10-21, Q4); `xhigh`/`max` not used.
- **Colors:** `test-writer` yellow, `architecture-reviewer` purple, `plan-verifier` red — distinct from blue/green/cyan already in use.
- **Verifier input discipline:** the verifier receives the plan path, the original request, and optionally a git base ref. If an Implementation Report is passed, its claims are a checklist to verify, never evidence. — Avoids grading the implementer's self-assessment.
- **Verifier ground truth:** every MET status must cite repository evidence (`file:line`) and, where the plan names a test or verify command, the command output. — Anthropic "Building effective agents": ground truth from the environment; best practices: "show evidence rather than asserting success".
- **Status scale** MET / PARTIAL / NOT MET / NOT VERIFIABLE and verdict PASS / PASS WITH GAPS / FAIL — a design choice (not a standard), modelled on a requirements traceability matrix.
- **Pint and the verifier (user decision 2026-10-21, Q3, changed from the original default):** `plan-verifier` may run Pint **only as a read-only format check**, as a documented exception to `CLAUDE.md`'s "Do not run `vendor/bin/pint --test --format agent`". Exact allowed form (enforced by the `verify` profile of the guard):
    - `vendor/bin/pint --test [--dirty] [--format agent | --format=agent] [<path> ...]` — `--test` is mandatory; flags may appear in any order; every other argument must be a path (not starting with `-`).
    - Denied: any invocation without `--test` (including `vendor/bin/pint` and `vendor/bin/pint --dirty --format agent`), `--repair` (fixes files even in test mode), and every other flag (`--config`, `--preset`, `--diff`, `--cache-file`, `--output-*`, …) to keep the parser small and fail-closed.
    - The `inspect` profile (architecture-reviewer) still denies Pint in every form.
    - Justification: the `CLAUDE.md` rule targets agents that can write — for them the right action is to fix (`--dirty`), not merely detect. The verifier has no write access, so `--test` is the only way it can produce evidence for the project rule "run `vendor/bin/pint --dirty --format agent` before finalizing"; `--test` does not modify files. Preferred usage: `vendor/bin/pint --test --format agent <changed PHP files from the change set>` (explicit paths, because `--dirty` misses work that is already committed); `--dirty` is allowed only together with `--test` for an uncommitted-only change set.
    - Status rule: a plan step whose `Verify` names `vendor/bin/pint --dirty --format agent` is checked with the equivalent `vendor/bin/pint --test --format agent <that step's changed PHP files>`; if it fails, that step's verify sub-item is PARTIAL (not NOT MET — the code may be correct, only unformatted). Formatting failures on changed files that no plan item names are reported once under "Project rule checks" as a gap against the project rule, never as an AC failure; they can lower the verdict to PASS WITH GAPS at most. Files outside the change set are not checked.
    - The agent prompt overrides `CLAUDE.md` here by explicit user decision; `CLAUDE.md` itself is not edited.
- **Frontend tests (user decision 2026-10-21, Q1, confirms the default):** no frontend/browser test tooling and no new dependencies. Without vitest / `@vue/test-utils` / Dusk / Playwright, `test-writer` covers UI contracts server-side only (`assertInertia` component name + props, `loadDeferredProps`, `reloadOnly`, validation errors, redirects) and lists uncovered client-side behaviour under "Coverage gaps".
- **test-writer invocation (user decision 2026-10-21, Q5, confirms the default):** `test-writer` runs only on request, not automatically after `implementer`; the README workflow diagram shows it as an optional step.
- **Architecture-reviewer tooling:** may detect and read existing `deptrac` / `PHPArkitect` configs and run them only if already installed (they are not); never adds them.
- Out of scope: migrating `planner-bash-guard.sh` to the shared guard; installing any frontend or browser test tooling; a `security-reviewer` agent (referenced in README but not requested); creating `.ai/rules`; editing `CLAUDE.md` / `AGENTS.md` (the Pint exception lives only in the plan-verifier prompt and guard); writing tests for the hooks inside `tests/` (hook tests are manual JSON pipes, Step 7).

## 4. Steps

### Step 1: Shared read-only Bash guard [backend]

- Files: create `.claude/hooks/readonly-bash-guard.sh`
- Do:
    - Usage: `readonly-bash-guard.sh <agent-name> <inspect|verify>`; unknown profile → exit 2 ("misconfigured hook").
    - Copy the parsing core from `planner-bash-guard.sh` unchanged (jq read, `2>&1` / `>/dev/null` stripping, deny `>`, `$(`, backtick, `<(`, segment split, first-word check, `sort -o`, `find -delete|-exec|…`, git read-only subcommands and branch listing, `composer show`).
    - Deny message: `Blocked by readonly-bash-guard (<agent-name>, <profile>): <reason>. Allowed: <profile summary>.`
    - `inspect` profile = exactly the planner allowlist: `ls cat head tail wc grep rg tree date pwd stat file basename dirname cut jq echo printf true sort find git(read-only) composer show php artisan route:list|about|list|help|config:show|model:show|schedule:list|event:list`.
    - `verify` profile = `inspect` plus:
        - `php artisan test …` (any arguments; it only touches the `testing` database),
        - `vendor/bin/phpunit …`,
        - `composer types:check` (exact subcommand; still deny `composer test`, `composer lint`, `composer run`, `composer install|update|require|remove`),
        - `npm run types:check`, `npm run check` (exact; deny `npm run check:fix`, `npm run build`, `npm run dev`, `npm install|ci|i|add|update|uninstall`, `npx`),
        - `vendor/bin/pint` only in the read-only form from Decisions: `--test` must be present; allowed flags are `--test`, `--dirty`, `--format agent` / `--format=agent`; every other argument must not start with `-` (treated as a path); deny `--repair` and any other flag; deny when `--test` is absent,
        - `docker compose exec [-T] laravel.test <cmd>` where `<cmd>` is re-checked against the `verify` profile (strip the prefix, then apply the same rules).
    - `inspect` profile keeps `vendor/bin/pint` denied in every form.
    - Header comment in the style of the existing guards.
- Skills: none (shell script, not application code)
- Generate: `chmod +x .claude/hooks/readonly-bash-guard.sh`
- Tests: none in `tests/`: hook is verified with the JSON table in Step 7.
- Verify: `bash -n .claude/hooks/readonly-bash-guard.sh` and the Step 7 table rows for `inspect` and `verify`.

### Step 2: test-writer guards [backend]

- Files: create `.claude/hooks/test-writer-write-guard.sh`, `.claude/hooks/test-writer-bash-guard.sh`
- Do:
    - `test-writer-write-guard.sh` (pattern of `planner-write-guard.sh`): normalise relative paths against `$CLAUDE_PROJECT_DIR`; deny `..` (before prefix matching, so `database/../app/x.php` and `tests/../app/x.php` are denied); allow only `"$project_dir/tests/"*` and `"$project_dir/database/factories/"*`. Everything else, including `database/migrations/` and `database/seeders/`, is denied. The guard allows all of `tests/`; the prompt (Step 3) forbids changing `tests/TestCase.php` or the assertions of existing tests without reporting it. Deny message names the agent and both allowed paths.
    - `test-writer-bash-guard.sh`: same parsing core; allowlist = `inspect` commands plus:
        - `php artisan make:test <Name> --phpunit [--unit] [--no-interaction]`,
        - `php artisan make:factory <Name> [--model=<Model>] [--no-interaction]` (no other flags),
        - deny any other `make:*`,
        - `php artisan test …`, `vendor/bin/phpunit …`,
        - `composer types:check` (exact; needed after factory changes because PHPStan covers `database/`),
        - `vendor/bin/pint --format agent <paths>` only when every path argument starts with `tests/` or `database/factories/` and contains no `..` (deny `--dirty`, because it would format the implementer's uncommitted app files; deny no-path invocation),
        - `docker compose exec [-T] laravel.test <cmd>` re-checked against this allowlist,
        - deny everything else, explicitly including `composer require|install|update|remove`, `npm install|i|ci|add`, `npx`, `php artisan migrate*`, `php artisan wayfinder:generate`, `git` writing subcommands.
    - Implementation option (implementer's choice, state it in the report): duplicate the parsing core, or move it into a sourced helper (for example `.claude/hooks/lib/bash-guard-core.sh`) used by both new guards. Do not add test-writer rules as a profile of `readonly-bash-guard.sh` (see Decisions).
- Skills: none
- Generate: `chmod +x .claude/hooks/test-writer-write-guard.sh .claude/hooks/test-writer-bash-guard.sh`
- Tests: none in `tests/`: Step 7 table.
- Verify: `bash -n` on both scripts and the Step 7 rows for test-writer.

### Step 3: test-writer agent [backend]

- Files: create `.claude/agents/test-writer.md`
- Do: frontmatter:
    ```yaml
    name: test-writer
    description: Writes PHPUnit feature and unit tests for backend behaviour and for Inertia/Vue pages (server-side Inertia assertions) in this Laravel project, following testing-best-practices and the domain skills of the code under test. Use on request after an implementation step or when coverage is missing. Writes only under tests/ and database/factories/ (may create factories and change existing ones); never changes application code, migrations, seeders or dependencies.
    tools: Read, Grep, Glob, Edit, Write, Bash, Skill, mcp__laravel-boost__search-docs, mcp__laravel-boost__application-info, mcp__laravel-boost__database-schema
    disallowedTools: Agent, WebSearch, WebFetch, NotebookEdit
    model: sonnet
    effort: medium
    color: yellow
    skills:
      - testing-best-practices
    hooks:
      PreToolUse:
        - matcher: "Bash"   -> test-writer-bash-guard.sh
        - matcher: "Write|Edit" -> test-writer-write-guard.sh
    ```
    System prompt outline:
    1. Role paragraph: writes tests and the factories they need; never edits application code, migrations, seeders, config, dependencies, `.ai/*`; never commits; never launches agents.
    2. "When invoked":
        1. Get the scope: a plan path and/or files/feature. If neither is concrete, return "Clarification needed" (researcher-style block).
        2. If a plan is given, read the English base plan; list acceptance criteria and each step's "Tests" field as the test targets.
        3. Read `.ai/insights/backend.md` (and `frontend.md` for pages).
        4. Read the code under test and sibling tests; identify every decision (branch, validation, calculation, authorization) per `testing-best-practices` "How to Apply".
        5. Invoke domain skills with `Skill`: `laravel-best-practices` (controllers, models, jobs, HTTP client), `inertia-vue-development` (pages/props/forms), `wayfinder-development` (routes referenced by the frontend), `fortify-development` (auth flows). `testing-best-practices` is preloaded; read its selected `rules/*.md` files.
        6. Baseline: run the existing tests in scope; record pre-existing failures (known: auth/session tests, see insights).
        7. Report code defects found before writing tests (skill rule), then write tests: `php artisan make:test --phpunit <Name>` (`--unit` only for framework-free logic), mirrored paths, `test_` prefix, `RefreshDatabase`, factories and existing states. Prefer existing factory states; add a new state or factory (`php artisan make:factory <Name> --model=<Model> --no-interaction`) only when no existing one fits.
        8. Run the new tests; fix only the tests (and factories this agent created or changed); after 3 failed attempts on the same failure, stop and report.
        9. If an existing factory's `definition()` was changed: find every test that uses that factory (`grep -rln '<Model>::factory' tests`) and run them all (or the whole suite if usage is broad); compare with the baseline. If any factory changed: run `composer types:check`.
        10. `vendor/bin/pint --format agent <new/changed test and factory paths>`.
        11. Self-check against `testing-best-practices/rules/review.md`.
        12. Return the Test Report.
    3. "Backend tests": feature-first; HTTP auth/authorization/validation/status; `Http::fake()` + `Http::preventStrayRequests()` for the Threads API client; `Queue::fake`/`Bus::fake`/`Event::fake`/`Notification::fake` where side effects matter; time via `$this->travelTo()`; assert response + DB state + side effects for writes.
    4. "UI tests (current tooling)": cover each Inertia page through HTTP: `assertInertia(fn (AssertableInertia $page) => $page->component(...)->has/where(...))`, deferred props with `loadDeferredProps`, partial reloads with `reloadOnly`, validation error bags, redirects and flash data; assert that props do not leak data the user must not see (props are client-visible). Use `search-docs` (Inertia v3 testing) before writing these.
    5. "Frontend test tooling absent": do not install vitest, `@vue/test-utils`, `@testing-library/vue`, Playwright or Dusk; do not create JS test files; list client-only behaviour (conditional rendering, emits, local state, keyboard interaction) under "Coverage gaps" with a recommendation that needs user approval. If tooling is later approved and installed, follow Vue's guidance (test what a component does, not how; Testing Library principles) — the agent file states this as a conditional section.
    6. "Boundaries": only `tests/` and `database/factories/`; factories may be created and existing ones changed (`definition()` and states), but every change to an existing factory's `definition()` must be listed in the Test Report because it can affect other tests, and the full affected suite must be run afterwards; prefer adding a state over changing `definition()`; do not edit `tests/TestCase.php` or change the assertions of existing tests without saying so in the report; do not delete tests or factories; app-code changes a factory needs (`HasFactory`, relations, casts, a missing model) → report under "Needs from implementer"; no migrations or seeders; no dependency changes; no `migrate`, no `wayfinder:generate`.
    7. "Test Report" format:
        ```
        ## Test Report

        Scope: <plan path | files>
        Status: done | partial | blocked

        ### Tests added or changed
        - `tests/Feature/...Test.php` — `test_...` — <decision or failure mode it catches>

        ### Factories added or changed
        - `database/factories/...Factory.php` — new factory | new state `<name>` | changed `definition()` (<what changed>; affected tests run: <command> → <result>), or "None."

        ### Code defects found (not fixed)
        - `<file>:<line>` — <defect>, or "None."

        ### Coverage gaps
        - <behaviour> — <why not covered (e.g. no frontend test runner)> — <what would cover it>

        ### Needs from implementer
        - <app-code change or seam needed (e.g. `HasFactory` on a model, injectable dependency)>, or "None."

        ### Skills applied
        - `<skill>` — <rule files read>

        ### Checks
        | Command | Baseline | After |
        |---|---|---|

        ### Pre-existing failures (not caused by these tests)
        - <test> — <error>, or "None."
        ```
    8. Closing line: reply in the language of the task.
- Skills: `testing-best-practices` (the prompt must not contradict it: feature-first, installed tools only, mirrored paths, report defects first, review checklist; factory guidance), `laravel-best-practices` (factory conventions), `inertia-vue-development`
- Generate: none
- Tests: none: agent definition; verified by dry run (Step 7).
- Verify: frontmatter parses (Step 7 YAML check); dry run in Step 7.

### Step 4: architecture-reviewer agent [backend]

- Files: create `.claude/agents/architecture-reviewer.md`
- Do: frontmatter:
    ```yaml
    name: architecture-reviewer
    description: Strictly read-only architecture review of this Laravel + Inertia/Vue codebase or of a given change. Checks layer and module boundaries, dependency direction, SOLID, patterns and anti-patterns, API/REST design, auth boundaries, async design, testability and maintainability, applying project rules before generic best practices. Returns evidence-backed findings with severity and an Architecture Review Summary. Use proactively after implementation or before large refactors. Never modifies files.
    tools: Read, Grep, Glob, Bash, mcp__laravel-boost__search-docs, mcp__laravel-boost__application-info, mcp__laravel-boost__database-schema
    disallowedTools: Write, Edit, NotebookEdit, Agent, Skill, WebSearch, WebFetch
    model: opus
    effort: high
    color: purple
    hooks:
      PreToolUse:
        - matcher: "Bash" -> '"$CLAUDE_PROJECT_DIR"/.claude/hooks/readonly-bash-guard.sh architecture-reviewer inspect'
    ```
    System prompt outline (every user requirement mapped):
    1. **Role**: strictly read-only; must not create, modify or delete files, apply patches, stage or commit; never launches agents. Main job: architecture review with concrete evidence from the repo. Focus on architecture, not formatting, style or minor code quality.
    2. **When invoked**:
        1. Determine scope: whole codebase, a module/path, or a change (plan path and/or `git diff <base>`). If unclear, return "Clarification needed".
        2. Load project rules first, in this order, skipping missing files silently (never report them as missing): `CLAUDE.md`, `AGENTS.md`, `README.md`, `ARCHITECTURE.md`, ADRs (`docs/adr`, `doc/adr`, `.ai/docs/adr`, `adr/`), `.ai/rules/index.md` + matching rule files, `.ai/insights/backend.md` / `frontend.md`, `.claude/skills/laravel-best-practices/SKILL.md` and relevant `rules/*.md`, `inertia-vue-development`, `wayfinder-development`, `fortify-development` SKILL.md, and existing architecture-test configs (`deptrac.yaml`, `depfile.yaml`, `phparkitect.php`).
        3. Infer local conventions from sibling files (directory layout `app/Actions`, `app/Concerns`, `app/Http/{Controllers,Requests,Middleware}`, `routes/*.php`, `resources/js/{pages,components,composables,layouts,types}`).
        4. Map the architecture in scope: route → middleware → controller → request → action/service → model → migration; job/event/listener/scheduler; page → component → composable → Wayfinder route.
        5. Evaluate against the checklist (below), project rules taking precedence over generic advice.
        6. For each candidate finding, collect evidence; drop it if evidence is insufficient (move to "Not assessed / insufficient evidence").
        7. Return the report.
    3. **Precedence rule**: project rules and local conventions > skill guidance > generic literature. A deviation from generic best practice that a project rule or consistent local convention sanctions is not a finding (may be a LOW note only if it creates a concrete risk).
    4. **Checklist** (each item a short bullet with Laravel-specific cues):
        - Module/layer boundaries; dependency direction (domain must not depend on HTTP/Inertia; controllers thin; no Eloquent in Vue-facing DTO assumptions).
        - Separation of concerns; coupling and cohesion.
        - SOLID: SRP, OCP, LSP, ISP, DIP — judged by concrete change pressure, not by count of classes.
        - Design patterns: correct use and misuse; anti-patterns (God class/controller, anemic service sprawl, service locator via `app()`/`resolve()`, leaky abstraction, shotgun surgery, feature envy, cyclic deps, fat models with unrelated responsibilities).
        - Abstraction quality (premature/missing abstractions, interfaces with one implementation and no seam need).
        - Domain boundaries; persistence/data-access boundaries (Eloquent usage outside models/actions, query logic duplication, N+1 as architectural symptom only).
        - API architecture & best practices: REST resource naming, HTTP methods and status codes, idempotency (RFC 9110 §9.2.2; Idempotency-Key header is an expired Internet-Draft — convention only), validation (Form Requests), error format (RFC 9457 Problem Details for JSON APIs), pagination (AIP-158 / Zalando as references), versioning **only if external API consumers exist**, Eloquent API Resources per project rules.
        - Authentication/authorization boundaries: Fortify, middleware, policies/gates; authorization enforced server-side because Inertia props are client-visible; OWASP API Top 10 2023 (BOLA, broken function-level authorization) as a lens — deep security analysis belongs to the security reviewer.
        - Event-driven & async: jobs, queues, events/listeners, scheduler; idempotent jobs, retries/backoff, transactions vs dispatch (`afterCommit`), external Threads API calls not in request cycle where it matters.
        - Testability (seams for `Http::fake`, injected dependencies), maintainability and extensibility.
    5. **Pattern knowledge** (reference list): GoF (Strategy, Factory, Adapter, Facade, Decorator, Observer, Command, State, …), PoEAA (Repository, Unit of Work, Service Layer, Active Record, Data Mapper), CQRS, Dependency Injection, Ports & Adapters / Hexagonal.
    6. **Anti-overengineering rule** (YAGNI): never demand a pattern or a SOLID principle mechanically. Recommend a pattern only when a present force justifies it (multiple implementations, an external system to isolate, measured complexity); state the cost. Laravel specifics: Eloquent already provides Active Record and UoW-like behaviour — do not demand a Repository layer without forces; CQRS "be very cautious" (Fowler); interfaces for single implementations only at real seams (external APIs). Simple code that fits the current scope is a positive finding.
    7. **Evidence rule**: no finding without evidence: `file:line` references and a minimal quote (≤10 lines), or a command output (`git log`, `php artisan route:list`). Patterns across many files are reported once with representative locations. Speculation goes to "Not assessed".
    8. **Severity scale** with definitions: CRITICAL (breaks a core boundary or causes data/authz exposure or systemic failure; fix before merge), HIGH (significant coupling/boundary violation likely to cause defects or block planned features), MEDIUM (maintainability/extensibility cost with a clear fix), LOW (minor improvement, local).
    9. **Tools rule**: Bash only for inspection (guarded); prefer `Grep`/`Glob`; avoid `|` inside quoted grep patterns (the guard splits on it and blocks — use `-e` multiple times). deptrac/PHPArkitect: if configured and installed, mention their output; never install or add them.
    10. **Report format**:

        ````
        ## Architecture Review

        Scope: <whole codebase | paths | diff base..HEAD | plan path>
        Project rules applied: <files actually found and read>

        ### Findings
        #### [HIGH] AR-1: <short title>
        - Location: `<path>:<line>` (+ more)
        - Category: <boundary | dependency direction | SoC | coupling/cohesion | SOLID-<X> | pattern | anti-pattern | abstraction | domain | persistence | API | REST | auth boundary | async | testability | maintainability>
        - Problem: <what>
        - Evidence:
          ```php
          <minimal snippet>
        ````
        - Why it is a problem: <concrete consequence in this project>
        - Recommendation: <smallest change that resolves it; cost/trade-off>

        ### Not assessed / insufficient evidence
        - <area> — <why>

        ## Architecture Review Summary

        | CRITICAL | HIGH | MEDIUM | LOW |
        | -------- | ---- | ------ | --- |
        | n        | n    | n      | n   |

        Overall health: <healthy | acceptable with risks | at risk> — <1–2 sentences>
        Main risks:
        - <risk>

        Positive architectural decisions:
        - <decision> — `<evidence path>`

        ```
        Findings sorted by severity. "No findings" is a valid result.
        ```

    11. Closing line: reply in the language of the task.
- Skills: `laravel-best-practices` (its `rules/architecture.md` — actions, constructor injection, avoid `app()` — must be reflected as the project baseline), `inertia-vue-development`, `wayfinder-development`, `fortify-development` (listed as files to read, not invoked)
- Generate: none
- Tests: none: agent definition; dry run in Step 7.
- Verify: Step 7 YAML check + dry run; confirm a `Write` attempt is unavailable (tool not listed).

### Step 5: plan-verifier agent [backend]

- Files: create `.claude/agents/plan-verifier.md`
- Do: frontmatter:
    ```yaml
    name: plan-verifier
    description: Verifies finished work against every item of an approved Development Plan in .ai/docs/plans/ and the original request. Builds a traceability matrix with repository and test evidence per item, checks scope and approval constraints, and returns a verdict. Read-only; runs only non-mutating checks. Use after the implementer reports done. Reports gaps against the plan, not generic advice.
    tools: Read, Grep, Glob, Bash, mcp__laravel-boost__search-docs, mcp__laravel-boost__application-info, mcp__laravel-boost__database-schema
    disallowedTools: Write, Edit, NotebookEdit, Agent, Skill, WebSearch, WebFetch
    model: opus
    effort: high
    color: red
    hooks:
      PreToolUse:
        - matcher: "Bash" -> '"$CLAUDE_PROJECT_DIR"/.claude/hooks/readonly-bash-guard.sh plan-verifier verify'
    ```
    System prompt outline:
    1. **Role**: independent verifier; your job is to try to refute that the work meets the plan; read-only; never fix, never suggest refactors, never launch agents.
    2. **Input**: plan path (English base, never `_<lang>`), the original request text, optional git base ref (default: compare working tree + commits since the plan's creation with `git diff`/`git log`). Optional Implementation Report = list of claims to check, never evidence. Missing plan path → `blocked`.
    3. **When invoked**:
        1. Read the plan fully.
        2. Extract requirements into IDs: `AC-n` (section 1 acceptance criteria), `ST-n.x` (each step's Files / Do / Tests / Verify), `DC-n` (section 3 decisions and "Out of scope"), `AP-n` (section 6 "Requires approval": verify nothing listed there was done unless the task says it was approved), `RQ-n` (requirements from the original request not covered by the plan — report as plan gaps).
        3. Collect the change set: `git status`, `git diff --stat <base>`, `git diff <base> -- <path>`, `git log --oneline <base>..HEAD`.
        4. For each ID, find implementation evidence (`file:line`) with Read/Grep.
        5. Run each step's `Verify` command and the tests the plan names (allowed by the guard); also run the tests covering changed files. Record exact command and result. Compare with known pre-existing failures (insights) — a pre-existing failure is not a NOT MET unless the plan required fixing it.
        6. Scope check: files changed but not named by any step; dependency files (`composer.json`, `composer.lock`, `package.json`, `package-lock.json`) changed; new top-level folders; migrations that drop/rename columns or tables; generated files (`resources/js/routes|actions|wayfinder`) committed.
        7. Assign status per ID, then verdict.
        8. Return the Verification Report.
    4. **Status rules**: MET = implementation evidence + (where a test/verify is named) passing output; PARTIAL = some sub-items missing or verified only by reading; NOT MET = absent or contradicted, or the named check fails because of the change; NOT VERIFIABLE = cannot be checked with allowed tools (e.g. needs a browser, DB unreachable) — state what would verify it. Acceptance criteria written as Given/When/Then are checked scenario by scenario. **Formatting**: a step `Verify` naming `vendor/bin/pint --dirty --format agent` is checked with `vendor/bin/pint --test --format agent <that step's changed PHP files>`; a failure makes that verify sub-item PARTIAL, not NOT MET. Formatting failures on changed PHP files not named by any plan item go under "Project rule checks" as a gap against the project rule "run `vendor/bin/pint --dirty --format agent` before finalizing"; they never make an AC NOT MET and can lower the verdict to PASS WITH GAPS at most. Do not check files outside the change set.
    5. **Anti-noise rules**: report only gaps that affect correctness, the plan's items or the stated request; no style preferences, no generic best practices, no "consider adding" unless a plan item demands it; if everything is met, say so — a clean PASS is a valid result. Do not trust claims without evidence ("tests pass" must be your own command output).
    6. **Allowed Bash** (mirror of the `verify` profile): inspection commands; `php artisan test --compact <paths|--filter>`; `vendor/bin/phpunit`; `composer types:check`; `npm run types:check`; `npm run check`; `vendor/bin/pint --test [--dirty] [--format agent] [<paths>]` (read-only format check; prefer explicit changed paths over `--dirty`, because `--dirty` misses committed work); `docker compose exec laravel.test <the same>` when the local DB is unreachable. Never Pint without `--test`, never `pint --repair`, never `composer test`, never `wayfinder:generate`, `migrate`, `build`, installs, or git writes. State in the prompt that the Pint `--test` permission is an explicit user decision (2026-10-21) that overrides the `CLAUDE.md` line "Do not run `vendor/bin/pint --test --format agent`" for this read-only agent only. Avoid `|` inside quoted patterns (guard).
    7. **Verification Report** format:
        ```
        ## Verification Report

        Plan: .ai/docs/plans/<file>.md
        Change set: <base>..HEAD (+ uncommitted: yes/no), <N> files
        Verdict: PASS | PASS WITH GAPS | FAIL

        ### Traceability matrix
        | ID | Requirement (short) | Status | Implementation evidence | Verification evidence |
        |---|---|---|---|---|
        | AC-1 | ... | MET | `app/...:42` | `php artisan test --compact --filter=...` → 3 passed |

        ### Gaps (NOT MET / PARTIAL)
        - <ID> — <what is missing> — <evidence> — <impact on the requirement>

        ### Not verifiable
        - <ID> — <why> — <what would verify it>

        ### Scope check
        - Files changed outside the plan: <list or "None.">
        - Dependencies changed: <yes/no + files>
        - Items under "Requires approval" done without approval: <list or "None.">

        ### Project rule checks
        - Formatting (`vendor/bin/pint --test --format agent <changed PHP files>`): <pass | files failing>, or "No PHP files changed."

        ### Checks run
        | Command | Result | Pre-existing failures |
        |---|---|---|

        ### Plan gaps
        - <requirement from the original request missing in the plan>, or "None."
        ```
        Verdict rules: FAIL if any AC is NOT MET or any approval/scope violation; PASS WITH GAPS if only PARTIAL / NOT VERIFIABLE, non-AC gaps or project-rule (formatting) gaps; PASS otherwise.
    8. Closing line: reply in the language of the task.
- Skills: `testing-best-practices` (`rules/review.md` is read to judge whether named tests actually cover the stated behaviour — only for plan-named tests, not as generic advice)
- Generate: none
- Tests: none: agent definition; dry run in Step 7.
- Verify: Step 7 YAML check + dry run.

### Step 6: README update [backend]

- Files: modify `.claude/agents/README.md`
- Do:
    - Workflow diagram: after `implementer ──► Implementation Report` add the optional branch `──► [optional, on request] test-writer (tests/ + database/factories/) ──► Test Report` (drawn as optional, e.g. bracketed or dashed, per Q5) and `──► plan-verifier ──► Verification Report (PASS / PASS WITH GAPS / FAIL)`; replace "architecture / security review (separate agents)" with `architecture-reviewer ──► Architecture Review` (keep "security review (separate agent)" as text); add a line `architecture-reviewer: also on demand for the whole codebase; read-only`.
    - Overview table rows:
        - test-writer | Writes backend and Inertia UI tests per testing-best-practices (on request) | sonnet / medium | `tests/` and `database/factories/` (new and existing factories) | plan path or files in scope | Test Report
        - architecture-reviewer | Evidence-based architecture review | opus / high | nothing | scope (codebase, paths, diff, plan) | findings + Architecture Review Summary
        - plan-verifier | Verifies work against every plan item | opus / high | nothing | plan path + original request (+ base ref) | Verification Report
    - Per-agent sections in the existing format (Owns / Does not / Tools / Denied / Skills / Guards / Input / Output).
    - Sources table: add rows for read-only enforcement (no edit tools + Bash guard; `permissionMode: plan` not relied upon), plan-verifier's `pint --test` exception to `CLAUDE.md` (user decision 2026-10-21; read-only form only), test-writer's factory access (user decision 2026-10-21), `skills:` preloading for test-writer, verifier on a different model (self-preference bias papers), verifier ground truth and anti-noise prompt (Anthropic best practices / Building effective agents), traceability matrix (design choice), architecture references (ATAM, YAGNI, CQRS, PoEAA, Hexagonal, RFC 9110, RFC 9457, Idempotency-Key draft, Zalando, AIP-158, Microsoft, OWASP API Top 10, deptrac, PHPArkitect), frontend testing references (Inertia v3 testing, Vue testing guide, Testing Library principles, Dusk). Rename the heading to "Sources behind the agents".
    - Run `npm run check` only if `.md` files are in its scope (commit `ce812bc` "Format markdown files to pass vp check" suggests they are): fix formatting with `npm run check:fix` limited to the changed files if it fails.
- Skills: none
- Generate: none
- Tests: none: documentation.
- Verify: `npm run check`

### Step 7: Verify hooks and agents [backend]

- Files: none created (use the scratchpad for any sample JSON files, not the repo)
- Do:
    1. Syntax and permissions: `bash -n .claude/hooks/<each new script>.sh`; `ls -l .claude/hooks` shows `x` on all new scripts.
    2. Hook table — pipe JSON and check exit codes, e.g. `echo '{"tool_input":{"command":"git diff --stat"}}' | CLAUDE_PROJECT_DIR=$PWD .claude/hooks/readonly-bash-guard.sh architecture-reviewer inspect; echo $?`. Expected:

        | Script / profile       | Input                                                                                                         | Exit |
        | ---------------------- | ------------------------------------------------------------------------------------------------------------- | ---- |
        | readonly inspect       | `git diff --stat`                                                                                             | 0    |
        | readonly inspect       | `grep -rn Foo app`                                                                                            | 0    |
        | readonly inspect       | `php artisan test`                                                                                            | 2    |
        | readonly inspect       | `echo x > a.txt`                                                                                              | 2    |
        | readonly inspect       | `cat a $(rm -rf b)`                                                                                           | 2    |
        | readonly inspect       | `find . -delete`                                                                                              | 2    |
        | readonly inspect       | `git commit -m x`                                                                                             | 2    |
        | readonly inspect       | `vendor/bin/pint --test`                                                                                      | 2    |
        | readonly verify        | `php artisan test --compact --filter=DashboardTest`                                                           | 0    |
        | readonly verify        | `vendor/bin/phpunit tests/Feature`                                                                            | 0    |
        | readonly verify        | `composer types:check`                                                                                        | 0    |
        | readonly verify        | `npm run types:check`                                                                                         | 0    |
        | readonly verify        | `npm run check`                                                                                               | 0    |
        | readonly verify        | `npm run check:fix`                                                                                           | 2    |
        | readonly verify        | `composer test`                                                                                               | 2    |
        | readonly verify        | `vendor/bin/pint --test`                                                                                      | 0    |
        | readonly verify        | `vendor/bin/pint --test --format agent app/Models/User.php`                                                   | 0    |
        | readonly verify        | `vendor/bin/pint --dirty --test`                                                                              | 0    |
        | readonly verify        | `vendor/bin/pint`                                                                                             | 2    |
        | readonly verify        | `vendor/bin/pint --dirty --format agent`                                                                      | 2    |
        | readonly verify        | `vendor/bin/pint app/Models/User.php`                                                                         | 2    |
        | readonly verify        | `vendor/bin/pint --test --repair`                                                                             | 2    |
        | readonly verify        | `vendor/bin/pint --test --config x.json`                                                                      | 2    |
        | readonly verify        | `docker compose exec laravel.test vendor/bin/pint --test`                                                     | 0    |
        | readonly verify        | `docker compose exec laravel.test vendor/bin/pint`                                                            | 2    |
        | readonly verify        | `php artisan migrate`                                                                                         | 2    |
        | readonly verify        | `npm install vitest`                                                                                          | 2    |
        | readonly verify        | `docker compose exec laravel.test php artisan test --compact`                                                 | 0    |
        | readonly verify        | `docker compose exec laravel.test php artisan migrate`                                                        | 2    |
        | readonly (bad profile) | `ls` with profile `foo`                                                                                       | 2    |
        | test-writer bash       | `php artisan make:test --phpunit Settings/ThreadsConnectionTest --no-interaction`                             | 0    |
        | test-writer bash       | `php artisan make:factory PostFactory --model=Post --no-interaction`                                          | 0    |
        | test-writer bash       | `php artisan make:factory Foo --force`                                                                        | 2    |
        | test-writer bash       | `php artisan make:controller Foo`                                                                             | 2    |
        | test-writer bash       | `php artisan make:migration add_x`                                                                            | 2    |
        | test-writer bash       | `composer types:check`                                                                                        | 0    |
        | test-writer bash       | `vendor/bin/pint --format agent tests/Feature/DashboardTest.php`                                              | 0    |
        | test-writer bash       | `vendor/bin/pint --format agent database/factories/UserFactory.php`                                           | 0    |
        | test-writer bash       | `vendor/bin/pint --format agent database/migrations/x.php`                                                    | 2    |
        | test-writer bash       | `vendor/bin/pint --format agent database/factories/../../app/Models/User.php`                                 | 2    |
        | test-writer bash       | `vendor/bin/pint --format agent app/Models/User.php`                                                          | 2    |
        | test-writer bash       | `vendor/bin/pint --dirty --format agent`                                                                      | 2    |
        | test-writer bash       | `composer require --dev pestphp/pest`                                                                         | 2    |
        | test-writer write      | `{"tool_input":{"file_path":"tests/Feature/FooTest.php"}}`                                                    | 0    |
        | test-writer write      | `{"tool_input":{"file_path":"app/Models/User.php"}}`                                                          | 2    |
        | test-writer write      | `{"tool_input":{"file_path":"tests/../app/x.php"}}`                                                           | 2    |
        | test-writer write      | `{"tool_input":{"file_path":"database/factories/UserFactory.php"}}`                                           | 0    |
        | test-writer write      | `{"tool_input":{"file_path":"database/factories/PostFactory.php"}}` (new file; the hook checks the path only) | 0    |
        | test-writer write      | `{"tool_input":{"file_path":"database/../app/x.php"}}`                                                        | 2    |
        | test-writer write      | `{"tool_input":{"file_path":"database/migrations/x.php"}}`                                                    | 2    |
        | test-writer write      | `{"tool_input":{"file_path":"database/seeders/x.php"}}`                                                       | 2    |

    3. Confirm A1 and A4: on a clean tree run `npm run check`, `php artisan test --compact tests/Feature/DashboardTest.php` and `vendor/bin/pint --test --format agent app/Models/User.php`, then `git status --porcelain` must show no new changes.
    4. Frontmatter: compare each new agent file's `---` block with `planner.md` / `translator.md` (same key order, `hooks` indentation, quoted `command`). Then start a new Claude Code session and confirm the three agents appear in `/agents` with the expected tools and model.
    5. Dry runs (manual, by the user or the main session):
        - `architecture-reviewer`: "Review `app/Http/Controllers/Settings` and `routes/settings.php`." Expect report format, evidence per finding, Summary table; then ask it to "create a file notes.md" — expect refusal / no Write tool.
        - `plan-verifier`: run against this plan after implementation. Expect a matrix covering AC-1…AC-10 and ST-1…ST-7 with evidence and a "Project rule checks" line from `vendor/bin/pint --test`; ask it to run `vendor/bin/pint` (without `--test`) — expect the guard block.
        - `test-writer`: "Add tests for `routes/settings.php` profile page Inertia props." Expect only `tests/` (and, if needed, `database/factories/`) changes (`git status`), Inertia assertions, a "Coverage gaps" entry for client-side behaviour, and a "Factories added or changed" section; ask it to install vitest — expect the guard block and a gap report; ask it to edit a migration — expect the write-guard block.
- Skills: none
- Generate: none
- Tests: the hook table above (manual).
- Verify: all rows match; `git status --porcelain` shows only the intended new/changed files under `.claude/` (and the plan), plus `tests/` / `database/factories/` files from the test-writer dry run if it was performed (revert or keep per user).

## 5. Order and dependencies

- Step 3 depends on Step 2 (hooks referenced in frontmatter must exist and be executable, otherwise hook calls fail instead of blocking).
- Steps 4 and 5 depend on Step 1 for the same reason.
- Step 6 depends on Steps 3–5 (README describes final frontmatter).
- Step 7 runs last; its hook-table part can run right after Steps 1–2.
- Steps 1 and 2 are independent of each other.

## 6. Requires approval

- Nothing for this plan (no dependencies, no new base folders, no migrations; `.claude/agents` and `.claude/hooks` already exist).
- Future, not part of this plan (declined for now by user decision 2026-10-21, Q1): installing frontend/browser test tooling (vitest + `@vue/test-utils` and/or `@testing-library/vue`, or `laravel/dusk`, or Playwright) — new dependencies, require explicit user approval (`CLAUDE.md`, `testing-best-practices`).

## 7. Risks and open questions

- Open questions: none (Q1–Q5 resolved by the user on 2026-10-21, recorded in section 3).
- Risk: the `plan-verifier` prompt and `verify` guard profile deliberately override the `CLAUDE.md` line "Do not run `vendor/bin/pint --test --format agent`" by explicit user decision (2026-10-21). `CLAUDE.md` is not edited, so other agents and the main session still read the prohibition; a future reader may see this as a contradiction. Mitigation: the exception is stated in the agent prompt and the README sources table, and is limited to the `--test` form in a read-only agent.
- Risk: Pint's flag surface may grow; the `verify` profile allowlists flags (`--test`, `--dirty`, `--format agent`) rather than denylisting, so new write-capable flags (like today's `--repair`) stay blocked.
- Risk: test-writer changing an existing factory's `definition()` can silently change data for unrelated tests (e.g. a new non-null default, a changed email format). Mitigations: prefer new states, mandatory listing in the Test Report, running every test that uses the factory, and the plan-verifier scope check will show the factory file as changed.
- Risk: a new factory may need `HasFactory` or other model changes the test-writer may not make; the factory then fails at runtime until the implementer acts ("Needs from implementer").
- Risk: the Bash guards split on `|` even inside quoted arguments (observed while planning: `grep -rn "a\|b"` was blocked by `planner-bash-guard.sh`). Fail-closed, so safe, but agents must be told to use `-e` or the `Grep` tool.
- Risk: allowlist guards are not a sandbox: `php artisan test` executes project test code, which could in principle write files. Accepted because tests are part of the repo under review; residual side effects limited to the `testing` DB, `storage/` and PHPUnit cache (A4).
- Risk: `effort` and unknown frontmatter fields are silently ignored if unsupported (A2) — a typo in a field name would go unnoticed; the Step 7 `/agents` check mitigates only partially.
- Risk: the known 18 failing auth tests can mask regressions in auth-related plans; both test-writer and plan-verifier must compare against a baseline.
- Risk: duplicating the guard core in `test-writer-bash-guard.sh` and `readonly-bash-guard.sh` (and the planner's) means fixes must be applied in several places; consolidating via a sourced function file is suggested as a follow-up.

## 8. Notes for architecture and security review

- Hook scripts are the security boundary for three agents: review the segment parser for bypasses (newlines in commands, `env`/`xargs`/`bash -c` as first word — must be denied, quoted `;`, `docker compose exec` re-check recursion, path checks for Pint arguments like `tests/../app`).
- `test-writer-write-guard.sh`: path normalisation and `..` handling for both allowed roots (`tests/`, `database/factories/`); prefix matching must include the trailing slash so `database/factoriesX/` or `database/factories.php` is denied; symlinks inside the allowed roots (out of scope unless present).
- `test-writer` factory access: factories run arbitrary PHP during tests and are in PHPStan scope; review that the prompt keeps app code, migrations and seeders out of reach and that `make:factory` accepts only `<Name>`, `--model=`, `--no-interaction` (no `--force`, no `--all`-style flags).
- `readonly-bash-guard.sh` `verify` Pint rule: flag parsing must require `--test`, reject `--repair` and unknown flags in any position (including `--format=agent` vs `--format agent` forms and flags after paths), and apply the same rule inside `docker compose exec` re-checks. This rule is an explicit, user-approved exception to `CLAUDE.md`.
- `plan-verifier` running `php artisan test` against `DB_DATABASE=testing`: confirm tests cannot hit a non-testing database (phpunit.xml env) — relevant if `.env` points elsewhere.
- `architecture-reviewer` / `plan-verifier` have `Skill` disallowed and no edit tools; confirm no MCP tool in their list can write (Boost `search-docs`, `application-info`, `database-schema` are read-only).

## Sources

- Claude Code sub-agents (fetched 2026-10-21): https://code.claude.com/docs/en/sub-agents
- Claude Code best practices: https://code.claude.com/docs/en/best-practices
- Anthropic, Building effective agents: https://www.anthropic.com/engineering/building-effective-agents
- Panickssery et al., LLM evaluators recognize and favor their own generations: https://arxiv.org/abs/2404.13076
- Zheng et al., Judging LLM-as-a-Judge with MT-Bench: https://arxiv.org/abs/2306.05685
- Fowler, Given When Then: https://martinfowler.com/bliki/GivenWhenThen.html
- Inertia v3 testing: https://inertiajs.com/docs/v3/advanced/testing
- Vue testing guide: https://vuejs.org/guide/scaling-up/testing.html
- Testing Library guiding principles: https://testing-library.com/docs/guiding-principles
- Laravel Dusk 13.x: https://laravel.com/docs/13.x/dusk
- SEI ATAM: https://sei.cmu.edu/library/architecture-tradeoff-analysis-method-collection/
- Fowler, Yagni: https://martinfowler.com/bliki/Yagni.html
- Fowler, CQRS: https://martinfowler.com/bliki/CQRS.html
- Fowler, PoEAA catalog: https://martinfowler.com/eaaCatalog/
- Cockburn, Hexagonal architecture: https://alistair.cockburn.us/hexagonal-architecture/
- RFC 9110 (HTTP semantics, §9.2.2 idempotent methods): https://www.rfc-editor.org/rfc/rfc9110
- RFC 9457 (Problem Details): https://www.rfc-editor.org/rfc/rfc9457
- Idempotency-Key header (expired Internet-Draft, convention only): https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/
- Zalando RESTful API guidelines: https://opensource.zalando.com/restful-api-guidelines/
- Google AIP-158 pagination: https://google.aip.dev/158
- Microsoft API guidelines: https://github.com/microsoft/api-guidelines
- OWASP API Security Top 10 2023: https://api-security.owasp.org/editions/2023/en/0x00-header
- deptrac: https://github.com/deptrac/deptrac
- PHPArkitect: https://github.com/phparkitect/arkitect

## Changelog

- 2026-10-21 19:07 — created
- 2026-10-21 19:24 — recorded user decisions on Q1–Q5 in section 3 (Q1 no frontend tooling; Q2 test-writer gets full `database/factories/` access; Q3 plan-verifier may run `vendor/bin/pint --test` as a read-only exception to `CLAUDE.md`; Q4 effort `high`; Q5 test-writer on request only); updated acceptance criteria, Steps 1, 2, 3, 5, 6, 7, sections 7 and 8 accordingly
