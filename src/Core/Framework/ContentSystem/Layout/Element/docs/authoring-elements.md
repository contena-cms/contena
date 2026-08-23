# Authoring Content Elements

The JSON shape of a content element as a layout author writes it: its fields, its slots, and how containers nest.

## Structure

Each content element follows this structure:

```json
{
  "id": "blog-card",
  "component": "CT:Blog:Card",
  "properties": {
    "text": "Featured Blog",
    "blogId": "{{blogId}}"
  },
  "slots": {
    "content": [
      {
        "id": "blog-title",
        "component": "CT:Blog:Title"
      }
    ]
  }
}
```

Placeholders (like `{{blogId}}`) must be assigned to properties before data loaders can use them:

```json
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
```

**Required fields:**
- `id` - Unique identifier for the element (required for processing)
- `component` - Element component identifier

**Optional fields:**
- `properties` - Configuration values (static or placeholders)
- `slots` - Named containers with arrays of child elements
- `dataRequirements` - Data loading declarations
- `providesContext` - Data shared with descendant elements
- `acceptsContext` - Data received from ancestor elements
- `attributedSpecifications` - System bookkeeping mapping a wired key to the binding specification that wired it (see [Binding/README.md](../../../Binding/README.md)). Re-derived on every save, so hand-editing it has no effect; never part of the Channel API response.

## Slots

Slots hold arrays of elements.

```json
{
  "id": "main-container",
  "component": "CT:Grid",
  "properties": {
    "columns": "1"
  },
  "slots": {
    "header": [
      {"id": "logo", "component": "CT:Content:Image"},
      {"id": "navigation", "component": "CT:Navigation"},
      {"id": "search", "component": "CT:Search"}
    ],
    "main": [
      {"id": "blog-listing", "component": "CT:Blog:Listing"}
    ],
    "sidebar": [
      {"id": "filter-panel", "component": "CT:Filter:Panel"},
      {"id": "promo-banner", "component": "CT:Content:Image"}
    ]
  }
}
```

In this example, the `header` slot contains 3 elements, while `main` has 1 and `sidebar` has 2.

## Nested Containers

Containers can be nested for complex layouts:

```json
{
  "id": "page-layout",
  "component": "CT:Grid",
  "properties": {
    "cssClass": "page-wrapper",
    "columns": "1"
  },
  "slots": {
    "default": [
      {
        "id": "hero-section",
        "component": "CT:Grid",
        "properties": {
          "cssClass": "hero",
          "columns": "1"
        },
        "slots": {
          "default": [
            {
              "id": "heading",
              "component": "CT:Content:Text",
              "properties": {
                "text": "Summer Sale",
                "style": "heading"
              }
            },
            {
              "id": "cta-button",
              "component": "CT:Content:Button",
              "properties": {
                "label": "Shop Now"
              }
            }
          ]
        }
      }
    ]
  }
}
```
