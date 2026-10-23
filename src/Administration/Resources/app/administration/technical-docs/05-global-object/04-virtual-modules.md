# `contena:*` Modules

Status: Experimental, stable with v6.8.0

The set of specifiers, what each one exports, and their types can change in any release until then, with
no deprecation cycle. `Contena.*` access is stable and unaffected.

The `contena:*` modules expose selected parts of the global `Contena` object as ES modules. The global
object remains available, and existing `Contena.*` calls continue to work.

```ts
const globalId = Contena.Utils.createId();

import { createId } from 'contena:utils';
const importedId = createId();
```

## Available modules

`contena:utils` and `contena:data` provide default and named exports for their global branches.

```ts
import utils, { createId, debounce } from 'contena:utils';
import data, { Criteria } from 'contena:data';
```

`contena:composables` provides `Contena.Composables` the same way. It is
`@experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES`.

Each member also has a subpath with a default export. Utility namespaces can have explicit named
exports. For example, `contena:utils/debug` provides `warn` and `error`.

```ts
import debug, { warn } from 'contena:utils/debug';
import EventBus from 'contena:utils/EventBus';
import CriteriaClass from 'contena:data/Criteria';
import useListing from 'contena:composables/useListing';
```

`contena:mixins` and `contena:stores` only have subpaths. The subpath matches the registry key.

```ts
import swFormFieldMixin from 'contena:mixins/sw-form-field';
import useSwOrderDetailStore from 'contena:stores/swOrderDetail';
```

| Specifier                     | Exports                                                            |
| ----------------------------- | ------------------------------------------------------------------ |
| `contena:utils`              | `Contena.Utils` as default and its members as named exports       |
| `contena:utils/<member>`     | The member as default, plus declared namespace exports             |
| `contena:data`               | `Contena.Data` as default and its classes as named exports        |
| `contena:data/<Class>`       | The class as default                                               |
| `contena:composables`        | `Contena.Composables` as default and its members as named exports |
| `contena:composables/<name>` | The composable as default                                          |
| `contena:mixins/<name>`      | The registered mixin as default, for mixins in `src/app/mixin`     |
| `contena:stores/<id>`        | A store composable as default                                      |

A store subpath returns a composable that defers the registry lookup until the composable runs. Store
subpaths have no named exports because destructuring a Pinia store removes reactivity. Use `storeToRefs`
when a component needs reactive properties from the store.

The mixin and store families have individual subpaths and no root module.

## Resolution timing

Production imports of utilities, data classes, composables, and mixins capture their initial values when
the module evaluates. A store module captures the global object but defers its registry lookup until its
exported function runs.

A generated module does not read a global in the Administration build. It imports the instance from
`src/core/contena`, so evaluation order guarantees the object exists, and a `contena:*` import is safe
at any point in the boot sequence. A mixin subpath additionally imports `src/app/mixin`, whose eager glob
registers every mixin in that directory, so the lookup cannot run before registration.

An extension bundle has no Administration source to import and reads the global instead. Extension code
runs after the Administration has booted, so the object is always there; the generated module throws with
that explanation if it ever is not.

A prebuilt extension bundle can run on an Administration older than the one it was built against.
`Contena.Composables` exists since Contena 6.7.16.0, so a `contena:composables` module of an extension
checks for it first. Instead of a `TypeError`, it throws an error that names the required version, the
installed version from `Contena.Context.app.config.version`, and the fix: require `contena/administration`
`>=6.7.16.0` in the `composer.json` of the extension, which the plugin lifecycle enforces. A composable
cannot be polyfilled from the extension, because it depends on Administration state such as stores and the
router.

The check covers `Contena.Composables` as a whole, not its members. A composable added in a later version
imports as `undefined` on an Administration that has `Contena.Composables` but not that composable.

`src/core` keeps using the global as a matter of layering. It is the Vue-independent framework code, it
boots first, and part of it is bundled into the admin worker, where there is no `window.Contena` at all.
Files under `src/app/mixin` keep it too, because a generated mixin module imports that directory and the
reverse would be a cycle.

A mixin a feature module registers as it loads — `cms-element` and `cms-state` from `sw-cms`, for
instance — has no subpath, because nothing can guarantee it is registered when an importer evaluates.
Those keep `Contena.Mixin.getByName()`.

The checked-in registry only contains Administration registrations. An extension cannot import a store
or mixin that the extension registers at runtime. Use the extension's own composable,
`Contena.Store.get()`, or `Contena.Mixin.getByName()` for these registrations.

## Generated files

Run this command after you add a utility, DAL class, composable, mixin, or store:

```bash
composer admin:generate-contena-modules
```

The command reads the existing source contracts and writes these files:

- `contena-modules.json` lists each valid specifier and its named exports.
- `src/contena-virtual-modules.d.ts` declares the same default and named exports for TypeScript.

Both files are checked in. The generator test fails when either file does not match the source.

## Build and test integration

The Vite plugin in `build/vite-plugins/virtual-contena-modules` generates one ES module for each valid
specifier. It emits static named exports so Rollup can reject invalid imports and remove unused exports.
A pure annotation lets Rollup remove an unused mixin lookup.

Jest does not run Vite. The Jest configuration writes all current stub files before its workers start.
Unlike production bindings, named stub exports use getters. Tests that replace `window.Contena` receive
values from their mocks. Store stubs also read the replaced global object on each call.
