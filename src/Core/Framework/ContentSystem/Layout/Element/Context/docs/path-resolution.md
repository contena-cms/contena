# Context Path Resolution

How a consumer addresses a nested property of the context an ancestor exposes, or of the layout's root-ambient context.

Consumers can request nested properties from context using dot notation. When a provider exposes an entity like `blog`, consumers can access nested properties without loading the full entity themselves.

**Example**: Provider exposes blog, consumer requests only the cover image:

```json
{
  "id": "blog-provider",
  "component": "Ct:Blog:Container",
  "dataRequirements": {
    "blog": {
      "source": "entity",
      "config": {
        "entity": "blog",
        "property": "blog",
        "associations": ["cover", "manufacturer"]
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
        "component": "Ct:Content:Image",
        "acceptsContext": {
          "blog.cover": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "manufacturer-name",
        "component": "Ct:Content:Text",
        "acceptsContext": {
          "blog.manufacturer.name": {
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
- `manufacturer-name` receives only `blog.manufacturer.name` (string)
- Supports arbitrary nesting depth: `blog.manufacturer.country.code`
- Works only with Contena Struct objects (all DAL entities)
- Path resolution happens automatically during context delivery
- A `scope: "root"` consumer addresses the root-ambient context by the same rule: `blog.cover` resolves through the root `blog` struct exactly as it resolves through a delivered one

**Required vs Optional**:
- `required: true` - Throws exception if path cannot be resolved (property missing, intermediate null, non-Struct value)
- `required: false` - Returns null silently if path fails

**Benefits**:
- Reduces memory usage: elements receive only what they need
- Cleaner element APIs: no need to extract nested data in templates
- Type safety: path validated at runtime with clear error messages
