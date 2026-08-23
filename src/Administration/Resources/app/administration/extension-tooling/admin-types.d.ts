/**
 * @ct-package framework
 *
 * The single type surface for Administration extensions: the live installed
 * types of this Administration. Leaf tsconfigs and plugin shims inject this
 * file via "files" so extension code sees exactly the API of the installed
 * Contena version, including the installation-specific entity schema.
 *
 * The entity schema import resolves to the generated
 * `src/entity-schema-definition.d.ts`. When that file has not been generated
 * yet, the setup command writes a stub that keeps `EntitySchema.Entities`
 * empty so missing schema types fail loudly instead of degrading to `any`.
 */

/// <reference types="node" />

import '../src/global.types';
import '../src/entity-schema-definition';
// Native-setup compile-time macros are stripped by the Contena setup transform,
// but extension SFCs still need their authoring types.
import '../build/vue-setup-transform/contena-setup-macros';
// Global `ServiceContainer` augmentations that live outside the module graph
// reachable from `global.types.ts`. The Administration's own program compiles
// all of `src/**/*`, so these are implicitly present there; extension programs
// only see them through an explicit import. `type-surface.spec.ts` guards this
// list against new augmentations drifting out of the surface.
import '../src/module/ct-flow/service';
import '../src/module/ct-extension/service';
import '../src/module/ct-settings-services/service';
import '../src/core/service/api/excluded-search-term.api.service';
import '../src/module/ct-channel/service/channel-favorites.service';
import '../src/module/ct-channel/service/domain-link.service';
import '../src/module/ct-settings-basic-information/service/captcha.service';
import '../src/module/ct-settings-search/service/blog-index.api.service';
import '../src/module/ct-settings-search/service/live-search.api.service';
