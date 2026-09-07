# The Native `ct-block` System in Contena 6 Administration

> **Status**: Stable in Contena 6.8. Administration components use Vue SFCs and this native block system exclusively.

---

## Why It Exists

Administration blocks are resolved by Vue at runtime. The component template itself is compiled as part of the normal SFC build:

- No runtime template compilation overhead
- Templates are written as native `.vue` SFCs
- Template state and extension APIs are TypeScript-aware
- Overrides remain visible in the normal Vue component/devtools flow

The **native block system** replaces all of that with pure Vue 3 components. Blocks and overrides are registered and resolved using Vue's reactivity model, so there is no separate compilation step and no secondary templating language.

---

## The Two Components

### `ct-block` — the extension point

`ct-block` serves double duty depending on which props it receives:

| Mode         | Props     | Purpose                                                                        |
| ------------ | --------- | ------------------------------------------------------------------------------ |
| **Define**   | `name`    | Creates an extension point with default content                                |
| **Override** | `extends` | Registers new content for a named extension point — **renders nothing itself** |

> **`<ct-block extends>` is registration-only.** When the `extends` prop is set, `ct-block` registers its default slot in the global block registry and returns `{ template: null }` — no HTML is emitted at the location where the tag appears. The position of `<ct-block extends>` inside a template is therefore irrelevant to rendering. The only requirement is that the component is **mounted** (not blocked from mounting by an ancestor `v-if`) so that `addBlock()` is called and the slot is picked up by the target `<ct-block name>`.

### `ct-block-parent` — the parent content placeholder

Used inside an override block to render the content from the previous block in the chain (the default content, or the previous override).

---

## Basic Usage

### Defining an extension point

In a component SFC:

```html
<ct-block name="ct_product_detail_summary">
    <p>Default summary content</p>
</ct-block>
```

- `name` — identifier for this block, scoped to the owning component. Like a Twig `{% block %}`, a block is addressed by `componentName + blockName`, so the same block name in two different components never collide: a `<ct-block extends="...">` only resolves against the `<ct-block name="...">` in the same component. The Contena setup transform stamps the owning component name onto every `<ct-block>` (the `ct-internal-component-name` attribute) when it lowers the SFC; authors do not write it. Block names use the `ct_` prefix and snake_case (e.g., `ct_product_detail_summary`).
- The block's owning component and data scope are wired by the Contena setup transform; `name` (or `extends`) is the only binding an author writes on `<ct-block>`.

### Complete end-to-end example

The following shows both sides together: the base component that declares the block and a plugin component that overrides it.

```html
<!-- ── Base component: ct-product-detail.vue ── -->
<div class="ct-product-detail">
    <ct-block name="ct_product_detail_summary">
        <p>Default summary content</p>
    </ct-block>
</div>
```

```html
<!-- ── Plugin override component template ── -->
<!--                                                                    -->
<!-- <ct-block extends> renders nothing at the position it is placed.   -->
<!-- Its slot is registered globally and picked up by the named block.  -->
<ct-block extends="ct_product_detail_summary">
    <ct-block-parent />
    <p class="my-badge">Added by MyPlugin</p>
</ct-block>
```

Rendered output:

```html
<div class="ct-product-detail">
    <p>Default summary content</p>
    <!-- rendered by <ct-block-parent /> -->
    <p class="my-badge">Added by MyPlugin</p>
</div>
```

### Overriding a block (replace)

```html
<!-- Replaces the default content entirely -->
<ct-block extends="ct_product_detail_summary">
    <p class="custom-summary">My custom summary</p>
</ct-block>
```

### Extending a block (wrap / append)

```html
<!-- Keeps the default content and adds to it -->
<ct-block extends="ct_product_detail_summary">
    <ct-block-parent />
    <div class="custom-badge">New!</div>
</ct-block>
```

`<ct-block-parent />` renders whatever the previous block in the chain produced. Placing it before or after your content controls the insertion point:

```html
<!-- Prepend: custom content appears BEFORE default -->
<ct-block extends="ct_product_detail_summary">
    <div class="prepended">I go first</div>
    <ct-block-parent />
</ct-block>

<!-- Append: custom content appears AFTER default -->
<ct-block extends="ct_product_detail_summary">
    <ct-block-parent />
    <div class="appended">I go last</div>
</ct-block>
```

---

## Multiple Overrides Chaining

Multiple `ct-block extends="..."` blocks for the same name are supported and form a **chain**. Each override's `<ct-block-parent />` renders the previous override's output (not the original default directly).

```html
<!-- Override 1 -->
<ct-block extends="ct_product_detail_summary">
    <ct-block-parent />
    <div class="from-plugin-a">Added by Plugin A</div>
</ct-block>

<!-- Override 2 -->
<ct-block extends="ct_product_detail_summary">
    <ct-block-parent />
    <div class="from-plugin-b">Added by Plugin B</div>
</ct-block>
```

**Rendered output:**

```
[default content]
[Plugin A addition]
[Plugin B addition]
```

When there are multiple overrides and none uses `<ct-block-parent />`, only the **last registered** override is rendered. The earlier ones are silently discarded:

```html
<ct-block extends="ct_product_detail_summary">
    <div class="from-plugin-a">Plugin A (never shown)</div>
</ct-block>

<ct-block extends="ct_product_detail_summary">
    <div class="from-plugin-b">Plugin B (shown)</div>
</ct-block>
```

---

## Accessing State Around a Block

Override blocks are rendered outside the component they extend, so they have no implicit access to its reactive state. State flows through the Contena setup transform instead:

- The owning component's data scope is wired to every named `<ct-block>` by the transform, which is how `<ct-block-parent />` content keeps rendering with the base component's state.
- Inside `<ct-block extends>` content, an override references its own setup bindings directly. Public base state is read through `useCtPreviousState()`.

```vue
<template>
    <ct-block extends="ct_blog_title">
        <ct-block-parent />
        <span class="custom-title">{{ customTitle }}</span>
    </ct-block>
</template>

<script setup>
import { computed } from 'vue';

const previousState = useCtPreviousState();
const customTitle = computed(() => `${previousState.title.value}!`);

ctDefineOverride({});
</script>
```

See [`07-native-setup-authoring.md`](./07-native-setup-authoring.md) for the authoring rules.

---

## Nested Blocks

Blocks can be nested freely. Each block is independently overrideable:

```html
<!-- Component template -->
<ct-block name="ct_product_tabs">
    <div class="tabs">
        <ct-block name="ct_product_tab_basic">
            <span>Basic Info</span>
        </ct-block>

        <ct-block name="ct_product_tab_advanced">
            <span>Advanced</span>
        </ct-block>
    </div>
</ct-block>

<!-- Plugin: add a new tab without touching the outer block -->
<ct-block extends="ct_product_tabs">
    <ct-block-parent />
    <span>Custom Tab</span>
</ct-block>
```

New named blocks are declared by the base components that own them; override files use `extends` to contribute into existing blocks.

---

## How It Works Internally

### The global block registry

The block system uses a module-level reactive object as its registry, exposed via the `useBlockContext` composable:

```1:46:src/Administration/Resources/app/administration/src/app/composables/use-block-context.ts
const blockContext: Record<string, Slot[]> = reactive({});

function getBlocks(blockName: string): Slot[] {
    return blockContext[blockName] ?? [];
}

function addBlock(blockName: string, block?: Slot): void {
    if (!block) {
        return;
    }
    if (!blockContext[blockName]) {
        blockContext[blockName] = [];
    }
    blockContext[blockName].push(block);
}

function removeBlock(blockName: string, block?: Slot): void {
    if (!block) {
        return;
    }
    if (!blockContext[blockName]) {
        return;
    }
    blockContext[blockName] = blockContext[blockName].filter((b) => b !== block);

    if (blockContext[blockName].length === 0) {
        delete blockContext[blockName];
    }
}
```

