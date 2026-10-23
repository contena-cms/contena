# Entity System

The Entity System in Contena 6 Administration provides reactive data objects with automatic change tracking, validation, and seamless integration with Vue.js. It forms the foundation for all data operations and state management within the administration interface.

## Entity Architecture

### Entity Class Structure

Entities in Contena 6 are imported from the Meteor Admin SDK and enhanced with Vue.js reactivity:

```typescript
// Located in: core/data/entity.data.ts
import Entity, { assignSetterMethod } from '@contena-ag/meteor-admin-sdk/es/_internals/data/Entity';

assignSetterMethod((draft, property, value) => {
    // Integration with Vue.js reactivity system
    Contena.Application.view.setReactive(draft as Vue, property, value);
});
```

### Entity Creation

Entities are created through repositories and automatically configured with proper defaults:

```typescript
// Create new entity
const blogRepository = this.repositoryFactory.create('blog');
const newProduct = blogRepository.create(context);

// Entity is automatically reactive and tracked
newProduct.name = 'New Blog';
newProduct.active = true;
newProduct.stock = 100;
```

## Change Tracking System

### Draft/Origin Pattern

Every entity maintains two states for sophisticated change detection:

```typescript
const blog = await blogRepository.get(blogId, context);

// Access original state (immutable)
const originalName = blog.getOrigin().name;
const originalPrice = blog.getOrigin().price;

// Access current draft state (mutable) - direct property access
const currentName = blog.name;
const currentPrice = blog.price;

// Make changes to draft
blog.name = 'Updated Blog Name';
blog.price = 29.99;

// Check modification status
console.log(blog.isNew());      // false (existing entity)
```

### Change Detection Methods

```typescript
// Check if entity is new (not yet persisted)
if (blog.isNew()) {
    console.log('This is a new entity');
}

// Check if entity has changes using repository method
const blogRepository = this.repositoryFactory.create('blog');
if (blogRepository.hasChanges(blog)) {
    console.log('Entity has unsaved changes');
    await blogRepository.save(blog, context);
}

// The hasChanges method detects:
// - Field-level changes (comparing draft vs origin)
// - Association changes (added/removed/modified related entities)
// - Entities in the deletion queue

// Common usage pattern in components
export default {
    computed: {
        hasUnsavedChanges() {
            return this.blog && this.blogRepository.hasChanges(this.blog);
        }
    },
    
    methods: {
        async onSave() {
            if (this.blogRepository.hasChanges(this.blog)) {
                await this.blogRepository.save(this.blog, this.context);
            }
        },
        
        canLeave() {
            // Used in route guards and navigation warnings
            return !this.blogRepository.hasChanges(this.blog);
        }
    }
};
```

### Field-Level Change Tracking

The changeset generator handles field-level change detection internally when saving entities:

```typescript
// The system automatically detects changes by comparing draft vs origin
// This happens during repository.save() operations

// You can manually check if values have changed
const hasNameChanged = blog.name !== blog.getOrigin().name;
const hasPriceChanged = blog.price !== blog.getOrigin().price;

// Get original value of a field
const originalValue = blog.getOrigin().price;

// Get current value (direct property access)
const currentValue = blog.price;
```

## Entity Properties and Metadata

### Basic Properties

```typescript
// Entity identification
console.log(blog.id);                    // Entity ID
console.log(blog.getEntityName());       // 'blog'
console.log(blog.getUniqueIdentifier()); // Composite primary key

// Timestamps (if available)
console.log(blog.createdAt);
console.log(blog.updatedAt);

// Entity state
console.log(blog.isNew());
// Note: Use repository.hasChanges(entity) to check for modifications
// console.log(blog.isDeleted()); // May not be available on all entity types
```

### Entity Extensions

Contena 6 supports extending entities with custom fields:

```typescript
// Access custom fields through extensions
if (blog.extensions && blog.extensions.customFields) {
    console.log(blog.extensions.customFields.myCustomField);
}

// Set custom field values
if (!blog.extensions) {
    blog.extensions = {};
}
if (!blog.extensions.customFields) {
    blog.extensions.customFields = {};
}
blog.extensions.customFields.myCustomField = 'Custom Value';
```

## Entity Collections

### EntityCollection Structure

EntityCollection provides array-like functionality with additional entity-specific methods:

