# Repository Pattern

The Repository Pattern in Contena 6 Administration provides a consistent, type-safe interface for entity CRUD operations and complex queries. It abstracts the underlying HTTP API calls and provides automatic data hydration, change tracking, and error handling.

## Repository Factory

The `RepositoryFactory` is the central entry point for creating repository instances:

```typescript
// Located in: core/data/repository-factory.data.ts
const repositoryFactory = Contena.Service('repositoryFactory');
const blogRepository = repositoryFactory.create('blog');
```

### Factory Dependencies

The repository factory injects several key services into each repository:

- **EntityHydrator**: Converts raw API responses to reactive entity objects
- **ChangesetGenerator**: Computes minimal change sets for save operations
- **EntityFactory**: Creates new entity instances with proper defaults
- **HttpClient**: Axios instance for API communication
- **ErrorResolver**: Maps API errors to field-specific validation messages

### Automatic Route Resolution

Entity names are automatically converted to API routes:
- `blog` → `/blog`
- `blog_category` → `/blog-category`

## Repository Interface

Each repository provides a consistent set of methods for data operations:

### Search Operations

```typescript
// Basic search with criteria
const criteria = new Criteria(1, 25);
criteria.addFilter(Criteria.equals('active', true));
criteria.addSorting(Criteria.sort('name', 'ASC'));

const searchResult = await blogRepository.search(criteria, context);
```

**Key Methods:**
- `search(criteria, context)`: Returns EntityCollection with entities and metadata
- `searchIds(criteria, context)`: Returns only entity IDs for efficient lookups
- `get(id, context, criteria)`: Fetch single entity by ID

### CRUD Operations

```typescript
// Create new entity
const newProduct = blogRepository.create(context);
newProduct.name = 'New Blog';
newProduct.active = true;

// Save entity (create or update)
await blogRepository.save(newProduct, context);

// Delete entity
await blogRepository.delete(blogId, context);

// Clone entity
const clonedProduct = await blogRepository.clone(blogId, context, criteria);
```

### Bulk Operations

```typescript
// Save multiple entities in a single request
await blogRepository.saveAll([blog1, blog2, blog3], context);

// Delete multiple entities
await blogRepository.deleteAll([id1, id2, id3], context);

// Sync operation for complex bulk updates
await blogRepository.sync([
    { action: 'upsert', entity: 'blog', payload: [blog1, blog2] },
    { action: 'delete', entity: 'blog', payload: [{ id: 'id-to-delete' }] }
], context);
```

## Entity Lifecycle Management

### Change Tracking

Repositories automatically track entity changes through the draft/origin pattern:

```typescript
const blog = await blogRepository.get(blogId, context);

// Original state preserved in entity.getOrigin()
console.log(blog.getOrigin().name); // "Original Name"

// Modifications tracked in entity.getDraft()
blog.name = "Modified Name";
console.log(blog.getDraft().name); // "Modified Name"

// Check if entity has been modified using repository
console.log(blogRepository.hasChanges(blog)); // true
```

### Changeset Generation

The `ChangesetGenerator` computes minimal payloads for API requests:

```typescript
// Only sends modified fields to the API
blog.name = "New Name";          // Will be included
blog.description = "New Desc";   // Will be included
// blog.price unchanged          // Will NOT be included

await blogRepository.save(blog, context);
```

### Association Handling

The repository pattern handles complex entity relationships:

```typescript
const criteria = new Criteria();
criteria.addAssociation('categories');
criteria.addAssociation('manufacturer');

const blog = await blogRepository.get(blogId, context, criteria);

// Access loaded associations
console.log(blog.categories);    // EntityCollection<Category>
console.log(blog.manufacturer);  // Entity<Manufacturer>
```

**Association Types Supported:**
- **OneToMany**: Collections of related entities (blog.media)
- **ManyToOne**: Single related entity (blog.manufacturer)
- **ManyToMany**: Collections with join table (blog.categories)
- **OneToOne**: Single related entity with foreign key

## TypeScript Integration

Repositories provide full TypeScript support through generic typing. In most cases, the types are inferred automatically:

```typescript
// Automatic type inference
const repositoryFactory = Contena.Service('repositoryFactory');
const blogRepository = repositoryFactory.create('blog');

// Explicit typing (optional)
const blogRepository: Repository<'blog'> = repositoryFactory.create('blog');

// Type-safe entity access
const blog: Entity<'blog'> = await blogRepository.get(id, context);

// TypeScript will enforce entity schema
blog.name = "string";     // ✅ Valid
blog.name = 123;          // ❌ Type error
blog.nonExistentField;    // ❌ Type error
```

## Context and Authentication

All repository operations require a context object that provides:

```typescript
const context = Contena.Context.api;  // Default API context

// Context includes:
// - Authentication headers
// - Language/locale settings
// - API version information
// - Inheritance configuration
```

