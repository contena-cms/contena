# Contena

Contena is a general-purpose, API-first administration platform exposing three distinct APIs (Administration, Channel, Sync) alongside a built-in Twig-based frontend. It provides reusable identity, ACL, configuration, media, plugin, data-dictionary, localization, notification, and workflow infrastructure through its custom Data Abstraction Layer, event-driven extension system, Vue-based Administration, and Frontend.

## Project Structure

```
contena/
├── src/
│   ├── Core/                     # Business logic & framework
│   ├── Administration/           # Admin UI
│   └── Frontend/                 # Frontend
├── tests/                        # Test suites
└── bin/console                   # CLI commands
```

## Technology Stack

- **Backend**: PHP 8.5+, Symfony 7, Doctrine DBAL 4
- **PHP language**: Prefer stable features available in PHP 8.5, such as property hooks
- **Frontend Admin**: Vue 3, Pinia + Vuex, Vite, TypeScript
- **Frontend**: Twig, Bootstrap 5, Vite
- **Database**: MySQL 8+ / MariaDB 10.11+
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
- `/api/` - Administration API (full CRUD, administrative operations)
- `/channel-api/` - Channel API (member-facing, API channels and HTML frontend)
- `/api/_action/sync` - Sync API (bulk operations)

### Platform And Tenant Data Modes
- Tenancy is optional. A system with no tenant rows must install and run with the complete platform feature set.
- `TenantField` marks data that can belong either to the platform (`tenant_id = NULL`) or to one tenant (`tenant_id = <UUID>`). Entities without `TenantField` are shared platform infrastructure.
- `Context::createDefaultContext()` is platform-scoped: it reads and writes only platform-owned rows. This is the safe default for business workflows and for systems that run without tenants.
- `Context::createGlobalContext()` is the platform management view: it reads across platform and tenant data but may write only platform-owned rows. `Context::createCLIContext()` uses this global scope. Never use a global Context to create, update, or delete tenant-owned business data.
- To administer tenant-owned data from a platform workflow, resolve the target tenant first and perform the write with `Context::createTenantContext($tenantId)`. Tenant Contexts automatically inject/filter that tenant and must never access platform-owned or another tenant's rows.
- Background maintenance that covers every business-data scope must use `TenantScopeContextProvider`, which yields the platform first and tenants in keyset-paginated batches. Consume its generator as a stream; do not cache or materialize the complete tenant Context list.
- Tenant ownership is immutable. Moving data between platform and tenant scopes, or between tenants, requires an explicit platform migration/tool rather than a normal DAL update.
- Preserve the resolved tenant through async messages, scheduled work, cache keys/tags, logs, search indices, filesystem paths, and nested DAL writes.
- Every entity definition containing a `TenantField` must test the four-context read and write matrix: Default/platform, target tenant, another tenant, and Global. Verify both platform-owned and tenant-owned fixtures; use the target tenant Context for tenant writes, and prove that Default, another tenant, and Global cannot mutate the target tenant's data. Infrastructure that derives or transports such entity data must repeat the matrix against its own read and write paths.

## AI Skills

This repo ships Agent Skills under `.agents/skills/`, with `.claude/skills` as a symlink for Claude Code compatibility. Skills are **offered** to the agent and invoked when the task matches their `description` — best-effort and model-decided, **not guaranteed**. The mandatory steps below are therefore stated here, in the always-loaded file, so they apply even when no skill is triggered.

### Definition of Done — mandatory for every change

Before you commit or hand work back:
- **Behaviour change ⇒ tests are required.** Admin JS/TS/Vue → follow `contena-admin-js`; PHP → `contena-phpunit-tests`. Style-only, snippet/translation, and docs-only changes do not need tests; still add one when it is useful and follows an established pattern.
- **Writing a PR title or description? → follow `contena-pr-hygiene`** — the Contena PR template is required, not a generic one.
- **Behavioural change, feature, deprecation, or config change? → check `contena-release-docs`** for RELEASE_INFO / UPGRADE entries.
- **Commit with a conventional message incl. scope**, e.g. `feat(administration): …`.
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

Skills can have an optional unattended twin via [GitHub Agentic Workflows](https://github.com/githubnext/gh-aw) at `.github/workflows/<name>.md` + `.github/aw/<name>-policy.md`. Editing or compiling these workflows requires the `gh aw` CLI extension; the current pin lives in [`.github/aw/README.md`](.github/aw/README.md) → "Pinning".

To add a new skill (interactive or unattended), follow the checklist in [`coding-guidelines/core/agent-skills.md`](coding-guidelines/core/agent-skills.md).

## Subtree Guidance

- PHP/server code: use the `contena-php-code` skill when the task touches PHP architecture, API schema, migrations, deprecations, or BC-sensitive code.
- Administration JS/TS/Vue code: detailed guidance starts at `src/Administration/Resources/app/administration/AGENTS.md`; use the `contena-admin-js` skill for Admin coding rules.
- Administration Vue components: every component must remain a native `.vue` SFC using `<script setup>`. Base components declare their public extension API with `ctDefinePublic`, override components use the `.override.vue` convention and `ctDefineOverride`, and the build transform injects block data scopes. Expose stable UI extension points with named `ct-block` components and use `ct-block-parent` in plugin overrides when retaining the original content; do not introduce runtime `.html.twig` templates, Options API components, or author-facing `createExtendableSetup` wrappers.
- PHPUnit tests: use the `contena-phpunit-tests` skill.
- More specific nested `AGENTS.md` files add local rules for their subtree.

## Coding Guidelines

**MANDATORY**: All code must follow the guidelines in `coding-guidelines/`.

## File Linting

**MANDATORY**: All code must be linted according to the following table.

| File Type              | Check Command                 | Fix Command                                  |
|------------------------|-------------------------------|----------------------------------------------|
| **PHP** (.php)         | `composer cs`                 | `composer cs-fix`                            |
| **PHP** (types)        | `composer phpstan`            | N/A - must fix manually                      |
| **JS/TS/Vue** (Admin)  | `composer eslint:admin`       | `composer eslint:admin:fix`                  |
| **JS/TS/Vue** (Frontend) | `npm --prefix src/Frontend/Resources/app/administration run lint` | `npm --prefix src/Frontend/Resources/app/administration run lint:fix` |
| **SCSS** (Admin)       | `composer stylelint:admin`    | `composer stylelint:admin:fix`               |
| **SCSS** (Frontend)    | `src/Administration/Resources/app/administration/node_modules/.bin/stylelint --config src/Administration/Resources/app/administration/.stylelintrc 'src/Frontend/**/*.scss'` | Same command with `--fix` |
| **Twig** (Frontend)    | `bin/console lint:twig src/Frontend/Resources/views --env=test` | Manual fix required |
| **Snippets**           | `composer translation:lint`   | Manual fix required                          |
| **Prettier** (Admin)   | `composer format:admin`       | `composer format:admin:fix`                  |
| **Prettier** (Frontend) | `npm --prefix src/Frontend/Resources/app/administration run format` | `npm --prefix src/Frontend/Resources/app/administration run format:fix` |
