# Entity Loader (`source: "entity"`)

Loads a single entity by ID or property reference.

```json
{
  "id": "blog-detail",
  "component": "CT:Blog:Detail",
  "dataRequirements": {
    "blog": {
      "source": "entity",
      "config": {
        "entity": "blog",
        "property": "blog",
        "associations": ["cover.media", "categories", "tags"]
      }
    }
  }
}
```

Config fields:
- `entity` (required) - Entity name to load (e.g., `"blog"`, `"category"`)
- `property` (required) - Property on this element containing the entity ID. The loader reads the ID from here.
- `associations` (optional) - List of associations to load with the entity

After loading, access via element's `blog` property (the requirement key).

A blog card that loads its own data:

```json
{
  "id": "blog-card",
  "component": "CT:Blog:Card",
  "properties": {
    "blog": "{{blogId}}"
  },
  "dataRequirements": {
    "blog": {
      "source": "entity",
      "config": {
        "entity": "blog",
        "property": "blog"
      }
    }
  }
}
```

The `property` field points to the element's own property. The loader reads the ID from there, loads the blog, and stores the result back in the same property.

If multiple elements need the same blog, load it once at their parent and use the context system instead (see [Context System](../../../Layout/Element/Context/README.md)).
