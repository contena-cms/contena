# Blog Detail Page Example

One layout combining entity-based rendering with context distribution. The Blog entity is loaded automatically from the `blog/{blogId}` path and exposed to root elements as `blog` context.

```json
{
  "id": "blog-detail-page",
  "component": "CT:Grid:Container",
  "properties": {
    "columns": 1,
    "gap": 24
  },
  "acceptsContext": {
    "blog": {
      "type": "single",
      "required": true,
      "redistribute": true
    }
  },
  "slots": {
    "content": [
      {
        "id": "blog-title",
        "component": "CT:Content:Text",
        "acceptsContext": {
          "blog.name": {
            "type": "single",
            "required": true,
            "propertyAlias": "text"
          }
        }
      },
      {
        "id": "blog-description",
        "component": "CT:Content:Text",
        "acceptsContext": {
          "blog.description": {
            "type": "single",
            "required": false,
            "propertyAlias": "text"
          }
        }
      }
    ]
  }
}
```

The root element receives the auto-loaded Blog entity and redistributes it to both text elements. `propertyAlias` maps the requested Blog field to the `text` property expected by `CT:Content:Text`.
