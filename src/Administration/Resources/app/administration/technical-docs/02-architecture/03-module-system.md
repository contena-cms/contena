# Module System

## Definition

A module in the Contena 6 Administration is a **self-contained business domain package** that encapsulates all functionality related to a specific area of the e-commerce platform. Modules are the primary organizational unit for features like blog management, user handling, order processing, and system configuration.

Each module operates as an independent unit with its own:
- AdminUser interface components and views
- Business logic and data services
- Navigation entries and routing
- Access control and permissions
- Translations and configuration

## Module Registration

### Registration Mechanism

Modules are registered using the `Module.register()` factory method from the core framework. The registration happens during application initialization through a centralized module loader.

```javascript
// Basic module registration pattern
Module.register('sw-blog', {
    type: 'core',
    name: 'blog',
    title: 'sw-blog.general.mainMenuItemGeneral',
    description: 'sw-blog.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'regular-blogs',
    entity: 'blog',
    
    routes: { /* route definitions */ },
    navigation: [ /* navigation entries */ ],
    defaultSearchConfiguration: { /* search config */ }
});
```

### Module Factory API

The module factory (`core/factory/module.factory.ts`) provides the following key methods:

- **`registerModule(moduleId, manifest)`** - Registers a new module
- **`getModuleRegistry()`** - Returns all registered modules
- **`getModuleByEntityName(entityName)`** - Finds modules by entity
- **`getModuleRoutes()`** - Gets all module routes
- **`getModuleSnippets()`** - Retrieves translation snippets (**deprecated**, will be removed in v6.8.0)

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
    entity?: string;                    // Associated data entity
    entityDisplayProperty?: string;     // Property for display
    display?: boolean;                  // Whether to show in UI
    flag?: string;                      // Feature flag dependency
    
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
    /** @deprecated tag:v6.8.0 - Will be removed, snippets are loaded from the `snippet` folders */
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
        component: 'sw-blog-list',
        path: 'index',
        meta: { privilege: 'blog.viewer' }
    },
    detail: {
        component: 'sw-blog-detail',
        path: 'detail/:id?',
        children: {
            base: { component: 'sw-blog-detail-base', path: 'base' },
            specifications: { component: 'sw-blog-detail-specifications', path: 'specifications' },
            // ... more child routes
        }
    }
}
```

```javascript
// Navigation structure with hierarchy
navigation: [
    {
        id: 'sw-catalogue',
        label: 'global.sw-admin-menu.navigation.mainMenuItemProducts',
        color: '#57D9A3',
        icon: 'regular-blogs',
        position: 20
    },
    {
        id: 'sw-blog',
        label: 'sw-blog.general.mainMenuItemGeneral',
        path: 'sw.blog.index',
        parent: 'sw-catalogue',
        privilege: 'blog.viewer',
        position: 10
    }
]
```

### 2. Component and View Registration

Modules register their Vue components globally during initialization:

```javascript
// Component registration pattern
Contena.Component.register('sw-blog-list', () => import('./page/sw-blog-list'));
Contena.Component.register('sw-blog-detail', () => import('./page/sw-blog-detail'));
Contena.Component.register('sw-blog-basic-form', () => import('./component/sw-blog-basic-form'));

