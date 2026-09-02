# Plugins

Plugins are the most powerful extension mechanism for self-hosted Contena instances, providing direct access to the administration's runtime environment and APIs.

## Plugin Architecture

### Runtime Integration

- **Direct JavaScript Injection**: Plugin code executes within the administration bundle
- **Pre-Mount Registration**: Components, services, and modules register before Vue app initialization
- **Global Contena Object**: Central registry for all extension points

### Core Extension Points

#### 1. Component System

```javascript
// Register new component
Contena.Component.register('my-custom-component', {
    template: '<div>{{ message }}</div>',
    data() {
        return {
            message: 'Hello from plugin',
        };
    },
});

// Extend existing component (creates new component)
Contena.Component.extend('my-enhanced-field', 'ct-text-field', {
    computed: {
        additionalClasses() {
            return [
                'my-custom-class',
                ...this.$super('additionalClasses'),
            ];
        },
    },
});

// Override existing component (replaces original)
Contena.Component.override('ct-text-field', {
    methods: {
        onInput() {
            // Call original method
            this.$super('onInput');

            // Add custom logic
            this.validateCustomRules();
        },
    },
});
```

#### 2. Service Registration

```javascript
// Register custom service
Contena.Service().register('myCustomService', () => {
    return {
        processData(data) {
            // Custom business logic
            return transformedData;
        },
    };
});

// Decorate existing service
const originalApiService = Contena.Service('apiService');
Contena.Service().register('apiService', () => {
    return {
        ...originalApiService,
        request(config) {
            // Add authentication headers
            config.headers = {
                ...config.headers,
                'X-Custom-Auth': this.getAuthToken(),
            };

            return originalApiService.request(config);
        },
    };
});
```

#### 3. Module Registration

```javascript
// Register complete new module
Contena.Module.register('my-custom-module', {
    type: 'plugin',
    name: 'custom-module',
    title: 'My Custom Module',
    description: 'Custom functionality for specific business needs',

    routes: {
        index: {
            component: 'my-custom-index',
            path: 'index',
        },
        detail: {
            component: 'my-custom-detail',
            path: 'detail/:id',
        },
    },

    navigation: [
        {
            id: 'my-custom-module',
            label: 'Custom Module',
            color: '#ff3d58',
            path: 'my.custom.module.index',
            icon: 'regular-products',
            parent: 'ct-catalogue',
            position: 100,
        },
    ],
});
```

## Current Extension Systems

### 1. Component Factory System

```javascript
// Method extension with super calls
Contena.Component.override('ct-product-detail', {
    methods: {
        saveProduct() {
            // Pre-save validation
            if (!this.validateCustomFields()) {
                return;
            }

            // Call original save method
            return this.$super('saveProduct').then(() => {
                // Post-save actions
                this.sendAnalyticsEvent('product_saved');
            });
        },

        validateCustomFields() {
            // Custom validation logic
            return this.product.customFields?.requiredField?.length > 0;
        },
    },
});
```

### 2. Native Vue Block System

```vue
<template>
    <ct-block extends="ct_product-detail-tabs">
        <ct-block-parent />
        <mt-card title="Custom Configuration" />
    </ct-block>
</template>
```

### 3. Native Block System

- New template extensions
- Future-proof development
- Enhanced flexibility
- Native Vue component
- Better performance
- Works with SFC

**Implementation:**

```html
<!-- In component template -->
<ct-block name="ct_product-detail-tabs">
    <ct-tabs>
        <ct-block name="ct_product-detail-tab-basic">
            <ct-tabs-item>Basic Information</ct-tabs-item>
        </ct-block>

        <ct-block name="ct_product-detail-tab-advanced">
            <ct-tabs-item>Advanced Settings</ct-tabs-item>
        </ct-block>
    </ct-tabs>
</ct-block>

<!-- In plugin override -->
<ct-block name="ct_product-detail-tabs" extends="ct_product-detail-tabs">
    <ct-block-parent />

    <ct-block name="ct_product-detail-tab-custom">
        <ct-tabs-item>Custom Configuration</ct-tabs-item>
    </ct-block>
</ct-block>
```

### 4. Composition API Extensions

> **Stable** in Contena 6.8.
> See [04-composition-extension-system.md](./04-composition-extension-system.md) for the full technical reference.

Components migrated to Composition API expose a typed public API that extensions can override with full type safety. There are two roles: **component author** (uses `createExtendableSetup`) and **plugin author** (uses `overrideComponentSetup`).

#### Component Author: Making a Setup Extendable

Components opt into the extension system by wrapping their setup with `createExtendableSetup`. The setup return value is split into `public` (accessible to overrides) and `private` (internal only, available as `_private`):

