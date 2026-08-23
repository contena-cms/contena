# Type Spec as Output Schema

What a type's declared `properties` describe, and how the property key ties the declaration to an element instance and to the data loaders.

The type specification's `properties` describe what a **hydrated** element looks like in the API response — not what is stored in the database. This is the central design relationship between the type system and the element system.

A type property with a FQCN type (e.g., `ChannelBlogEntity`) is not stored in the database as a property value. It appears in the element's `properties` map only after hydration, when a data loader or context provider fills it.

A type property with a primitive type (e.g., `string`, `boolean`) is stored in the database as a static property value set at design time.

Both end up in the same `properties` map after hydration. The type spec does not distinguish between them because the API consumer (frontend, admin, headless client) sees a single unified property bag.

## Key-Based Linkage

The property key is the connecting identifier across all systems:

- Type spec: `properties.blog` — "this element has a property called `blog`"
- Element storage: `dataRequirements.blog` — "load `blog` via this data loader"
- Element storage: `acceptsContext.blog` — "receive `blog` from a parent"
- Hydrator: `$element->setProperty('blog', $data)` — "store loaded data under key `blog`"
- API output: `properties.blog` — serialized ChannelBlogEntity

The type spec declares WHAT properties exist and their types. The element instance declares HOW each non-primitive property gets its value (via `dataRequirements` or `acceptsContext`). These are different concerns with different structures, connected by the shared property key.

**Alias and path variations:** The direct key match is the common case. Two exceptions:
- Context consumers may use `propertyAlias` to store received data under a different key than the consumer key (e.g., `acceptsContext.blog` with `propertyAlias: "item"` stores data under `properties.item`).
- Path-based consumers (e.g., `acceptsContext: blog.cover`) receive a resolved sub-property from the parent's `blog` context, stored under the consumer key or its property alias.

## Type-to-Loader Bridge

`ContentSystemDataLoaderMap` connects type spec FQCNs to data loader capabilities:

- Forward: given a loader source (e.g., `"entity"`), what types can it produce?
- Reverse: given a FQCN (e.g., `ChannelBlogEntity`), which loaders can produce it?

`ContentSystemDataLoaderMapResolver` assembles and memoizes this bridge lazily on its first runtime lookup; `ContentSystemDataLoaderCompilerPass` builds no map — at compile time it only validates each tagged loader: the `@extends` annotation (`extendsDescriptor()`), the source name, and the declared `configSpecification()` (failure conditions in [DataLoader](../../../Hydration/DataLoader/AGENTS.md)). Currently consumed by the Schema API endpoint; designed to also serve future layout validation.