// Component extension pattern
Contena.Component.extend('sw-blog-visibility-select', 'sw-entity-multi-select', 
    () => import('./component/sw-blog-visibility-select')
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
Contena.Service().register('blogService', () => {
    return {
        getProducts: () => { /* API calls */ },
        saveProduct: (blog) => { /* Save logic */ },
        validateProduct: (blog) => { /* Validation */ }
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
    key: 'blog',
    roles: {
        viewer: {
            privileges: [
                'blog:read',
                'blog_media:read',
                'category:read',
                // ... more read privileges
            ]
        },
        editor: {
            privileges: [
                'blog:update',
                'blog_media:create',
                'blog_media:update',
                // ... more edit privileges
            ]
        },
        creator: {
            privileges: [
                'blog:create',
                // ... creation privileges
            ]
        },
        deleter: {
            privileges: [
                'blog:delete',
                // ... deletion privileges
            ]
        }
    }
});
```

### 6. Translation Snippets

> **Deprecated:** The `snippets` property of the module manifest is deprecated and will be removed in v6.8.0.
> Snippets are loaded from the `snippet` folders by the initial snippets request, so simply place
> `en.json` / `de.json` files in the module's `snippet` folder instead of registering them in the manifest.

Modules provided localized strings for their interface via the manifest:

```javascript
// Snippet registration (typically in separate files)
snippets: {
    'de-DE': {
        'sw-blog': {
            'general': {
                'mainMenuItemGeneral': 'Produkte',
                'descriptionTextModule': 'Verwaltet Produkte'
            }
        }
    },
    'en-GB': {
        'sw-blog': {
            'general': {
                'mainMenuItemGeneral': 'Blogs',
                'descriptionTextModule': 'Manage blogs'
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
    blogNumber: {
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
}
```

### Route Guards

Routes can have navigation guards for access control:

```javascript
routes: {
    detail: {
        component: 'sw-blog-detail',
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
            privilege: 'blog.viewer'
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
const userService = Contena.Service('userService');
const user = await userService.getCustomer(userId);

// Repository pattern for data access
const blogRepository = Contena.Service('repositoryFactory').create('blog');
const blogs = await blogRepository.search(criteria, context);

// Event-based communication
Contena.Application.getApplicationRoot().$emit('blog-updated', blog);
Contena.Application.getApplicationRoot().$on('user-changed', this.handleCustomerChange);
```

### Service Dependencies

Modules depend on core services but avoid direct module-to-module dependencies:

```javascript
// Good: Using core services
const httpClient = Contena.Service('httpClient');
const stateManager = Contena.Service('stateManager');

// Avoid: Direct module imports
// import BlogModule from '../sw-blog';  // ❌ Tight coupling
```

## Naming Conventions

### Module Naming

- **Core modules**: `sw-[domain]` (e.g., `sw-blog`, `sw-user`)
- **Plugin modules**: `[vendor]-[plugin]-[domain]` (e.g., `swag-paypal-payment`)
- **Settings modules**: `sw-settings-[area]` (e.g., `sw-settings-tax`)

### Component Naming

- **Pages**: `sw-[module]-[type]` (e.g., `sw-blog-list`, `sw-blog-detail`)
- **Views**: `sw-[module]-detail-[section]` (e.g., `sw-blog-detail-base`)
- **Components**: `sw-[module]-[function]-[type]` (e.g., `sw-blog-price-form`)

### Route Naming

- **Pattern**: `sw.[module].[action].[sub-action]`
- **Examples**: `sw.blog.index`, `sw.blog.detail`, `sw.blog.detail.base`

## Standard Module Structure

```
sw-[module-name]/
├── acl/
│   └── index.js                    # ACL privilege definitions
├── component/                      # Reusable UI components
│   ├── sw-[module]-[component]/
│   │   ├── index.js
│   │   ├── sw-[module]-[component].html.twig
│   │   ├── sw-[module]-[component].scss
│   │   └── sw-[module]-[component].spec.js
│   └── ...
├── page/                          # Main page components
│   ├── sw-[module]-list/
│   ├── sw-[module]-detail/
│   └── ...
├── view/                          # Tab/section components
│   ├── sw-[module]-detail-base/
│   ├── sw-[module]-detail-[section]/
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
│   ├── de-DE.json
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
Contena.Component.override('sw-settings-payment-detail', () => import('./extension/sw-settings-payment-detail'));
Contena.Component.override('sw-sales-channel-modal-detail', () => import('./extension/sw-sales-channel-modal-detail'));
Contena.Component.override('sw-first-run-wizard-paypal-credentials', () => import('./extension/sw-first-run-wizard-paypal-credentials'));
```

**2. Creating New Components**
```javascript
// Register new plugin-specific components
Contena.Component.register('swag-paypal-overview-card', () => import('./components/swag-paypal-overview-card'));
Contena.Component.register('swag-paypal-settings-icon', () => import('./components/swag-paypal-settings-icon'));
```

**3. Creating New Modules**
```javascript
// Create entirely new modules for plugin functionality
Contena.Module.register('swag-paypal-disputes', {
    type: 'plugin',
    name: 'paypal-disputes',
    title: 'swag-paypal.disputes.title',
    description: 'swag-paypal.disputes.description',
    color: '#009cde',
    icon: 'regular-fingerprint',
    
    routes: {
        index: {
            component: 'swag-paypal-disputes-list',
            path: 'index'
        }
    },
    
    navigation: [{
        id: 'swag-paypal-disputes',
        label: 'swag-paypal.disputes.title',
        parent: 'sw-order',
        position: 100
    }]
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
        }
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
        }
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
        }
    };
});
```