```typescript
// Located in: core/data/entity-collection.data.ts
import EntityCollection from '@contena-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';

const blogs = await blogRepository.search(criteria, context);
console.log(blogs instanceof EntityCollection); // true
```

### Collection Methods

```typescript
// Basic collection operations
console.log(blogs.length);           // Number of entities
console.log(blogs.total);            // Total available (from search)
console.log(blogs.first());          // First entity or null
console.log(blogs.last());           // Last entity or null

// Get entity by ID
const blog = blogs.get('blog-id-123');

// Check if entity exists
if (blogs.has('blog-id-123')) {
    console.log('Blog exists in collection');
}

// Iterate over entities
blogs.forEach(blog => {
    console.log(blog.name);
});

// Filter collection
const activeProducts = blogs.filter(blog => blog.active);

// Map collection
const blogNames = blogs.map(blog => blog.name);

// Find entity
const premiumProduct = blogs.find(blog => 
    blog.name.includes('Premium')
);
```

### Collection Manipulation

```typescript
// Add entity to collection
const newProduct = blogRepository.create(context);
blogs.add(newProduct);

// Remove entity from collection
blogs.remove('blog-id-to-remove');

// Clear collection
blogs.clear();

// Convert to plain array
const blogArray = blogs.toArray();
```

## Entity Associations

### Loading Associations

Associations are loaded through criteria and accessed as properties:

```typescript
const criteria = new Criteria();
criteria.addAssociation('manufacturer');
criteria.addAssociation('categories');

const blog = await blogRepository.get(blogId, context, criteria);

// Access single association (many-to-one)
console.log(blog.manufacturer.name);

// Access collection association (one-to-many, many-to-many)
console.log(blog.categories.length);
blog.categories.forEach(category => {
    console.log(category.name);
});
```

### Association Types

```typescript
// Many-to-One (single entity)
const manufacturer = blog.manufacturer;
if (manufacturer) {
    console.log('Manufacturer:', manufacturer.name);
}

// One-to-Many (collection)
const media = blog.media;
media.forEach(mediaItem => {
    console.log('Media URL:', mediaItem.url);
});

// Many-to-Many (collection with join table)
const categories = blog.categories;
categories.forEach(category => {
    console.log('Category:', category.name);
});

// One-to-One (single entity with foreign key)
const defaultPrice = blog.price;
if (defaultPrice) {
    console.log('Price:', defaultPrice.gross);
}
```

### Modifying Associations

```typescript
// Set many-to-one association
blog.manufacturerId = 'new-manufacturer-id';
// The manufacturer object will be updated after save/reload

// Add to many-to-many collection
const newCategory = categoryRepository.create(context);
newCategory.name = 'New Category';
blog.categories.add(newCategory);

// Remove from collection
blog.categories.remove('category-id-to-remove');

// Replace entire collection
const newCategories = new EntityCollection('/category', 'category', context);
newCategories.add(category1);
newCategories.add(category2);
blog.categories = newCategories;
```

## Entity Validation

### Built-in Validation

Entities automatically validate based on entity definitions:

```typescript
// Required field validation
blog.name = null; // Will trigger validation error on save

// Type validation (TypeScript compile-time)
blog.active = 'invalid'; // TypeScript error
blog.active = true;       // Correct

// Length validation (if defined in entity schema)
blog.name = 'A'.repeat(300); // May trigger length validation
```

### Custom Validation

```typescript
// Add custom validation before save
function validateProduct(blog) {
    const errors = [];
    
    if (!blog.name || blog.name.trim().length === 0) {
        errors.push('Blog name is required');
    }
    
    if (blog.price && blog.price < 0) {
        errors.push('Price cannot be negative');
    }
    
    if (errors.length > 0) {
        throw new Error('Validation failed: ' + errors.join(', '));
    }
}

// Use before save
try {
    validateProduct(blog);
    await blogRepository.save(blog, context);
} catch (error) {
    console.error('Validation error:', error.message);
}
```

## Entity Lifecycle Hooks

### Vue.js Integration

Entities integrate with Vue.js lifecycle and reactivity:

