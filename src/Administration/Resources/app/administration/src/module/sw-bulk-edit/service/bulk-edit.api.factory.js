import BulkEditProductHandler from './handler/bulk-edit-blog.handler';
import BulkEditOrderHandler from './handler/bulk-edit-order.handler';
import BulkEditCustomerHandler from './handler/bulk-edit-user.handler';

/**
 * @class
 *
 * @sw-package framework
 */
class BulkEditApiFactory {
    constructor() {
        this.handlers = {
            blog: () => new BulkEditProductHandler(),
            order: () => new BulkEditOrderHandler(),
            user: () => new BulkEditCustomerHandler(),
        };
    }

    getHandler(module) {
        if (!this.handlers[module]) {
            throw Error(`Bulk Edit Handler not found for ${module} module`);
        }

        // Lazy load the module handler
        return this.handlers[module]();
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BulkEditApiFactory;
