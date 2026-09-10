# Blog Listing Loader (`source: "blog_listing"`)

Loads blog listings for a navigation/category. Filters, sorting, and pagination are controlled via request parameters.

```json
{
  "id": "category-listing",
  "component": "Ct:Blog:Listing",
  "properties": {
    "navigationId": "{{categoryId}}"
  },
  "dataRequirements": {
    "listing": {
      "source": "blog_listing",
      "config": {
        "property": "navigationId",
        "associations": ["cover", "manufacturer"]
      }
    }
  }
}
```

Config fields:
- `property` (optional) - Property on this element containing the navigation/category ID. Defaults to `"navigationId"` if not specified.
- `associations` (optional) - List of associations to load with the blogs
- `associationOverride` (optional) - Names an element property holding a `list<string>` of further associations. `LoaderInputResolver` merges that list into `associations` before `load()` runs, so the loader reads the merged list under the `associations` key alone. Defaults to the property name `"associations"`

After loading, access via element's `listing` property (the requirement key).

Pagination, filters, and sorting are controlled via request parameters (query string), not config. See [Additional Parameters](../../../Adapter/docs/placeholders.md#additional-parameters) for details.
