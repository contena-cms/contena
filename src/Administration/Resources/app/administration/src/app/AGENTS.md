# App Layer - AGENTS.md

> **Full Docs**: `technical-docs/02-architecture/` for boot process, folder structure, state management

## Critical Patterns

### Boot Sequence (Order Matters!)
```
init-pre/ → init/ → init-post/
```
**See**: `src/app/init/AGENTS.md` for details

### Dependency Injection (NOT imports)
```ts
// ✅ CORRECT
inject: ['repositoryFactory', 'acl']

// ❌ WRONG
import repositoryFactory from '...';
```

### Global Components Only
```ts
// ✅ Registered globally in init/component.init.ts
Contena.Component.register('ct-product-list', () => import('./page'));

// ❌ Local imports break plugin system
import CtProductList from './page';
```

## Directory Overview

- **`init/`**: Boot sequence (See AGENTS.md)
- **`component/`**: Global UI components (See AGENTS.md)
- **`store/`**: Pinia stores (See AGENTS.md)
- **`composables/`**: Vue 3 hooks (use-context, use-session, use-system)
- **`mixin/`**: Legacy shared logic (prefer composables)
- **`assets/scss/`**: Global styles, variables, mixins
- **`snippet/`**: Translations (zh.json, en.json)

## Component Development

```ts
export default {
  inject: ['repositoryFactory', 'acl'],
  mixins: [Mixin.getByName('notification')],

  computed: {
    repository() {
      return this.repositoryFactory.create('product');
    },
    ...mapPropertyErrors('product', ['name'])
  },

  methods: {
    async save() {
      await this.repository.save(this.entity, Contena.Context.api);
      this.entity = await this.repository.get(this.entity.id, Contena.Context.api);
      this.createNotificationSuccess({ message: this.$tc('saved') });
    }
  }
};
```

## Component Pattern (Vue SFC)

```vue
<template>
  <mt-card>
    <mt-text-field v-model="name" />
  </mt-card>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const name = ref('');

ctDefinePublic({ name });
</script>
```

All Administration components must be Vue SFCs. Base components use `ctDefinePublic`, and `.override.vue` components use `ctDefineOverride`; do not wrap author code in `createExtendableSetup` or add `ComponentPublicApiMapping`. Do not create or retain `.html.twig` component templates or TwigJS runtime dependencies. Preserve required extension points with the native extension system while converting legacy templates, then delete the Twig files in the same change.

## State Management

```ts
// Register
Contena.Store.register({ id: 'myStore', state, actions, getters });

// Access
const store = Contena.Store.get('myStore');
```

**See**: `src/app/store/AGENTS.md` for patterns

## Styling (BEM + Meteor Tokens)

```scss
.ct-product-list {
  padding: var(--mt-spacing-4);
  color: var(--mt-color-text-primary);

  &__header { }
  &__grid { }
}
```

## Anti-Patterns

❌ Local component imports
❌ Direct DOM manipulation
❌ Mutating props
❌ Business logic in templates
❌ Inline styles
❌ Using mixins for new code (use composables)

**See**: `technical-docs/02-architecture/02-folder-structure.md` for complete structure