The registry maps a block name to an ordered array of Vue `Slot` functions. Every `ct-block extends="..."` adds its default slot to this array on mount and removes it on `onBeforeUnmount`.

### `ct-block` render logic

```59:114:src/Administration/Resources/app/administration/src/app/component/structure/ct-block-override/ct-block/index.ts
export default Contena.Component.wrapComponentConfig({
    props: {
        name: {
            type: String,
        },
        extends: {
            type: String,
        },
        data: {
            type: Object as PropType<ComponentInternalInstance['proxy']>,
            default: null,
        },
    },
    setup(props, { slots }) {
        const { addBlock, removeBlock, getBlocks } = useBlockContext();
        if (props.extends) {
            addBlock(props.extends, slots.default);

            onBeforeUnmount(() => {
                if (props.extends) {
                    removeBlock(props.extends, slots.default);
                }
            });

            return { template: null };
        }

        const providedParents = ref<ReturnType<Slot>[]>([]);
        provide(parentsInjectionKey, providedParents);

        const template = computed(() => {
            if (!props.name) {
                throw new Error('[ct-block] The "name" prop is required when "extends" is not set.');
            }

            const blocks = getBlocks(props.name);
            const blocksAndParent = [
                slots.default ?? (() => []),
                ...blocks,
            ];
            const blocksNodes = blocksAndParent.map((block) => block?.(props.data));

            const lastNode = blocksNodes.pop();
            // Reset the list on every render so unconsumed entries from the previous cycle
            // are released and each ct-block-parent pops the correct slot.
            providedParents.value = blocksNodes;
            return lastNode;
        });

        return {
            template,
        };
    },
    render() {
        return this.template;
    },
});
```

The key steps when rendering a **named block** (`name` prop):

1. Retrieve all registered override slots from `getBlocks(name)`
2. Build an array: `[defaultSlot, ...overrideSlots]`
3. Call each slot function with the `data` prop (making scope available)
4. **Pop the last element** — that is what actually gets rendered
5. **Assign all others** to the `providedParents` ref (exposed via `provide`), replacing the previous list so stale entries are released

This is why the last registered override wins when no `<ct-block-parent />` is used.

### `ct-block-parent` render logic

```1:26:src/Administration/Resources/app/administration/src/app/component/structure/ct-block-override/ct-block-parent/index.ts
import { h, inject } from 'vue';
import parentsInjectionKey from '../ct-block/parents-injection-key';

export default Contena.Component.wrapComponentConfig({
    setup() {
        const parent = inject(parentsInjectionKey, null)?.value.pop();

        return {
            parent,
        };
    },
    render() {
        return h(() => this.parent);
    },
});
```

