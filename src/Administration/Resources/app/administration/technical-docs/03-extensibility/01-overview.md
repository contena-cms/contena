# Extensibility Overview

The Contena 6 Administration provides multiple paradigms for extending and customizing the system to meet specific business requirements. This document outlines the different extension mechanisms, their evolution, and architectural decisions that shape the current extensibility landscape.

## Philosophy

Contena's extensibility philosophy centers on providing **extension points** that minimize the need for core modifications or forks. The system is designed to:

- Provide public APIs for common customization scenarios
- Maintain backward compatibility across minor versions
- Enable both simple customizations and complex business logic extensions
- Support both in-process and out-of-process extension models

## Extension Paradigms

### 1. Plugins (In-Process JavaScript/Vue Augmentation)

Plugins are the most powerful extension mechanism for self-hosted Contena instances. They allow direct modification of the administration's behavior through:

- **Component Registration**: Add new Vue components to the system
- **Component Extension**: Extend existing components using `Component.extend()`
- **Component Override**: Replace component behavior using `Component.override()`
- **Service Registration**: Add custom services and business logic
- **Module Registration**: Create entirely new administration modules

**Key Characteristics:**

- Full access to Contena's internal APIs
- Can modify core business logic
- Requires code deployment to the Contena server
- Not available in Contena Cloud environments
- Highest level of customization capability

### 2. Apps (Out-of-Process Iframe Integrations via Meteor Admin Extension SDK)

Apps represent Contena's cloud-native extension approach for administration customization:

- **Meteor Admin Extension SDK**: Iframe-based UI integrations within the administration
- **Admin Modules**: Create new administration modules and pages
- **Menu Extensions**: Add custom menu items and navigation
- **View Integration**: Embed external UI's into the existing administration layout
- **Event Communication**: Bidirectional communication between app and administration
- **Admin API Access**: Controlled access to administration APIs

**Key Characteristics:**

- Event-driven architecture within administration context
- Iframe-based UI isolation for security
- Cloud-compatible (required for Contena Cloud)
- Reduced system access through controlled SDK interface
- Administration-focused integration patterns

## Current Extension System Architecture

The administration extensibility is built on two foundational systems:

### 1. Component Factory and Native Vue Block System

The administration uses a centralized component factory for Vue components and a native block registry:

```javascript
// Register a Vue component
Contena.Component.register('my-component', {
    render: () => h('h1', 'Original header'),
});

// Extend existing component (creates new component based on existing)
Contena.Component.extend('my-extended-field', 'my-component', {
    // Additional functionality
});

// Override existing component (replaces original)
Contena.Component.override('my-component', {
    // Modified behavior
});
```

### 2. Native Block System + Composition API Extension System

Both systems are stable in Contena 6.8.

Vue-native block system for SFC templates:

```html
<!-- Define extensible block -->
<ct-block name="ct_product-header">
    <h1>{{ product.name }}</h1>
</ct-block>

<!-- Extend block in plugin -->
<ct-block name="ct_product-header" extends="ct_product-header">
    <ct-block-parent />
    <div class="custom-badge">New!</div>
</ct-block>
```

Modern extension pattern for Vue 3 components:

```javascript
// Override component behavior using Composition API
Contena.Component.overrideComponentSetup()('originalComponent', (previousState, props) => {
    const newMessage = 'Hello from the extension!';

    const newIncrement = () => {
        previousState.increment();

        if (props.showNotification) {
            // Add custom behavior
        }
    };

    return {
        message: newMessage,
        increment: newIncrement,
    };
});
```

## Evolution Journey & Migration Path

### Current System (Contena 6.8)

- Vue 3 Composition API with `overrideComponentSetup()` for behavior extensions
- Native `ct-block` components for template extension points
- Vue SFCs for all Administration components

## Stability Levels

### Public APIs (Stable)

- `Component.register`, `Component.extend`, and `Component.override` for Vue component registration and behavior composition
- Service registration patterns
- Event system interfaces
- Data handling abstractions

### Experimental APIs (Subject to Change)

- Advanced component lifecycle hooks

## Next Steps

Explore detailed documentation for each extension method:

- [Plugins](./02-plugins.md) - In-depth plugin development patterns
- [Apps](./03-apps.md) - Meteor Admin SDK integration
- [Composition API Extension System](./04-composition-extension-system.md) - Full technical reference for `createExtendableSetup` and `overrideComponentSetup`

## Tooling

Extension authors can type-check and lint their Administration code against the installed Contena version's live types and the Administration's own pinned TypeScript/ESLint setup with `composer admin:setup-extension-tooling` and `composer admin:check-extensions`. See [`extension-tooling/README.md`](../../extension-tooling/README.md) for details.

### Finding extension blocks in the running Administration

The Contena Vue DevTools plugin includes a development-only inspector named **Contena Extension Blocks**. It is the block counterpart of the position identifier inspector for apps:

- The tree lists every native block currently rendered, nested the way the blocks nest in the page. Blocks are tagged as `native` and as `extended` when a native extension targets them. Filtering matches block and component names while keeping the path to a match. The tree refreshes after route and DOM changes.
- Selecting a block frames it in the page with a label such as `<ct-block name="ct_blog_detail_base_content">`. Clicking or pressing Escape in the page removes the frame.
- The pick action lets you click any part of the page to select the innermost block underneath it. The click does not reach the page; Escape cancels. The tree focuses on the picked block and its nested blocks until the clear action restores the complete page tree.
- The state panel shows the owning component, enclosing blocks, the number of native `<ct-block extends>` registrations, and a copy-ready native extension snippet.

The inspector needs `data-ct-block` markers on rendered elements. Marking changes rendered output and is therefore opt-in and disabled by default: use the inspector's power action or run `localStorage.setItem('ct-admin-block-inspector', 'true')` in the console, then reload. The markers are never rendered in production builds.

Implementation: `src/core/factory/block-inspector.ts` owns marker state and metadata, native `ct-block` marks its rendered roots, and `src/app/adapter/view/block-inspector/` contains the DOM, tree, overlay, picker, and Vue DevTools integration.
