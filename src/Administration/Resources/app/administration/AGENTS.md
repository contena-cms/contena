# Contena 6 Administration - AGENTS.md

> **Full Documentation**: See `technical-docs/` for comprehensive guides
> **Specific Areas**: See AGENTS.md in `src/core/`, `src/app/`, `src/module/`, `test/`
> **Skill**: For Admin JS/TS/Vue/Jest work, follow `contena-admin-js` (`.agents/skills/contena-admin-js/SKILL.md`) — it carries the mandatory coding + test rules.

## File Structure

```
technical-docs/     # Full technical documentation
src/
├── core/               # Vue indepedent code, Framework, repositories, services (AGENTS.md)
|   ├── application.ts  # Application bootstrap (AGENTS.md)
|   └── contena.ts     # Global Contena object in window (AGENTS.md)
├── app/                # Vue specific code, UI, components, stores (AGENTS.md)
│   ├── init/           # Boot sequence (AGENTS.md)
│   ├── component/      # Global components (AGENTS.md)
│   └── store/          # Pinia stores (AGENTS.md)
└── module/             # Business modules (AGENTS.md)
test/                   # Only setup files, helper and mocks for tests (AGENTS.md)
```

## Technologies

```
# Core
TypeScript          # Main programming language
JavaScript          # Was used in legacy code
Vue 3               # Components get compiled to Vue 3 components
Pinia               # State management
Vue Router          # Routing
Axios               # HTTP client
Vite                # Build tool
Jest                # Testing framework
```

## Special differences to regular Vue projects

- **Vue Single File Components**: Every Administration component is a Vue SFC. Runtime component templates must not use `.html.twig` or TwigJS. Preserve extension points through the native extension system while converting legacy templates, then delete the Twig source immediately.
    - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/03-extensibility/` for details on the component factory and extensibility.

- **Special Boot Sequence**: The boot process is tailored to the Contena ecosystem. It dynamically imports core modules like `core/contena.ts` and `app/main.ts`, initializes the dependency injection container, and sets up services and plugins. The server-rendered Twig shell only injects runtime configuration before the Vue.js application is bootstrapped; it is not an Administration component template.
    - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/02-architecture/01-boot-process.md` for a detailed overview of the boot sequence.

- **Global Contena Object**: A global `Contena` object is created during the boot process. This object acts as the central point for accessing services, factories, and the dependency injection container. It is initialized in `core/contena.ts` and is available throughout the application.
    - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/02-architecture/03-module-system.md` for more information on the global Contena object.

## Coding guidelines

- Administration component names and template tags must use the `mt-*` prefix. Do not add, extend, or newly reference legacy `ct-*` UI components; migrate existing `ct-*` components to `mt-*` and remove the legacy component as the relevant area is converted.
- Develop every new or materially changed Administration component as a native Vue SFC. Do not add runtime `.html.twig` component templates or TwigJS dependencies; the Symfony-rendered bootstrap shell is not a component and must be audited separately before its Twig dependency changes.
- Keep all component-specific styles in the owning `.vue` file with a `<style lang="scss">` block. Do not create or retain a separate same-named `.scss` file for an Administration component; reserve shared SCSS files for genuinely global tokens, mixins, resets, and cross-component utilities.
- Make every SFC extendable with native `<script setup>`. Base components declare the supported public bindings once with `ctDefinePublic({ ... })`; unlisted top-level bindings remain private. Override components use the `.override.vue` filename convention, read the base state through `useCtPreviousState()`, and declare replacements with `ctDefineOverride({ ... })`. Do not author `createExtendableSetup` wrappers or `ComponentPublicApiMapping` entries; the native setup transform owns that runtime plumbing.
- Expose plugin-facing template seams with named `<ct-block name="ct_...">` elements around meaningful page, content, state, and action regions. Every static `name` or `extends` value must start with `ct_` and use snake_case. The native setup transform injects `:data="$dataScope"`; do not write that binding by hand. Plugin overrides should use `<ct-block extends="ct_...">` and `<ct-block-parent />` when preserving the base output. Structural `ct-page`/`ct-block` tags are allowed, but legacy `ct-*` UI widgets are not.
- Keep block definitions stable and outside `v-for` loops. Do not hide an override registration behind a condition unless the extension is intentionally scoped to that mounted state. Add component tests for the public setup API and the important block/state behavior.
- Write Jest tests for all new features and bug fixes
    - Locate tests in the same folder as the code they are testing, using the `.spec.ts` suffix.
    - Split big tests (500+ lines) into a `.spec/` directory named after the original spec. Each file in that directory must cover a logical scenario or behavior group, similar to how related tests are grouped with `describe` blocks, for example `ct-component-name.spec/validation.spec.ts`.
- Use TypeScript for all new code
- Do NOT introduce breaking changes to public APIs without prior discussion
- Follow existing code style and patterns
- Use the provided linting and formatting scripts (see below)

## Scripts

Run the composer commands in the root of the repository. These commands are wrapper scripts around the NPM scripts.
In a Docker environment prepend `docker compose exec web ...`.

```bash
# Linting
composer eslint:admin # Run ESLint
composer eslint:admin:fix # Run ESLint with --fix
composer stylelint:admin # Run Stylelint
composer stylelint:admin:fix # Run Stylelint with --fix

composer format:admin # Format code with Prettier
composer format:admin:fix # Format code with Prettier and --write

# Tests
composer admin:unit # Run unit tests
composer admin:unit:watch # Run unit tests in watch mode

# Single jest test, run inside "src/Administration/Resources/app/administration" folder
npx jest --collectCoverage=false src/core/factory/http.factory.spec.js # Example single test run
# All jest tests without coverage for better readability, run inside "src/Administration/Resources/app/administration" folder
npx jest --collectCoverage=false

# Build
composer build:js:admin # Build the administration
```

**See**: `src/Administration/Resources/app/administration/technical-docs/` for architecture, patterns, and detailed guides
