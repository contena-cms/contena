# Administration architecture

These rules apply to code under `src/Administration/Resources/app/administration`.

## Layers

- Follow existing Administration code style and component patterns in the area you touch.
- Do not introduce breaking changes to public Administration APIs or extension points without prior discussion.
- Use the repository-root composer wrappers for Administration linting, formatting, tests, and builds.
- Keep `core` free of Vue-related code. Modules may import shared non-Vue functionality from `core`.
- Keep module code independent. Do not import directly from another module; communicate through registered services, repositories, routes, stores, or shared app/core code.
- Vue components must use native `.vue` SFCs and the native setup extension contract. Base components declare extension-facing bindings with one top-level `swDefinePublic({ ... })`; all other top-level runtime bindings are private. Overrides use the `.override.vue` filename convention, `useSwPreviousState()`, and one top-level `swDefineOverride({ ... })`. Do not author `createExtendableSetup` wrappers or `ComponentPublicApiMapping` entries. Read [the native setup authoring reference](../../src/Administration/Resources/app/administration/technical-docs/03-extensibility/07-native-setup-authoring.md) before adding or migrating a component.
- Keep the boot order `init-pre/ -> init/ -> init-post/` when changing startup code.

## Extension-aware access

- Prefer extension-aware access through the global `Contena` APIs where they are available, for example `Contena.Component`, `Contena.Service()`, and `Contena.Store`. Component code may still use injected services, and boot code may need direct access before all globals are available.
- Do not import factory internals directly when an extension-aware global API is available in that context.
- Develop and register every component as a Vue SFC using the native extension system. Runtime component templates must not use TwigJS or `.html.twig`. Preserve legacy extension points while converting them to native SFC blocks, and delete the Twig template in the same change.
- Preserve extension points exposed through the global `Contena` object when changing repositories, services, components, and stores.
- In SFC templates, declare stable plugin seams with `<ct-block name="sw_...">`. The native setup transform injects `:data="$dataScope"`; do not add it manually. Use snake_case names with the `sw_` prefix, place blocks around semantic regions rather than incidental markup, and keep them out of `v-for` loops. Extension components should use `extends` and `<ct-block-parent />` when they retain the base content. See [the native block reference](../../src/Administration/Resources/app/administration/technical-docs/03-extensibility/04-native-block-system.md).

## Modules and UI

- Use Ant Design Vue as the primary Administration UI library and prefer its native components and theme tokens. Name project-owned Administration abstractions with the `ct-*` prefix. Legacy Meteor `mt-*` components are migration targets; do not introduce new usages or compatibility layers.
- Protect module routes, navigation entries, and templates with the required ACL privileges.
- When adding Admin UI that reads or persists a DAL entity or association, update the matching ACL privilege mapping in the same change. Verify limited-role users get the needed `read`, `create`, `update`, and `delete` privileges through the feature's viewer/editor/creator/deleter roles instead of relying on super-admin behavior.
- If new privileges must apply to roles that already exist in installations, add a migration that updates stored `acl_role.privileges`; changing the Administration mapping alone only fixes future role evaluation.
- Reload an entity after saving it through a repository so origin state and change tracking stay in sync.
- Use snippets for visible text; do not hardcode user-facing strings.
- Keep business logic out of templates.
- Prefer composables for new shared Vue logic. Do not add new mixin-based APIs unless you are extending legacy code.
- Use BEM-style class names and Ant Design Vue theme tokens for Administration styling. Avoid inline styles and equivalent hard-coded design values.

## Data access

- Use repositories and Criteria for entity work instead of direct entity or HTTP manipulation.
- Keep Criteria page sizes reasonable and disable total counts when the UI does not need them.
- Load only the associations needed for the current screen or operation.
- Batch related entity changes into one save when possible.
