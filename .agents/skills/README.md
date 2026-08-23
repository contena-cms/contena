# Contena AI Skills

Portable AI capabilities packaged in the [Anthropic Agent Skills](https://agentskills.io) format. `.agents/skills` is the canonical location; `.claude/skills` is a symlink for Claude Code. Offered to Agent-Skills-compatible runtimes; a skill is invoked when its `description` matches the task (best-effort, model-decided — not guaranteed). Mandatory steps live in `AGENTS.md` so they apply even when no skill triggers.

## Available skills

| Skill | Trigger phrases (examples) | What it does |
|---|---|---|
| [`ct-bugfixer`](ct-bugfixer/SKILL.md) | "fix issue #X", "create a bugfix PR", "handle qi/ct-bugfixer", "improve Bugfixer PR #N", "/ct-bugfixer improve" | Diagnoses a Contena issue or Bugfixer PR feedback, applies a focused fix when appropriate, validates narrowly, and reports the change or no-op decision. |
| [`ct-triage`](ct-triage/SKILL.md) | "triage issue #X", "classify this bug", "is this a duplicate", "what severity is #N" | Triages a Contena 6 GitHub bug issue — identifies the affected code area, checks for related fixes or duplicates, and emits a Markdown summary (disposition, severity, suggested labels, confidence, evidence). |
| [`ct-review`](ct-review/SKILL.md) | "review PR #X", "security review this branch", "review my staged changes" | Reviews a Contena 6 PR or local diff through calibrated persona lenses, dedupes findings, and emits Markdown or schema-valid JSON depending on invocation mode. |
| [`nightly-triage`](nightly-triage/SKILL.md) | "triage this nightly run", "identify all failing tests from <Actions run link>", "group the integration-major failures into issues" | Sweeps a failing multi-job PHPUnit CI run — extracts failing tests per shard, clusters them into root causes (with local Docker verification), and files per-domain issues plus a parent tracking issue, routing collateral failures to the root-cause owner. |
| [`contena-knowledge-capture`](contena-knowledge-capture/SKILL.md) | "save this for later", "preserve this knowledge", "where should this information live" | Routes durable Contena knowledge to the right home without duplicating rules or adding mechanical stubs. |
| [`contena-change-scope`](contena-change-scope/SKILL.md) | "fix this bug", "apply review feedback", "should we clean this up too" | Keeps bug fixes and cleanups scoped to the root cause while catching safe nearby consistency work. |
| [`contena-release-docs`](contena-release-docs/SKILL.md) | "does this need release notes", "add upgrade docs", "public API changed" | Decides whether a change needs developer-facing release info, upgrade notes, or API schema docs. |
| [`contena-pr-hygiene`](contena-pr-hygiene/SKILL.md) | "create a PR", "update the PR", "address review feedback" | Applies Contena PR template, title, and follow-up commit conventions. |
| [`contena-php-code`](contena-php-code/SKILL.md) | "edit PHP code", "add migration", "add API route", "deprecate this" | Applies Contena PHP architecture, public surface, migration, API schema, and deprecation rules. |
| [`contena-admin-js`](contena-admin-js/SKILL.md) | "edit Administration", "Admin UI", "Vue component", "Jest spec" | Applies Contena Administration JS/TS/Vue architecture, ACL, Jest, and linting conventions. |
| [`contena-phpunit-tests`](contena-phpunit-tests/SKILL.md) | "write PHPUnit tests", "add data provider", "feature flag test" | Applies Contena PHPUnit structure, fixtures, feature-flag, DBAL, coverage, and data-provider rules. |

## How auto-loading works

When you start a session in this repo with an Agent-Skills-compatible runtime:

1. The runtime scans `.agents/skills/` for `SKILL.md` files, or `.claude/skills/` in Claude Code.
2. Each skill's `description` frontmatter is matched against your message.
3. If a skill matches, its body (plus on-demand `references/`) is injected into the agent's context.

No flags, no plugins — drop into a session and just describe what you want.

## Unattended twins

A skill can additionally run unattended in CI via [GitHub Agentic Workflows](https://github.com/githubnext/gh-aw): a workflow source at `.github/workflows/<name>.md` plus a `runtime-import`-ed policy fragment at `.github/aw/<name>-policy.md`. When both surfaces exist, the shared rubric lives in `.github/aw/shared/<name>-policy.md` and is loaded by both surfaces — they cannot drift on the policy.

Current twins: `ct-triage`, `ct-bugfixer`, and `ct-review` (see `.github/workflows/<name>.md` + `.github/aw/<name>-policy.md`).

For the gh aw setup, secrets, and registration mechanics, see [`.github/aw/README.md`](../../.github/aw/README.md).

## Adding a new skill

See the checklist in [`coding-guidelines/core/agent-skills.md`](../../coding-guidelines/core/agent-skills.md) — required frontmatter, references layout, optional gh aw twin, registration trick, and engine pin convention.