```typescript
export default {
    data() {
        return {
            blog: null
        };
    },
    
    computed: {
        // Computed properties update when entity changes
        blogDisplayName() {
            return this.blog ? 
                `${this.blog.name} (${this.blog.blogNumber})` : 
                '';
        },
        
        hasUnsavedChanges() {
            return this.blog && this.blogRepository.hasChanges(this.blog);
        }
    },
    
    watch: {
        // Watch for entity changes
        'blog.name': {
            handler(newName, oldName) {
                console.log('Blog name changed:', oldName, '->', newName);
            }
        },
        
        hasUnsavedChanges: {
            handler(hasChanges) {
                if (hasChanges) {
                    this.showUnsavedChangesWarning = true;
                }
            }
        }
    }
};
```

### Entity Events

```typescript
// Listen for entity changes (custom implementation)
class EntityEventListener {
    constructor() {
        this.listeners = new Map();
    }
    
    on(entityName, event, callback) {
        const key = `${entityName}.${event}`;
        if (!this.listeners.has(key)) {
            this.listeners.set(key, []);
        }
        this.listeners.get(key).push(callback);
    }
    
    emit(entityName, event, entity, data) {
        const key = `${entityName}.${event}`;
        const callbacks = this.listeners.get(key) || [];
        callbacks.forEach(callback => callback(entity, data));
    }
}

// Usage
const eventListener = new EntityEventListener();

eventListener.on('blog', 'save', (blog, result) => {
    console.log('Blog saved:', blog.name);
});

eventListener.on('blog', 'delete', (blog) => {
    console.log('Blog deleted:', blog.name);
});
```

## Error Handling

### Entity-Level Errors

```typescript
try {
    await blogRepository.save(blog, context);
} catch (error) {
    if (error.response && error.response.data && error.response.data.errors) {
        const entityErrors = error.response.data.errors;
        
        entityErrors.forEach(err => {
            if (err.source && err.source.pointer) {
                const field = err.source.pointer.split('/').pop();
                console.log(`Error in field ${field}: ${err.detail}`);
            }
        });
    }
}
```

### Field-Specific Error Handling

```typescript
// Access field-specific errors from error store
const errorStore = Contena.Store.get('error');
const fieldErrors = errorStore.getApiErrorsForEntity(blog);

// Display errors in UI
Object.keys(fieldErrors).forEach(fieldName => {
    const errors = fieldErrors[fieldName];
    errors.forEach(error => {
        console.log(`${fieldName}: ${error.detail}`);
    });
});
```

## Performance Optimization

### Efficient Entity Usage

```typescript
// ✅ Good: Batch changes before save
blog.name = 'New Name';
blog.description = 'New Description';
blog.active = true;
await blogRepository.save(blog, context); // Single API call

// ❌ Avoid: Multiple saves for related changes
blog.name = 'New Name';
await blogRepository.save(blog, context);
blog.description = 'New Description';
await blogRepository.save(blog, context); // Unnecessary additional call
```

### Memory Management

```typescript
// ✅ Good: Clear references when done
export default {
    beforeDestroy() {
        // Clear entity references to prevent memory leaks
        this.blog = null;
        this.blogs = null;
    }
};

// ✅ Good: Use weak references for temporary entities
const weakEntityMap = new WeakMap();
weakEntityMap.set(component, entity); // Automatically cleaned up
```

### Association Loading Strategy

```typescript
// ✅ Good: Load associations in single request
const criteria = new Criteria();
criteria.addAssociation('manufacturer');
criteria.addAssociation('categories');
const blog = await blogRepository.get(id, context, criteria);

// ❌ Avoid: Loading associations separately
const blog = await blogRepository.get(id, context);
const manufacturer = await manufacturerRepository.get(blog.manufacturerId, context);
const categories = await categoryRepository.search(categoryCriteria, context);
```

## Best Practices

### Entity State Management

1. **Always check entity state before operations**
2. **Use change tracking to optimize saves**
3. **Reset entities when discarding changes**
4. **Validate entities before persistence**

### Vue.js Integration

1. **Use computed properties for derived data**
2. **Watch entity changes for side effects**
3. **Clean up entity references in component lifecycle**
4. **Leverage Vue.js reactivity for UI updates**

### Error Handling

1. **Always handle save/load errors**
2. **Display field-specific validation errors**
3. **Provide admin_user feedback for entity operations**
4. **Log errors for debugging purposes**

### Performance

1. **Batch entity operations when possible**
2. **Load associations efficiently**
3. **Use appropriate collection methods**
4. **Avoid unnecessary entity cloning**
