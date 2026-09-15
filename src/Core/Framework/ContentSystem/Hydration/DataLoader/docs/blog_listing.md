# Blog Listing Loader (`source: "blog_listing"`)

Loads blog listings for a navigation/category. Filters, sorting, and pagination are controlled via request parameters.

Category pages declare this loader once at page level through `CategoryContentLayoutDefinition`. The resulting
`blogListing` is root-scoped context: `Ct:Blog:Listing` and a neighbouring `Ct:Filter:Panel` consume the same
result without declaring their own element-level data requirement.

```json
{
  "id": "category-listing",
  "component": "Ct:Blog:Listing"
}
```

The built-in Category page requirement uses:

```json
{
  "source": "blog_listing",
  "config": {
    "property": "categoryId",
    "associations": ["cover", "cover.media", "cover.media.thumbnails"]
  }
}
```

Plugins and apps can still declare an explicit `blog_listing` requirement for a non-Category root. Its element
type must expose the string property named by `config.property`; the requirement key receives the loaded
`BlogListingResult`.

Config fields:
- `property` (optional) - Property on this element containing the navigation/category ID. Defaults to `"navigationId"` if not specified.
- `associations` (optional) - List of associations to load with the blogs
- `associationOverride` (optional) - Names an element property holding a `list<string>` of further associations. `LoaderInputResolver` merges that list into `associations` before `load()` runs, so the loader reads the merged list under the `associations` key alone. Defaults to the property name `"associations"`

Pagination, filters, and sorting are controlled via request parameters (query string), not config. See [Additional Parameters](../../../Adapter/docs/placeholders.md#additional-parameters) for details.
