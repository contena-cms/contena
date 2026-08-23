# Module System

## Definition

A module in the Contena 6 Administration is a **self-contained business domain package** that encapsulates all functionality related to a specific area of the e-commerce platform. Modules are the primary organizational unit for features like product management, customer handling, order processing, and system configuration.

Each module operates as an independent unit with its own:

- User interface components and views
- Business logic and data services
- Navigation entries and routing
- Access control and permissions
- Translations and configuration

## Module Registration

### Registration Mechanism

Modules are registered using the `Module.register()` factory method from the core framework. The registration happens during application initialization through a centralized module loader.

```javascript
// Basic module registration pattern
Module.register('ct-product', {
    type: 'core',
    name: 'product',
    title: 'ct-product.general.mainMenuItemGeneral',
    description: 'ct-product.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'regular-products',
    entity: 'product',

    routes: {
        /* route definitions */
    },
    navigation: [
        /* navigation entries */
    ],
    defaultSearchConfiguration: {
        /* search config */
    },
});
```

### Module Factory API

The module factory (`core/factory/module.factory.ts`) provides the following key methods:

- **`registerModule(moduleId, manifest)`** - Registers a new module
- **`getModuleRegistry()`** - Returns all registered modules
- **`getModuleByEntityName(entityName)`** - Finds modules by entity
- **`getModuleRoutes()`** - Gets all module routes
- **`getModuleSnippets()`** - Retrieves translation snippets

### Module Manifest Structure

```typescript
interface ModuleManifest {
    // Core Properties
    type: 'core' | 'plugin';
    name: string;
    title: string;
    description?: string;
    version?: string;
    targetVersion?: string;

    // Visual Properties
    color?: string;
    icon?: string;
    favicon?: string;

    // Functionality
    entity?: string; // Associated data entity
    entityDisplayProperty?: string; // Property for display
    display?: boolean; // Whether to show in UI
    flag?: string; // Feature flag dependency

    // Routing
    routes: { [key: string]: RouteConfig };
    routeMiddleware?: Function;
    routePrefixName?: string;
    routePrefixPath?: string;
    coreRoute?: boolean;

    // Navigation
    navigation?: Navigation[];
    settingsItem?: SettingsItem[];

    // Extension Support
    extensionEntryRoute?: {
        extensionName: string;
        route: string;
    };

    // Search & Translations
    defaultSearchConfiguration?: SearchConfig;
    snippets?: { [lang: string]: unknown };
}
```

## Module Responsibilities

### 1. Route and Navigation Management

Modules define their URL structure and navigation entries:

```javascript
// Route definitions with nested structure
routes: {
    index: {
        component: 'ct-product-list',
        path: 'index',
        meta: { privilege: 'product.viewer' }
    },
    detail: {
        component: 'ct-product-detail',
        path: 'detail/:id?',
        children: {
            base: { component: 'ct-product-detail-base', path: 'base' },
            specifications: { component: 'ct-product-detail-specifications', path: 'specifications' },
            // ... more child routes
        }
    }
}
```

```javascript
// Navigation structure with hierarchy
navigation: [
    {
        id: 'ct-catalogue',
        label: 'global.ct-admin-menu.navigation.mainMenuItemCatalogue',
        color: '#57D9A3',
        icon: 'regular-products',
        position: 20,
    },
    {
        id: 'ct-product',
        label: 'ct-product.general.mainMenuItemGeneral',
        path: 'ct.product.index',
        parent: 'ct-catalogue',
        privilege: 'product.viewer',
        position: 10,
    },
];
```

### 2. Component and View Registration

Modules register their Vue components globally during initialization:

```javascript
// Component registration pattern
Contena.Component.register('ct-product-list', () => import('./page/ct-product-list'));
Contena.Component.register('ct-product-detail', () => import('./page/ct-product-detail'));
Contena.Component.register('ct-product-basic-form', () => import('./component/ct-product-basic-form'));

// Component extension pattern
Contena.Component.extend(
    'ct-product-visibility-select',
    'ct-entity-multi-select',
    () => import('./component/ct-product-visibility-select'),
);
```

### 3. State Management (Contena Store System)

Modules can define their own state stores for managing application state using Contena's custom store system based on Pinia:

```javascript
// Contena Store registration (actual pattern used in codebase)
const cmsPageStore = Contena.Store.register({
    id: 'cmsPageState',

    state: (): CmsPageState => ({
        currentPage: null,
        currentPageType: null,
        currentMappingEntity: null,
        currentMappingTypes: {},
        currentDemoEntity: null,
        currentDemoProducts: [],
        pageEntityName: 'cms_page',
        defaultMediaFolderId: null,
        currentCmsDeviceView: 'desktop',
        selectedSection: null,
        selectedBlock: null,
        isSystemDefaultLanguage: true
    }),

    actions: {
        setCurrentPage(page) {
            this.currentPage = page;
        },

        setCurrentPageType(pageType) {
            this.currentPageType = pageType;
        },

        resetCmsPageState() {
            this.currentPage = null;
            this.selectedSection = null;
            this.selectedBlock = null;
        }
    },

    getters: {
        getCurrentPage: (state) => state.currentPage,
        isSystemDefaultLanguage: (state) => state.isSystemDefaultLanguage
    }
});

// Usage in components:
// Access state: Contena.Store.get('cmsPageState')
// Call actions: cmsPageStore.setCurrentPage(page)
```

