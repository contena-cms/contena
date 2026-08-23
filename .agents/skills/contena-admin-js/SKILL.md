---
name: contena-admin-js
description: Apply Contena Administration JS/TS/Vue coding rules. Use when editing Admin UI code (.js/.ts/.vue) under src/Administration, including Jest specs and ACL-backed components.
license: MIT
---

# Contena Admin JS

Keep general Administration structure, tech stack, docs links, and scripts in `src/Administration/Resources/app/administration/AGENTS.md`.

## Code

- Use TypeScript for new code.
- Do not introduce public API breaks without prior discussion.
- Follow existing component, module, service, repository, and store patterns.
- Administration Vue components must be native `.vue` SFCs using `<script setup>`. Base components declare extension-facing bindings with one top-level `swDefinePublic({ ... })`; `.override.vue` components use `useSwPreviousState()` and one top-level `swDefineOverride({ ... })`. Do not author `createExtendableSetup` wrappers or `ComponentPublicApiMapping` entries.
- Add named `<ct-block name="sw_...">` seams around meaningful template regions so plugins can extend pages and components. The native setup transform injects `:data="$dataScope"`; do not add it manually. Use snake_case `sw_` names and `<ct-block-parent />` in extending blocks when preserving default content; do not register blocks inside `v-for` loops. Structural `ct-page`/`ct-block` tags are allowed, but visible UI controls must use `mt-*` components.
- For Admin UI that reads or persists DAL entities or associations, update matching ACL privilege mapping and migrations for existing roles when needed.

## Tests

- Write Jest tests for new features and bug fixes.
- Keep tests next to the code under test with `.spec.ts` when adding new TypeScript tests.
- Split very large specs into a `.spec/` directory by behavior group.

## Detailed Guidelines

- Read `coding-guidelines/administration/architecture.md` when changing Admin architecture, component registration, services, state, or module patterns.
- Read the [native setup authoring](../../../src/Administration/Resources/app/administration/technical-docs/03-extensibility/07-native-setup-authoring.md) and [block system](../../../src/Administration/Resources/app/administration/technical-docs/03-extensibility/04-native-block-system.md) references when creating or migrating an SFC with plugin extension points.
- Read `coding-guidelines/administration/testing.md` when adding or restructuring Administration Jest tests.
- Read `coding-guidelines/administration/feature-flags-and-deprecations.md` when touching Admin feature flags, deprecations, or BC behavior.
