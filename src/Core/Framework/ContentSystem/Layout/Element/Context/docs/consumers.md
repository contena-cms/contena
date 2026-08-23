# Consumer Configuration

Configuration reference for the `acceptsContext` entry an element declares to receive data from an ancestor.

Consumer receives context from ancestor provider using `acceptsContext`.

```json
{
  "id": "blog-title-consumer",
  "component": "CT:Blog:Title",
  "acceptsContext": {
    "blog": {
      "type": "single",
      "required": true
    }
  }
}
```

Fields:
- Context key (`"blog"`) - Must match provider's context key exactly (or its `consumerAlias`)
- `type` - Expected context data type:
  - `"single"` - Expects single entity/value
  - `"collection"` - Expects array of entities/values
- `required` - Whether context is mandatory:
  - `true` - Element fails if context unavailable
  - `false` - Element works without context
- `propertyAlias` (optional) - Renames the property key where context data is stored in this element. The consumed data is stored with this alias instead of the original context key. Useful for component reusability when elements expect specific property names. Cannot contain dots. Must be unique within the element (no two consumers can resolve to the same property key).

Consumer receives context data directly as a property.

**Property Alias Example:**

**Use case:** Reusable image element expects `"image"` property, but blog layout provides `"blog.cover"`. Use `propertyAlias` to adapt without modifying the element.

```json
{
  "id": "blog-cover",
  "component": "CT:Content:Image",
  "acceptsContext": {
    "blog.cover": {
      "type": "single",
      "required": true,
      "propertyAlias": "image"
    }
  }
}
```

Element receives `blog.cover` from parent context but stores it as `image` internally, matching what the renderer expects. Enables component reusability across different data sources.