But you don't need to provide it manually in most cases, as the default context is sufficient for standard operations and is automatically used by the repositories.

## Error Handling

Repositories integrate with the error handling system:

```typescript
try {
    await blogRepository.save(blog, context);
} catch (error) {
    // Errors automatically mapped to entity fields
    if (error.response?.data?.errors) {
        // Field-specific errors available in error store
        const fieldErrors = Contena.Store.get('error').getApiErrorsForEntity(blog);
    }
}
```

### Component Error Mapping

Contena provides a convenient helper for mapping entity errors directly to component computed properties:

```javascript
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

export default {
    // ...existing code...
    
    computed: {
        // ...existing code...
        
        // Maps errors for the 'manufacturer' entity and 'name' field
        // Creates a computed property 'manufacturerNameError'
        ...mapPropertyErrors('manufacturer', ['name']),
        
        // For multiple fields:
        ...mapPropertyErrors('blog', ['name', 'price', 'stock']),
        // Creates: blogNameError, blogPriceError, blogStockError
    }
};
```

**Template Usage:**
```html
<sw-text-field
    v-model="manufacturer.name"
    :label="$t('sw-manufacturer.detail.labelName')"
    name="name"
    validation="required"
    required
    :error="manufacturerNameError"
    :disabled="!acl.can('blog_manufacturer.editor')"
/>
```

The `mapPropertyErrors` helper automatically:
- Listens to the error store for the specified entity and fields
- Returns the first error message for each field
- Updates reactively when errors change or are cleared
- Provides null when no errors exist for the field

## Performance Best Practices

### Repository Instance Reuse
```typescript
// ✅ Good: Reuse repository within component scope
export default {
    inject: ['repositoryFactory'],
    
    created() {
        this.blogRepository = this.repositoryFactory.create('blog');
    },
    
    methods: {
        async loadProducts() {
            return this.blogRepository.search(criteria, context);
        }
    }
};
```

### Pagination Strategy
```typescript
// ✅ Good: Use appropriate page sizes
const criteria = new Criteria(1, 25);  // Reasonable page size

// ❌ Avoid: Large page sizes
const criteria = new Criteria(1, 1000);  // May impact performance

// ✅ Good: Disable total count when not needed
criteria.setTotalCountMode(0);  // Improves performance
```

### Change Detection Optimization
```typescript
// ✅ Good: Batch multiple changes before save
blog.name = "New Name";
blog.description = "New Description";
blog.active = false;
await blogRepository.save(blog, context);  // Single API call

// ❌ Avoid: Multiple individual saves
await blogRepository.save(blog, context);  // Save after each change
blog.description = "New Description";
await blogRepository.save(blog, context);  // Unnecessary API calls
```

### Post-Save Data Refresh
```typescript
// ✅ Good: Reload entity after save to sync origin state
async saveProduct(blog) {
    try {
        await this.blogRepository.save(blog, this.context);
        
        // Reload to ensure proper change tracking
        this.blog = await this.blogRepository.get(blog.id, this.context, this.criteria);
        
        // Now change detection works correctly
        // If admin_user changes a value back to what it was before the save,
        // the system will correctly detect it as "no change"
        
    } catch (error) {
        // Handle errors
    }
}

// ❌ Avoid: Not reloading after save
async saveProductBad(blog) {
    await this.blogRepository.save(blog, this.context);
    
    // Problem: draft state is still the modified version
    // origin state is outdated (pre-save state)
    // Changing values back to pre-save state won't be detected as "no change"
    
    console.log(this.blogRepository.hasChanges(blog)); // May incorrectly return true
}
```

**Why reload after save?**
- **Origin State Update**: The entity's origin needs to reflect the current server state
- **Change Tracking Accuracy**: Ensures future modifications are correctly detected
- **Server-Side Changes**: Captures any server-side modifications (timestamps, calculated fields, etc.)
- **Consistent State**: Prevents drift between client and server state

## Extension and Customization

### Repository Decoration
```typescript
// Extend repository behavior through service decorators
Contena.Service().registerDecorator('repositoryFactory', (service) => {
    const originalCreate = service.create;
    
    service.create = function(entityName, route, options) {
        const repository = originalCreate.call(this, entityName, route, options);
        
        // Add custom methods or override behavior
        repository.customMethod = function() {
            // Custom logic here
        };
        
        return repository;
    };
    
    return service;
});
```

### Custom Repository Methods
```typescript
// Add domain-specific methods to repositories
class BlogRepository extends Repository {
    async findActiveProducts(criteria, context) {
        criteria.addFilter(Criteria.equals('active', true));
        return this.search(criteria, context);
    }
    
    async bulkUpdatePrices(priceUpdates, context) {
        const operations = priceUpdates.map(update => ({
            action: 'upsert',
            entity: 'blog',
            payload: [{ id: update.id, price: update.price }]
        }));
        
        return this.sync(operations, context);
    }
}
```
