# Context Example

A single provider distributing one loaded entity to three consumer children.

Provider distributing context to multiple consumer children:

```json
{
  "id": "blog-detail-context",
  "component": "Ct:Blog:Detail",
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
        "component": "Ct:Blog:Title",
        "acceptsContext": {
          "blog": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "blog-price",
        "component": "Ct:Blog:Price",
        "acceptsContext": {
          "blog": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "blog-images",
        "component": "Ct:Blog:Images",
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
3. All three children (`title`, `price`, `images`) receive the same blog data
4. Each consumer declares context as `required: true`
