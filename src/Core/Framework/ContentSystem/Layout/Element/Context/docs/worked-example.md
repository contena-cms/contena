# Context Example

A single provider distributing one loaded entity to three consumer children.

Provider distributing context to multiple consumer children:

```json
{
  "id": "blog-detail-context",
  "component": "CT:Blog:Detail",
  "dataRequirements": {
    "blog": {
      "source": "entity",
      "config": {
        "entity": "blog",
        "property": "blog"
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
        "id": "blog-title",
        "component": "CT:Blog:Title",
        "acceptsContext": {
          "blog": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "blog-excerpt",
        "component": "CT:Blog:Excerpt",
        "acceptsContext": {
          "blog": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "blog-cover",
        "component": "CT:Blog:Cover",
        "acceptsContext": {
          "blog": {
            "type": "single",
            "required": true
          }
        }
      }
    ]
  }
}
```

Process:
1. Provider loads blog via `dataRequirements`
2. Provider exposes blog as `"single"` context with `"broadcast"` distribution
3. All three children (`title`, `excerpt`, `cover`) receive the same blog data
4. Each consumer declares context as `required: true`
