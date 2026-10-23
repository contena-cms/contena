# Contena

Contena is an open-source content management platform derived from the Contena CMS baseline.
It keeps the API-first architecture with three distinct APIs (Admin, Channel, Sync) and a built-in Twig frontend,
but it carries no commerce layer: there are no orders, carts, payments, prices, stock, shipping or tax.
It uses a custom Data Abstraction Layer instead of a traditional ORM, an event-driven extension system replacing decorators, and Flow Builder for automation.

Content is modelled around blogs and CMS pages: a blog post is the publishing unit, blog streams are saved
filter sets used for listings, and comments attach to blog posts.

## Project Structure

```
contena/
├── src/
│   ├── Core/                     # Business logic & framework
│   ├── Administration/           # Admin UI
│   ├── Frontend/                 # Frontend
│   └── Elasticsearch/            # Search integration
├── tests/                        # Test suites
└── bin/console                   # CLI commands
```

## Technology Stack

- **Backend**: PHP 8.5+, Symfony 7, Doctrine DBAL 4
- **Frontend Admin**: Vue 3, Pinia + Vuex, Vite, TypeScript
- **Frontend**: Twig, Bootstrap 5, Webpack 5
- **Database**: MySQL 8+ / MariaDB 10.11+
- **Search**: OpenSearch 2 / Elasticsearch 8
- **Cache**: Redis (optional), Symfony Cache
- **Testing**: PHPUnit, PHPStan, Jest, Playwright

## Contena Architecture

### NOT Standard Symfony/Doctrine
- **NO Doctrine ORM** - Uses custom Data Abstraction Layer (DAL)
- **NO QueryBuilder** - Use `Criteria` API instead
- **NO Doctrine Annotations** - Use `EntityDefinition` classes
- **NO Doctrine Repositories** - Use `EntityRepository` with DAL

### Extension Pattern Priority
1. **Prefer Events** - EventSubscriberInterface for most extensibility
2. **Use Decorators Only When** - Event timing doesn't fit

### Three Distinct APIs
- `/api/` - Admin API (full CRUD, admin operations)
- `/channel-api/` - Channel API (frontend-facing, channel scoped)
- `/api/_action/sync` - Sync API (bulk operations)

## AI Skills

This repo ships Agent Skills under `.agents/skills/`, with `.claude/skills` as a symlink for Claude Code compatibility.
Skills normally match their `description` against the task — best-effort and model-decided, **not guaranteed** — while skills with unattended CI twins require explicit invocation.
The mandatory steps below are therefore stated here, in the always-loaded file, so they apply even when no skill is triggered.

### Definition of Done — mandatory for every significant change

Before you commit or hand work back:
- **Behaviour change ⇒ tests are required.** Admin JS/TS/Vue → follow `contena-admin-js`; PHP → `contena-phpunit-tests`.
  Twig-only template changes follow the rule below. Style-only, snippet/translation, and docs-only changes do not need tests;
  still add one when it is useful and follows an established pattern.
- **Twig-only template changes ⇒ never add PHP integration tests just to render or assert Twig output.**
  A direct render without a Frontend request can cache empty request-dependent Twig globals in the shared test kernel and break unrelated later tests.
  Run the Frontend Twig lint; use browser/acceptance coverage when the rendered behaviour needs testing.
- **Writing a PR title or description? → follow `contena-pr-hygiene`** — the Contena PR template is required, not a generic one.
- **Behavioural change, feature, deprecation, or config change? → check `contena-release-docs`** for RELEASE_INFO / UPGRADE entries.
- **Touching `.github/workflows/`, `.github/actions/`, or `.github/bin/`? → follow `.github/AGENTS.md`** when that directory is present in your checkout — a CI job must never report success without proving the work ran.
  The `.github/` tree is ported separately from upstream, so it may be absent on a feature branch.
- **Asked to commit changes? Commit with a conventional message incl. scope**, e.g. `feat(administration): …`. Never commit without explicitly beeing told to do so.
  Commit titles and bodies are written in English in every Contena repository.
- **After review feedback or CI failures**, create a follow-up commit; do not amend or force-push unless explicitly asked.
- **Lint every file you touched** per the File Linting table below.

When a task matches a skill, open `.agents/skills/<name>/SKILL.md` and follow it **before** implementing.

### Guidance Skills