### 4. Service Registration

Modules provide business logic through services:

```javascript
// Service registration in module
Contena.Service().register('productService', () => {
    return {
        getProducts: () => {
            /* API calls */
        },
        saveProduct: (product) => {
            /* Save logic */
        },
        validateProduct: (product) => {
            /* Validation */
        },
    };
});
```

### 5. ACL Privileges Definition

Access Control Lists are defined per module to control permissions:

```javascript
// ACL privilege mapping
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'product',
    roles: {
        viewer: {
            privileges: [
                'product:read',
                'product_media:read',
                'category:read',
                // ... more read privileges
            ],
        },
        editor: {
            privileges: [
                'product:update',
                'product_media:create',
                'product_media:update',
                // ... more edit privileges
            ],
        },
        creator: {
            privileges: [
                'product:create',
                // ... creation privileges
            ],
        },
        deleter: {
            privileges: [
                'product:delete',
                // ... deletion privileges
            ],
        },
    },
});
```

### 6. Translation Snippets

Modules provide localized strings for their interface:

```javascript
// Snippet registration (typically in separate files)
snippets: {
    'zh-CN': {
        'ct-product': {
            'general': {
                'mainMenuItemGeneral': '产品',
                'descriptionTextModule': '在此管理产品'
            }
        }
    },
    'en-GB': {
        'ct-product': {
            'general': {
                'mainMenuItemGeneral': 'Products',
                'descriptionTextModule': 'Manage products'
            }
        }
    }
}
```

### 7. Search Configuration

Modules can contribute to the global search functionality:

```javascript
// Default search configuration
defaultSearchConfiguration: {
    _searchable: true,
    name: {
        _searchable: true,
        _score: 500
    },
    productNumber: {
        _searchable: true,
        _score: 500
    },
    manufacturer: {
        _searchable: true,
        _score: 100
    }
}
```

## Module Lifecycle

### Registration Flow

1. **Module Discovery** - Modules are discovered during app initialization
2. **Validation** - Module manifest is validated for required properties
3. **Route Processing** - Routes are sanitized and prefixed appropriately
4. **Component Registration** - Vue components are registered globally
5. **Navigation Setup** - Menu entries are added to navigation system
6. **Service Registration** - Module services are made available
7. **ACL Integration** - Privileges are integrated into permission system

### Lifecycle Hooks

The module system provides several extension points:

```javascript
// Route middleware for pre/post processing
routeMiddleware: (next, currentRoute) => {
    // Custom logic before route resolution
    if (hasPermission(currentRoute)) {
        next();
    } else {
        redirectToLogin();
    }
};
```

### Route Guards

Routes can have navigation guards for access control:

```javascript
routes: {
    detail: {
        component: 'ct-product-detail',
        path: 'detail/:id',
        beforeEnter: (to, from, next) => {
            // Custom route guard logic
            if (checkAccess(to.params.id)) {
                next();
            } else {
                next('/access-denied');
            }
        },
        meta: {
            privilege: 'product.viewer'
        }
    }
}
```

## Isolation and Coupling

### Isolation Goals

- **Encapsulation** - Each module manages its own domain logic
- **Independence** - Modules can be developed by separate teams
- **Testability** - Modules can be tested in isolation
- **Maintainability** - Changes in one module don't affect others

### Cross-Module Communication

Modules interact through well-defined interfaces:

```javascript
// Service-based communication
const customerService = Contena.Service('customerService');
const customer = await customerService.getCustomer(customerId);

// Repository pattern for data access
const productRepository = Contena.Service('repositoryFactory').create('product');
const products = await productRepository.search(criteria, context);

// Event-based communication
Contena.Application.getApplicationRoot().$emit('product-updated', product);
Contena.Application.getApplicationRoot().$on('customer-changed', this.handleCustomerChange);
```

### Service Dependencies

Modules depend on core services but avoid direct module-to-module dependencies:

```javascript
// Good: Using core services
const httpClient = Contena.Service('httpClient');
const stateManager = Contena.Service('stateManager');

// Avoid: Direct module imports
// import ProductModule from '../ct-product';  // ❌ Tight coupling
```

## Naming Conventions

### Module Naming

- **Core modules**: `ct-[domain]` (e.g., `ct-product`, `ct-customer`)
- **Plugin modules**: `[vendor]-[plugin]-[domain]` (e.g., `ct-paypal-payment`)
- **Settings modules**: `ct-settings-[area]` (e.g., `ct-settings-tax`)

### Component Naming

- **Pages**: `ct-[module]-[type]` (e.g., `ct-product-list`, `ct-product-detail`)
- **Views**: `ct-[module]-detail-[section]` (e.g., `ct-product-detail-base`)
- **Components**: `ct-[module]-[function]-[type]` (e.g., `ct-product-price-form`)

