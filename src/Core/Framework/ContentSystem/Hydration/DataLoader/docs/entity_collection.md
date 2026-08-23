# Entity Collection Loader (`source: "entity_collection"`)

Loads multiple entities by their IDs.

```json
{
  "id": "blog-slider",
  "component": "CT:Blog:Slider",
  "properties": {
    "blogIds": ["019456789abc", "019456789def", "019456789ghi"]
  },
  "dataRequirements": {
    "blogs": {
      "source": "entity_collection",
      "config": {
        "entity": "blog",
        "property": "blogIds",
        "associations": ["cover"]
      }
    }
  }
}
```

Config fields:
- `entity` (required) - Entity name to load
- `property` (required) - Property on this element containing an array of entity IDs
- `associations` (optional) - List of associations to load with the entities

After loading, access via element's `blogs` property (the requirement key).