```typescript
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import { ref, computed } from 'vue';

// Register the component's public API shape in the global type map
declare global {
    interface ComponentPublicApiMapping {
        'ct-product-list': {
            columns: Ref<Column[]>;
            totalCount: Ref<number>;
            loadData: () => Promise<void>;
        };
    }
}

export default {
    name: 'ct-product-list',
    props: { showDrafts: Boolean },
    setup(props) {
        return createExtendableSetup({ name: 'ct-product-list', props }, (props) => {
            const columns = ref<Column[]>([]);
            const totalCount = ref(0);
            const internalCursor = ref<string | null>(null); // kept private

            const loadData = async () => {
                /* ... */
            };

            return {
                public: { columns, totalCount, loadData },
                private: { internalCursor },
            };
        });
    },
};
```

Rules for `createExtendableSetup`:

- The `originalSetup` callback **must** return `{ public?, private? }` — at least one is required.
- Props must **not** be returned from the setup callback (enforced with a console error).
- `public` properties form the override API; `private` properties are accessible in overrides via `previousState._private`.

#### Plugin Author: Overriding Component Setup

```javascript
import { ref, computed } from 'vue';

Contena.Component.overrideComponentSetup()('ct-product-list', (previousState, props, context) => {
    const customFilters = ref([]);
    const isCustomMode = ref(false);

    // Extend an existing computed property — reads from the previous state ref
    const columns = computed(() => {
        const baseColumns = previousState.columns.value;

        if (isCustomMode.value) {
            return [
                ...baseColumns,
                { property: 'customScore', label: 'Custom Score', sortable: true },
            ];
        }

        return baseColumns;
    });

    // Override an existing method, calling the previous implementation
    const loadData = async () => {
        // Apply custom filters before delegating to the previous implementation
        customFilters.value.forEach((f) => {
            /* apply */
        });
        return previousState.loadData();
    };

    // Access private state (not part of the public API)
    const cursor = previousState._private.internalCursor;

    return {
        columns, // replaces the existing ref (2-way sync for plain refs)
        loadData, // replaces the existing function
        customFilters, // new ref added to component state
        isCustomMode, // new ref added to component state
    };
});
```

#### Supported Override Return Types

| Return value               | Behavior                                                                   |
| -------------------------- | -------------------------------------------------------------------------- |
| Plain `ref` (non-computed) | 2-way synced with the existing ref in the component state                  |
| `readonly` computed ref    | Replaces the existing property directly                                    |
| Writable computed ref      | Wrapped in a new computed with getter + setter                             |
| `reactive` object          | Merged into the existing reactive object (must preserve all existing keys) |
| `function`                 | Replaces the existing method directly                                      |

Returning a prop key in the override result logs a console error and the value is ignored.

#### Options API Backward-Compatibility Shim

When a plugin overrides a Composition API component using old-style Options API patterns (e.g. `data`, `methods`, `computed`, `watch`, mixins, or lifecycle hooks), the administration **automatically activates a compatibility shim** instead of throwing an error. A deprecation warning is logged to the browser console.

**Shim activation** — the shim is enabled when the override config contains any of:
`data`, `methods`, `computed`, `watch`, `mixins`, `inject`, `extends`, or any lifecycle hook name.

**Supported Options API features:**

| Feature         | Notes                                                                                                                                                     |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `data()`        | Each key becomes a `ref`                                                                                                                                  |
| `methods`       | Bound to a `this` proxy                                                                                                                                   |
| `computed`      | Converted to `computed()` refs; supports getter-only and getter+setter                                                                                    |
| `watch`         | Registered via `watch()`. Dot-notation paths are **not** supported                                                                                        |
| `inject`        | Array, `{ localKey: 'provideKey' }`, and `{ localKey: { from, default } }` forms                                                                          |
| `mixins`        | Flattened depth-first (deepest ancestor first), then merged                                                                                               |
| Lifecycle hooks | `beforeCreate`, `created`, `beforeMount`, `mounted`, `beforeUpdate`, `updated`, `beforeUnmount`, `unmounted`, `activated`, `deactivated`, `errorCaptured` |

**`this` proxy resolution order** inside shim methods/computed/watchers:

1. Local state (`data`, `computed`, `methods` from the override itself)
2. Injected values (`inject`)
3. Props
4. `previousState` (the component's existing Composition API state)

`this.$super('methodName', ...args)` calls the method on `previousState`. For computed refs, `$super` unwraps the ref value.

**Unsupported options (logged as warnings and ignored):**

| Option                                      | Level                                               | Reason                                                                                                                                                                                          |
| ------------------------------------------- | --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `components`, `directives`                  | `console.warn`                                      | Component/directive registration belongs to the component definition itself, not to overrides. Register them in the component config instead.                                                   |
| `provide`                                   | `console.warn`                                      | The provide/inject contract is established at component setup time. Overriding it after the fact could silently break descendant injections in unpredictable ways.                              |
| `template`                                  | `console.warn`                                      | Composition API components already have a compiled template; replacing it via an override would bypass Vue's template compiler pipeline and conflict with the existing render function.         |
| `extends`                                   | `console.warn`                                      | `extends` creates implicit inheritance chains that are difficult to merge reliably with Composition API state. Use `mixins` or explicit override methods instead.                               |
| `inheritAttrs`, `emits`                     | `console.warn`                                      | These are component-level declarations that affect how Vue compiles and validates the component. They cannot be changed at override time without re-compiling the component.                    |
| `render()`                                  | `console.error` — component will not work correctly | A custom render function completely replaces the compiled template. The shim cannot reconcile a custom render function with the existing Composition API template, so the component will break. |
| Dot-notation `watch` paths (e.g. `'a.b.c'`) | `console.warn` — watcher is skipped                 | Resolving nested reactive paths requires deep traversal of the Composition API state graph, which adds significant complexity for a pattern that is rarely used in plugins or core code.        |

**Lifecycle hooks applied late** (overrides registered after `setup()` has already returned):

- `beforeCreate`, `created`, `beforeMount`, `mounted` — called immediately
- `beforeUnmount`, `unmounted`, and other future hooks — cannot be registered; a warning is logged

#### Migration Guide: Options API Override → `overrideComponentSetup`

```javascript
// Before — Options API override (triggers shim + deprecation warning)
Contena.Component.override('ct-product-list', {
    data() {
        return { isCustomMode: false };
    },
    computed: {
        columns() {
            const base = this.$super('columns');
            return this.isCustomMode
                ? [
                      ...base,
                      { property: 'score' },
                  ]
                : base;
        },
    },
    methods: {
        async loadData() {
            await this.$super('loadData');
            this.isCustomMode = true;
        },
    },
});

// After — native Composition API override (no shim, fully typed)
Contena.Component.overrideComponentSetup()('ct-product-list', (previousState) => {
    const isCustomMode = ref(false);

    const columns = computed(() =>
        isCustomMode.value
            ? [
                  ...previousState.columns.value,
                  { property: 'score' },
              ]
            : previousState.columns.value,
    );

    const loadData = async () => {
        await previousState.loadData();
        isCustomMode.value = true;
    };

    return { isCustomMode, columns, loadData };
});
```

## Advanced Patterns

### State Management Integration

```javascript
// Register custom Vuex module
Contena.State.registerModule('myCustomModule', {
    namespaced: true,
    state: {
        customData: [],
    },
    mutations: {
        setCustomData(state, data) {
            state.customData = data;
        },
    },
    actions: {
        async loadCustomData({ commit }) {
            const data = await this._vm.$api.get('/custom-endpoint');
            commit('setCustomData', data);
        },
    },
});
```

### Repository Decoration

```javascript
// Enhance existing repository
const originalProductRepository = Contena.Service('repositoryFactory').create('product');

Contena.Service().register('productRepository', () => {
    return {
        ...originalProductRepository,

        async save(entity, context) {
            // Pre-save processing
            this.processCustomFields(entity);

            // Call original save
            const result = await originalProductRepository.save(entity, context);

            // Post-save actions
            this.triggerWebhooks(entity);

            return result;
        },
    };
});
```

## Best Practices

### 1. Component Extension Hierarchy

```javascript
// Good: Clear extension chain
Contena.Component.extend('my-base-field', 'ct-text-field', {
    // Base enhancements
});

Contena.Component.extend('my-specific-field', 'my-base-field', {
    // Specific functionality
});

// Avoid: Deep override chains that are hard to debug
```

### 2. Service Decoration

```javascript
// Good: Preserve original interface
const originalService = Contena.Service('originalService');
Contena.Service().register('originalService', () => ({
    ...originalService,
    enhancedMethod(data) {
        const processed = this.preProcess(data);
        return originalService.originalMethod(processed);
    },
}));
```

### 3. Error Handling

```javascript
Contena.Component.override('ct-entity-detail', {
    methods: {
        async saveEntity() {
            try {
                const result = await this.$super('saveEntity');
                this.onSaveSuccess(result);
                return result;
            } catch (error) {
                this.handleSaveError(error);
                throw error; // Re-throw to preserve original behavior
            }
        },
    },
});
```

## Limitations & Drawbacks

### Current System Challenges

1. **Complex Override Chains**: Deep extension hierarchies are difficult to debug
2. **Vue runtime resolution**: block slots are resolved reactively by Vue
3. **Breaking Changes**: Almost every core change can break extensions
4. **TypeScript Support**: Limited type safety with Options API extensions
5. **Performance Impact**: Runtime template compilation and deep override chains

### Migration Considerations

- **Testing Requirements**: Add component tests for SFC block and setup extension behavior
- **Documentation Updates**: Plugin documentation must cover multiple approaches
- **Developer Training**: Teams need to learn new extension patterns

## Future Roadmap

The plugin system is evolving toward:

1. **Full Composition API**: Native Vue 3 patterns with better TypeScript support
2. **Native Blocks**: Vue-native template extension points
3. **Enhanced Developer Experience**: Better debugging and development tools
4. **Improved Performance**: Elimination of runtime compilation overhead

For current development, use native Vue SFCs, `ct-block`, and `overrideComponentSetup()`.
