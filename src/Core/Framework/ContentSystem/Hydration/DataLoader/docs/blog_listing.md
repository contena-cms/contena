# Blog Listing Loader (`source: "blog_listing"`)

Loads blog listings for a navigation or category. Filters, sorting, and pagination are controlled through request parameters.

```json
{
  "id": "category-listing",
  "component": "CT:Blog:Listing",
  "properties": {
    "navigationId": "{{categoryId}}"
  },
  "dataRequirements": {
    "listing": {
      "source": "blog_listing",
      "config": {
        "property": "navigationId",
        "associations": ["cover.media", "categories", "tags"]
      }
    }
  }
}
```

Config fields:

- `property` (optional) - Property on this element containing the navigation or category ID. Defaults to `"navigationId"`.
- `associations` (optional) - Associations to load with the blogs.

After loading, access the result through the element's `listing` property (the requirement key).

Pagination, filters, and sorting are controlled through request parameters rather than loader config. See [Additional Parameters](../../../Adapter/docs/placeholders.md#additional-parameters).
