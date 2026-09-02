# Module Layer - AGENTS.md

> **Full Docs**: `technical-docs/02-architecture/03-module-system.md`
> **Skill**: Module work → follow `contena-admin-js` (`.agents/skills/contena-admin-js/SKILL.md`).

## Module Structure

```
ct-[module]/
├── index.js          # Module registration (REQUIRED)
├── acl/index.js      # Privileges
├── page/             # List/detail pages
├── view/             # Detail tabs
├── component/        # Module components
├── snippet/          # Translations
└── default-search-configuration.js
```

## Registration Pattern

```js
// 1. Register components (lazy-loaded)
Contena.Component.register('ct-product-list', () => import('./page/ct-product-list'));
Contena.Component.register('ct-product-detail', () => import('./page/ct-product-detail'));

// 2. Register module
Module.register('ct-product', {
  type: 'core',
  name: 'product',
  entity: 'product',
  title: 'ct-product.general.mainMenuItemGeneral',
  color: '#57D9A3',
  icon: 'solid-products',

  routes: {
    index: {
      component: 'ct-product-list',
      path: 'index',
      meta: { privilege: 'product.viewer' }
    },
    detail: {
      component: 'ct-product-detail',
      path: 'detail/:id?',
      meta: { privilege: 'product.viewer' },
      children: {
        base: {
          component: 'ct-product-detail-base',
          path: 'base'
        }
      }
    }
  },

  navigation: [{
    id: 'ct-product',
    label: 'ct-product.general.mainMenuItemGeneral',
    path: 'ct.product.index',
    parent: 'ct-catalogue',
    privilege: 'product.viewer',
    position: 10
  }]
});
```

## ACL Configuration

```js
Contena.Service('privileges').addPrivilegeMappingEntry({
  category: 'permissions',
  parent: 'catalogues',
  key: 'product',

  roles: {
    viewer: {
      privileges: ['product:read', 'manufacturer:read'],
      dependencies: []
    },
    editor: {
      privileges: ['product:update'],
      dependencies: ['product.viewer']
    },
    creator: {
      privileges: ['product:create'],
      dependencies: ['product.viewer', 'product.editor']
    },
    deleter: {
      privileges: ['product:delete'],
      dependencies: ['product.viewer']
    }
  }
});
```

## Page Patterns

### Extendable SFC Requirement

New pages and pages undergoing a substantive migration must use a native `.vue` SFC. Do not create a new `.html.twig` runtime template. Base pages declare their public extension surface with one top-level `ctDefinePublic({ ... })`; other top-level bindings are private. Overrides live in `.override.vue` files, consume `useCtPreviousState()` when needed, and declare replacements with `ctDefineOverride({ ... })`. Do not author `createExtendableSetup` wrappers or `ComponentPublicApiMapping` entries.

Expose page-level seams with named `<ct-block name="ct_...">` elements around content and actions. The build transform injects `:data="$dataScope"`; authors must not add it manually. Use `<ct-block extends="ct_...">` plus `<ct-block-parent />` in extension components that add to existing output. Keep block names stable, snake_case, and prefixed with `ct_`; never register an override block inside a `v-for` loop. Structural `ct-page` and `ct-block` are extension infrastructure, while visible controls should use `mt-*` components.

The Options API examples below document existing legacy pages only; do not copy them for new work or SFC migrations.

### List Page
```ts
export default {
  inject: ['repositoryFactory', 'acl'],
  mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

  computed: {
    repository() {
      return this.repositoryFactory.create('product');
    },

    criteria() {
      const criteria = new Criteria(this.page, this.limit);
      criteria.setTerm(this.term);
      criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
      return criteria;
    }
  },

  methods: {
    async getList() {
      this.isLoading = true;
      this.items = await this.repository.search(this.criteria, Contena.Context.api);
      this.total = this.items.total;
      this.isLoading = false;
    }
  }
};
```

### Detail Page
```ts
export default {
  inject: ['repositoryFactory', 'acl'],
  mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

  computed: {
    repository() {
      return this.repositoryFactory.create('product');
    },
    ...mapPropertyErrors('product', ['name', 'price'])
  },

  methods: {
    async loadEntity() {
      const criteria = new Criteria();
      criteria.addAssociation('manufacturer');

      this.entity = await this.repository.get(this.entityId, Contena.Context.api, criteria);
    },

    async onSave() {
      await this.repository.save(this.entity, Contena.Context.api);

      // ✅ CRITICAL: Reload to sync origin for change tracking
      this.entity = await this.repository.get(this.entity.id, Contena.Context.api);

      this.createNotificationSuccess({ message: this.$tc('ct.product.detail.messageSaved') });
    }
  }
};
```

## Snippets (i18n)

```json
{
  "ct-product": {
    "general": {
      "mainMenuItemGeneral": "Products"
    },
    "list": {
      "title": "Products",
      "buttonCreate": "Add product"
    },
    "detail": {
      "labelName": "Name",
      "messageSaved": "Product saved successfully"
    }
  }
}
```

**Usage**: `this.$tc('ct.product.list.title')`


## Anti-Patterns

❌ Cross-module imports (modules must be independent)
❌ Missing ACL checks in routes/templates
❌ Not reloading after save
❌ Hardcoded strings (use `$tc()` for translations)
❌ Large components (split into views/components)
❌ Business logic in templates

**See**: `technical-docs/02-architecture/03-module-system.md`