- `contena-knowledge-capture` — saving durable knowledge; routing it to AGENTS, coding guidelines, README, ADR, skills, or local notes.
- `contena-change-scope` — root-cause analysis, boyscouting, and cleanup scope.
- `contena-release-docs` — release notes, upgrade notes, developer-facing changelog decisions.
- `contena-pr-hygiene` — PR templates, conventional titles, review follow-up commits.
- `contena-php-code` — PHP architecture, API schema, migrations, deprecations, BC-sensitive code.
- `contena-admin-js` — Administration JavaScript, TypeScript, Vue, ACL, Jest.
- `contena-phpunit-tests` — PHPUnit test structure, fixtures, feature flags, coverage, data providers.

Skills can have an optional unattended twin via [GitHub Agentic Workflows](https://github.com/githubnext/gh-aw) at `.github/workflows/<name>.md` + `.github/aw/<name>-policy.md`.
Editing or compiling these workflows requires the `gh aw` CLI extension.
The `.github/` tree is ported from upstream in a separate step, so these twins are only available once that port has landed.

To add a new skill (interactive or unattended), follow the checklist in [`coding-guidelines/core/agent-skills.md`](coding-guidelines/core/agent-skills.md).

## Subtree Guidance

- PHP/server code: use the `contena-php-code` skill when the task touches PHP architecture, API schema, migrations, deprecations, or BC-sensitive code.
- Administration JS/TS/Vue code: detailed guidance starts at `src/Administration/Resources/app/administration/AGENTS.md`; use the `contena-admin-js` skill for Admin coding rules.
- PHPUnit tests: use the `contena-phpunit-tests` skill.
- CI workflows, composite actions, and automation scripts: local rules in `.github/AGENTS.md`, rationale and examples in `coding-guidelines/core/ci-workflows.md`.
- More specific nested `AGENTS.md` files add local rules for their subtree.

## Coding Guidelines

**MANDATORY**: All code must follow the guidelines in `coding-guidelines/`.

### Writing Markdown

This rules apply to every file with the ending `*.md`.

- Put a blank line before and after every heading
- Put only one sentence into one line.
  Add a line break after the sentence ends.
  If a sentence is getting too long, it is possible to add a line break after a comma or semicolon.
- Indent continuation lines inside list items to align with the item's content (2 spaces for - ), instead of relying on lazy continuation lines.
- Do not add a line break in the middle of a sentence, without a punctuation sign before it.
- Add an empty line before a code example enclosed with code fences.

## Snippets & Translations

Contena ships English and Simplified Chinese only.
Upstream German resources were converted to Simplified Chinese and are not kept as a separate `de` copy,
so there is no informal/formal register split to maintain between the two UIs.
Write Chinese snippets in a neutral register and never mix "您" and "你" inside one file.

Snippet files are named by language, not locale: `en.json` / `zh.json` in the Administration, `frontend.en.json` / `frontend.zh.json` in the Frontend.
Locale-specific names such as `en-GB.json` or `frontend.de-DE.json` are the legacy scheme;
do not create them, and do not use them in tests, fixtures, or examples unless the test deliberately covers the legacy loader.

Pluralized snippets use two forms, `singular | plural`, when the zero case reads the same as the plural case.
Only add the explicit three-form `zero | singular | plural` variant when the zero case needs its own wording.
Simplified Chinese does not inflect for plural, so both forms usually read the same.

```json
"buttonAddBlogs": "Add {count} blog | Add {count} blog"
```

## File Linting

**MANDATORY**: All code must be linted according to the following table.

| File Type              | Check Command                 | Fix Command                               |
|------------------------|-------------------------------|-------------------------------------------|
| **PHP** (.php)         | `composer cs`                 | `composer cs-fix`                         |
| **PHP** (types)        | `composer phpstan`            | N/A - must fix manually                   |
| **JS/TS/Vue** (Admin)  | `composer eslint:admin`       | `composer eslint:admin:fix`               |
| **JS/TS** (Frontend)   | `composer eslint:frontend`    | `composer eslint:frontend:fix`            |
| **SCSS**               | `composer stylelint`          | `composer stylelint:[admin\|frontend]:fix` |
| **Twig** (Frontend)    | `composer ludtwig:frontend`   | `composer ludtwig:frontend:fix`           |
| **Snippets**           | `composer translation:lint`   | Manual fix required                       |
| **Prettier** (Admin)   | `composer format:admin`       | `composer format:admin:fix`               |
| **GitHub Actions**     | `composer lint:actions`       | `composer lint:actions:fix`               |
