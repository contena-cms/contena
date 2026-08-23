# Channel

Channel API entry point. A single `ContentRoute` class serves all formats and content sections, parameterized via dependency injection.

## Key Classes

- `AbstractContentRoute` - Decorator base for route extension
- `ContentRoute` - DI-parameterized: `RenderingSpecificationResolver` + `ContentSection` + content-layout `EntityRepository` + `AbstractResponseFactory`

## Endpoints

All endpoints use HTTP GET with cache enabled. `?elementId` partial rendering is gated per section, not per format: every main-section format accepts it, and header and footer accept it in no format, because their specification sources never resolve a target element. See [Partial Rendering](../Output/README.md#partial-rendering).

**Main section:** `/channel-api/content/{path}`, `/channel-api/content-decomposed/{path}`, `/channel-api/content-skeleton/{path}`, `/channel-api/content-data/{path}`

**Header/Footer:** Same format variants at `/channel-api/content-header*` and `/channel-api/content-footer*`.

Routes registered programmatically via `ContentRouteLoader` in Routing/, not via PHP attributes.

## Subdirectories

- **Routing/** - Programmatic route registration (ContentRouteLoader, ContentRouteDefinition)
