---
title: Administration JavaScript deprecation guards
date: 2026-08-10
area: administration
tags: [administration, deprecation, extension-api, feature-flag]
---

## Context

Administration uses JSDoc `@deprecated tag:vX.Y.0` annotations to communicate planned removals. The annotation is useful to IDEs and ESLint, but it does not create runtime behaviour. An extension can therefore use a deprecated Administration API without receiving a warning during development, and core can keep using it after the target major feature flag is enabled.

The PHP implementation solves this with `Feature::triggerDeprecationOrThrow()`: it reports legacy use before the major and rejects it once the related major flag is active. Administration needs the same lifecycle for its supported runtime extension APIs.

Not every deprecated Administration member is a supported runtime API. Types, styles, tests, template markup, and private implementation details cannot, or should not, receive a runtime guard.

## Decision

### Runtime helper

Add `Contena.Feature.triggerDeprecationOrThrow(majorFlag, message)` to the Administration feature API.

Before the target major flag is active, it emits a development deprecation warning with a useful migration message and call site. When the flag is active, it throws an `Error`.

The helper is used only at the boundary where deprecated functionality is actually consumed. Core must move to the replacement before enabling the major flag; the active-flag error reveals missed core and extension uses.

```ts
Contena.Feature.triggerDeprecationOrThrow(
    'V6_9_0_0',
    'Contena.Service("example").oldMethod() is deprecated; use newMethod() instead.',
);
```

### Public, detectable APIs

The following public API categories require a runtime strategy:

- global `Contena.*` APIs, registered services, and exported functions at their call boundary;
- registered native SFC components at mount and deprecated props when the prop is supplied;
- public functions and accessors exposed through a native SFC's `ctDefinePublic()` contract.

Component events may later be detected from listener VNode properties. They are not part of the initial enforcement scope because that needs a dedicated, tested listener-presence boundary.

### Private and static-only symbols

`@private` on the directly attached declaration and identifiers beginning with `_` take precedence over `@deprecated` for public BC enforcement. They are not public extension contracts and do not require a runtime deprecation guard.

Types, interfaces, SCSS, tests, template markup, and unobservable state reads remain static-only. Component-local state, store state and getters without an explicit facade, watchers, `provide`, and `inject` are static-only unless a future public API defines a reliable use boundary.

### Enforcement

`ct-deprecation-rules/require-deprecation-guard` associates a leading `@deprecated tag:vX.Y.0` comment with the declared symbol and validates the corresponding major flag. A public runtime-detectable symbol requires a matching `triggerDeprecationOrThrow()` call. The rule skips directly private and underscore-prefixed symbols and requires an explicit `@deprecationGuard static-only - <reason>` annotation for other exceptions.

Native SFC components and props carry declarative `deprecated` metadata. The Administration deprecation plugin guards the component when it is created and the prop when it is explicitly supplied.

## Consequences

Extension developers receive actionable warnings while upgrading and a clear failure in next-major mode instead of discovering an unsupported call only after removal. Core's next-major tests expose missed legacy uses as well.

The rule adds deliberate classification work: technical reachability does not by itself make a member public. Private implementation work remains outside the public deprecation lifecycle.
