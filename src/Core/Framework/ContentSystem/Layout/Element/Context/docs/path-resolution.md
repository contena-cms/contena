# Context Path Resolution

How a consumer addresses a nested property of the context an ancestor exposes.

Consumers can request nested properties from context using dot notation. When a provider exposes an entity like `blog`, consumers can access nested properties without loading the full entity themselves.

**Example**: Provider exposes blog, consumer requests only the cover image:

```json
{
  "id": "blog-provider",
  "component": "CT:Blog:Container",
  "dataRequirements": {
    "blog": {
      "source": "entity",
      "config": {
        "entity": "blog",
        "property": "blog",
        "associations": ["cover.media", "categories"]
      }
    }
  },
  "providesContext": {
    "blog": {
      "type": "single",
      "distribution": "broadcast"
    }
  },
  "slots": {
    "default": [
      {
        "id": "cover-image",
        "component": "CT:Content:Image",
        "acceptsContext": {
          "blog.cover": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "cover-url",
        "component": "CT:Content:Text",
        "acceptsContext": {
          "blog.cover.media.url": {
            "type": "single",
            "required": false
          }
        }
      }
    ]
  }
}
```

**Key points**:
- Provider exposes full `blog` entity
- `cover-image` receives only `blog.cover` (MediaEntity)
- `cover-url` receives only `blog.cover.media.url` (string)
- Supports arbitrary nesting depth: `blog.cover.media.url`
- Works only with Contena Struct objects (all DAL entities)
- Path resolution happens automatically during context distribution

**Required vs Optional**:
- `required: true` - Throws exception if path cannot be resolved (property missing, intermediate null, non-Struct value)
- `required: false` - Returns null silently if path fails

**Benefits**:
- Reduces memory usage: elements receive only what they need
- Cleaner element APIs: no need to extract nested data in templates
- Type safety: path validated at runtime with clear error messages
