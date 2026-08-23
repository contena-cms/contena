# DataLoader

Data fetching for content elements. Elements declare `DataRequirement` objects with a `source` identifier. `DataLoaderProvider` dispatches to the registered loader matching that source.

## Key Classes

- `AbstractContentDataLoader` - Loader base class with `load()`, `getRequirementType()`, `producibleTypes()`, and `resolveProducedType()`
- `ContentDataLoaderResult` - Result with data and cache info: `notFound()`, `cached()`, `cachedExternally()`, `uncacheable()`
- `LoaderTypeCapability` - VO describing one type a loader can produce: `producedType`, `configTemplate`, `genericParameters`
- `LoaderConfigSpecification` - VO for a loader's declared config contract: an ordered `list<ConfigKeySpecification>`; `requiredKeys()` and `keysOfKind()` derive from it
- `ConfigKeySpecification` - VO for one declared config key: `name`, `kind`, `type`, `required`, `hasDefault`/`default`, optional `adminUI`
- `ConfigKeyKind` - Enum: what a config key's value names — `Literal`, `PropertyReference`, `EntityName`
- `DataLoaderProvider` - Service locator dispatcher: `get($source)` throws if the source is not registered; `getSources()` lists every registered source identifier (used by the type resolver)

## Built-in Loaders

- [**EntityLoader** (`entity`)](docs/entity.md) — Single entity by ID
- [**EntityCollectionLoader** (`entity_collection`)](docs/entity_collection.md) — Multiple entities by IDs
- [**BlogListingDataLoader** (`blog_listing`)](docs/blog_listing.md) — Blog listings with filters, sorting, pagination
- **BlogSearchDataLoader** (`blog_search`) — Blog search results
- **BlogSuggestDataLoader** (`blog_suggest`) — Blog search suggestions
- [**NavigationDataLoader** (`navigation`)](docs/navigation.md) — Navigation tree; aliases: `main-navigation`, `service-navigation`, `footer-navigation`
- **ServiceMenuDataLoader** (`service_menu`) — Service menu navigation
- **BreadcrumbDataLoader** (`breadcrumb`) — Breadcrumb trail
- [**LanguageDataLoader** (`language`)](docs/language.md) — Available channel languages

The four unlinked loaders above — `blog_search`, `blog_suggest`, `service_menu`, `breadcrumb` — have no configuration reference yet.

## Guides

- [docs/data-requirements.md](docs/data-requirements.md) - What a `dataRequirements` entry declares, when to use one, and its fields.
- [docs/custom-loaders.md](docs/custom-loaders.md) - Registering a new data source: config, serializer, loader, and cache behavior.
- [docs/introspection.md](docs/introspection.md) - The Admin API surface clients read to discover available sources and their config keys.

## Extension Point

1. Extend `AbstractContentDataLoader`, implement `getRequirementType()` returning your source identifier
2. Annotate with `@extends AbstractContentDataLoader<YourStruct>` — the default `producibleTypes()`/`resolveProducedType()` derive the produced type from it; a missing or unresolvable annotation fails the build (see Schema/)
3. Create config class extending `AbstractContentDataLoaderConfig` with matching serializer
4. Override `configSpecification()` when the config serializer accepts keys — declares the loader's config contract for the derived completion residue
5. Tag with `content_system.data_loader` in the owning domain's DI — service locator uses `getRequirementType()` as key
6. Return `ContentDataLoaderResult` with appropriate cache info — never throw exceptions

Fixed-type loaders need no override: the base `producibleTypes()` returns one `LoaderTypeCapability` derived from `@extends`, and `resolveProducedType()` returns that type ignoring config.

Wildcard loaders that serve multiple concrete types (e.g., the generic `entity`/`entity_collection` loaders) override both `producibleTypes()` and `resolveProducedType()` to enumerate the live definition registry — one capability per registered entity, each carrying the `configTemplate` (`['entity' => <name>]`) needed to produce it; each also declares a `configSpecification()` requiring `entity` and `property`, so the derived residual config key is `property`. Enumeration skips definitions that have no addressable type: `MappingEntityDefinition`s, plus the `entity` loader skips an `ArrayEntity` entity class and the `entity_collection` loader skips a bare `EntityCollection` collection class. `resolveProducedType()` throws `ContentSystemException::unknownLoaderEntity` when the configured entity name is not registered.
