# Module Layer - AGENTS.md

> **Full Docs**: `technical-docs/02-architecture/03-module-system.md`
> **Skill**: Module work → follow `contena-admin-js` (`.agents/skills/contena-admin-js/SKILL.md`).

## Module Structure

```
sw-[module]/
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
Contena.Component.register('sw-blog-list', () => import('./page/sw-blog-list'));
Contena.Component.register('sw-blog-detail', () => import('./page/sw-blog-detail'));

// 2. Register module
Module.register('sw-blog', {
  type: 'core',
  name: 'blog',
  entity: 'blog',
  title: 'sw-blog.general.mainMenuItemGeneral',
  color: '#57D9A3',
  icon: 'regular-blogs',

  routes: {
    index: {
      component: 'sw-blog-list',
      path: 'index',
      meta: { privilege: 'blog.viewer' }
    },
    detail: {
      component: 'sw-blog-detail',
      path: 'detail/:id?',
      meta: { privilege: 'blog.viewer' },
      children: {
        base: {
          component: 'sw-blog-detail-base',
          path: 'base'
        }
      }
    }
  },

  navigation: [{
    id: 'sw-blog',
    label: 'sw-blog.general.mainMenuItemGeneral',
    path: 'sw.blog.index',
    parent: 'sw-catalogue',
    privilege: 'blog.viewer',
    position: 10
  }]
});
```

## ACL Configuration

```js
Contena.Service('privileges').addPrivilegeMappingEntry({
  category: 'permissions',
  parent: 'catalogues',
  key: 'blog',

  roles: {
    viewer: {
      privileges: ['blog:read', 'manufacturer:read'],
      dependencies: []
    },
    editor: {
      privileges: ['blog:update'],
      dependencies: ['blog.viewer']
    },
    creator: {
      privileges: ['blog:create'],
      dependencies: ['blog.viewer', 'blog.editor']
    },
    deleter: {
      privileges: ['blog:delete'],
      dependencies: ['blog.viewer']
    }
  }
});
```

## Page Patterns

### List Page
```ts
export default {
  inject: ['repositoryFactory', 'acl'],
  mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

  computed: {
    repository() {
      return this.repositoryFactory.create('blog');
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
      return this.repositoryFactory.create('blog');
    },
    ...mapPropertyErrors('blog', ['name', 'price'])
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

      this.createNotificationSuccess({ message: this.$t('sw.blog.detail.messageSaved') });
    }
  }
};
```

## Snippets (i18n)

German (`de-DE`) Administration snippets use the informal "Du" (unlike the Frontend, which uses "Sie").

```json
{
  "sw-blog": {
    "general": {
      "mainMenuItemGeneral": "Blogs"
    },
    "list": {
      "title": "Blogs",
      "buttonCreate": "Add blog"
    },
    "detail": {
      "labelName": "Name",
      "messageSaved": "Blog saved successfully"
    }
  }
}
```

**Usage**: `this.$t('sw.blog.list.title')`


## Anti-Patterns

❌ Cross-module imports (modules must be independent)
❌ Missing ACL checks in routes/templates
❌ Not reloading after save
❌ Hardcoded strings (use `$t()` for translations)
❌ Large components (split into views/components)
❌ Business logic in templates

**See**: `technical-docs/02-architecture/03-module-system.md`