`ct-block-parent` **injects** the `providedParents` array from the nearest ancestor `ct-block` (via Vue's provide/inject using a Symbol key), and **pops** the last element from it — which is the pre-rendered VNode array of the previous block in the chain. It then renders that as its output.

### Data flow diagram

```
Component with <ct-block name="ct_foo">
│
│  Mount
│
│  useBlockContext.getBlocks("component-name ct_foo")
│  → [defaultSlot, overrideSlot1, overrideSlot2]
│
│  Call each slot with $dataScope
│  → [defaultVNodes, override1VNodes, override2VNodes]
│
│  provide(parentsInjectionKey, [defaultVNodes, override1VNodes])
│  render → override2VNodes   ← last one wins
│
│       ↓ inside override2VNodes template ↓
│
│  <ct-block-parent />
│  inject(parentsInjectionKey).pop()
│  → override1VNodes   ← previous in chain
│
│       ↓ inside override1VNodes template ↓
│
│  <ct-block-parent />
│  inject(parentsInjectionKey).pop()
│  → defaultVNodes
```

---

## Lifecycle Reactivity

Because override `ct-block` components register and deregister themselves using Vue's lifecycle hooks, the system is fully reactive to mounting and unmounting:

- An override's content appears as soon as the `ct-block extends="..."` mounts
- It disappears when it unmounts (e.g., when a plugin's component is conditionally hidden with `v-if`)
- Multiple mount/unmount cycles do not accumulate duplicates

This is verified in the test suite — toggling `v-if` on an override component correctly adds and removes its contribution without leaving stale entries.

---

## Native Block Contract

Administration blocks are native Vue components. Components are compiled from SFCs, while block overrides are resolved reactively at runtime. `<ct-block-parent />` renders the previous slot in the chain and scoped slots provide the owning component's data.

---

## Known Limitations

The native block system has these limitations:

**`v-if` / `v-else` disruption** — inserting an `ct-block` between `v-if` and `v-else` siblings breaks Vue's conditional rendering, because the block inserts a DOM node between them:

```html
<!-- ❌ This breaks v-else -->
<div v-if="condition">...</div>
<ct-block name="ct_between_conditions">...</ct-block>
<div v-else>...</div>
```

**Slot composition breakage** — placing an `ct-block` between a `<template #slot>` and its intended parent component disrupts Vue's slot composition.

**`<ct-block-parent />` inside `v-for`** — prohibited. Each list iteration creates a separate `ct-block-parent` instance, and each calls `.pop()` on the shared `providedParents` array during `setup()`. Multiple pops in a single render pass consume more parent slots than intended, silently corrupting the chain:

```html
<!-- ❌ Multiple instances each pop() a different slot from the chain -->
<ct-block extends="ct_product_detail_summary">
    <template v-for="item in items">
        <ct-block-parent />
    </template>
</ct-block>
```

**`<ct-block-parent />` inside `v-if`** — unsupported. A toggle that unmounts then remounts `<ct-block-parent />` re-runs `setup()`, which calls `.pop()` again. The parent `ct-block` resets `providedParents` in its `template` computed on each re-render, but the interleaving between that reset and the child's mount order is not guaranteed to be safe:

```html
<!-- ❌ Re-mounting ct-block-parent calls .pop() again -->
<ct-block extends="ct_product_detail_summary">
    <ct-block-parent v-if="condition" />
    <div>My content</div>
</ct-block>
```

**`v-if` / `v-else` directly on `<ct-block-parent />`** — prohibited. `<ct-block-parent />` must not be part of a Vue conditional chain. It must always render unconditionally inside an extending block, otherwise local `v-else` branches can break the parent chain resolution:

```html
<!-- ❌ Conditional ct-block-parent breaks the parent chain -->
<ct-block extends="ct_product_detail_summary">
    <ct-block-parent v-if="showParent" />
    <div v-else>Local fallback</div>
</ct-block>
```

**`<ct-block extends>` inside `v-for`** — prohibited. Each iteration independently calls `addBlock()`, registering a separate override entry per list item and causing the override content to be rendered multiple times:

```html
<!-- ❌ Registers one override per list item -->
<template v-for="item in items">
    <ct-block extends="ct_product_detail_summary">
        <div>{{ item.name }}</div>
    </ct-block>
</template>
```

> **Note:** `<ct-block extends>` inside `v-if` is explicitly **supported**. The `addBlock`/`removeBlock` lifecycle hooks handle registration and deregistration correctly as the component mounts and unmounts. See [Lifecycle Reactivity](#lifecycle-reactivity) above.

---

## Summary

The `ct-block` system provides two Vue components:

- `<ct-block name="ct_...">` — declares an extension point with default content; reactively incorporates any registered overrides at render time
- `<ct-block extends="ct_...">` — registers override content for a named block; renders nothing itself, just adds its slot to the global registry
- `<ct-block-parent />` — renders the previous content in the chain (default or prior override), via Vue's provide/inject

The global block registry (`useBlockContext`) is a reactive module-level map of block names to ordered slot arrays. The last registered override is always the outermost render layer; `<ct-block-parent />` walks backwards through the chain via Vue's `inject`.