### Route Naming

- **Pattern**: `ct.[module].[action].[sub-action]`
- **Examples**: `ct.product.index`, `ct.product.detail`, `ct.product.detail.base`

## Standard Module Structure

```
ct-[module-name]/
├── acl/
│   └── index.js                    # ACL privilege definitions
├── component/                      # Reusable UI components
│   ├── ct-[module]-[component]/
│   │   ├── index.js
│   │   ├── ct-[module]-[component].vue
│   │   ├── ct-[module]-[component].scss
│   │   └── ct-[module]-[component].spec.js
│   └── ...
├── page/                          # Main page components
│   ├── ct-[module]-list/
│   ├── ct-[module]-detail/
│   └── ...
├── view/                          # Tab/section components
│   ├── ct-[module]-detail-base/
│   ├── ct-[module]-detail-[section]/
│   └── ...
├── service/                       # Business logic services
│   └── [module].service.js
├── state/                         # State management
│   └── [module].store.js
├── helper/                        # Utility functions
│   └── [module].helper.js
├── mixin/                         # Shared component logic
│   └── [module].mixin.js
├── snippet/                       # Translations
│   ├── zh-CN.json
│   ├── en-GB.json
│   └── ...
├── index.js                       # Module registration entry point
└── default-search-configuration.js # Search behavior definition
```

## Plugin Extensions

### Plugin Module Structure

Plugins follow the same structure but are located in `custom/plugins/[PluginName]/src/Resources/app/administration/src/`:

```
custom/plugins/[PluginName]/src/Resources/app/administration/src/
├── app/                           # Application extensions
├── core/                          # Core service extensions
├── module/                        # New modules or extensions
├── init/                          # Plugin initialization
├── constant/                      # Plugin constants
├── mixin/                         # Plugin-specific mixins
├── types/                         # TypeScript definitions
└── main.ts                        # Plugin entry point
```

### Extension Patterns

**1. Extending Existing Components**

```javascript
// Override existing components to add functionality
Contena.Component.override('ct-settings-payment-detail', () => import('./extension/ct-settings-payment-detail'));
Contena.Component.override('ct-sales-channel-modal-detail', () => import('./extension/ct-sales-channel-modal-detail'));
Contena.Component.override(
    'ct-first-run-wizard-paypal-credentials',
    () => import('./extension/ct-first-run-wizard-paypal-credentials'),
);
```

**2. Creating New Components**

```javascript
// Register new plugin-specific components
Contena.Component.register('ct-paypal-overview-card', () => import('./components/ct-paypal-overview-card'));
Contena.Component.register('ct-paypal-settings-icon', () => import('./components/ct-paypal-settings-icon'));
```

**3. Creating New Modules**

```javascript
// Create entirely new modules for plugin functionality
Contena.Module.register('ct-paypal-disputes', {
    type: 'plugin',
    name: 'paypal-disputes',
    title: 'ct-paypal.disputes.title',
    description: 'ct-paypal.disputes.description',
    color: '#009cde',
    icon: 'regular-fingerprint',

    routes: {
        index: {
            component: 'ct-paypal-disputes-list',
            path: 'index',
        },
    },

    navigation: [
        {
            id: 'ct-paypal-disputes',
            label: 'ct-paypal.disputes.title',
            parent: 'ct-order',
            position: 100,
        },
    ],
});
```

**4. Service Registration and Access**

```javascript
// Register new services (actual pattern from codebase)
Contena.Service().register('paypalPaymentService', (container) => {
    return {
        processPayment: (payment) => {
            // Custom PayPal payment processing logic
            return this.handlePayPalSpecificFlow(payment);
        },

        validatePayment: (payment) => {
            // PayPal-specific validation
            return this.validatePayPalPayment(payment);
        },
    };
});

// Access existing services (actual pattern from codebase)
const aclService = Contena.Service('acl');
const repositoryFactory = Contena.Service('repositoryFactory');
const loginService = Contena.Service('loginService');

// Service composition pattern - create new services that use existing ones
Contena.Service().register('extendedPaymentService', (container) => {
    const originalPaymentService = Contena.Service('paymentService');

    return {
        // Delegate to original service
        processStandardPayment: originalPaymentService.processPayment,

        // Add new functionality
        processPayPalPayment: (payment) => {
            // Custom PayPal logic
            return this.handlePayPalSpecificFlow(payment);
        },
    };
});
```

**5. Service Decoration Pattern**

```javascript
// Since there's no override method, plugins typically create wrapper services
// or use dependency injection to replace services at registration time
Contena.Service().register('paymentService', (container) => {
    // Get dependencies
    const httpClient = container.httpClient;
    const apiService = container.apiService;

    // Return service with extended functionality
    return {
        processPayment: (payment) => {
            // Add PayPal-specific logic before/after standard processing
            if (payment.method === 'paypal') {
                return this.processPayPalPayment(payment);
            }
            return this.processStandardPayment(payment);
        },
    };
});
```
