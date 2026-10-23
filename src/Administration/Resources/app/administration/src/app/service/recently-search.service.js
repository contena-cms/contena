/**
 * @module app/recently-search-service
 * @sw-package inventory
 */

/**
 * A service for RecentlySearch feature
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class RecentlySearchService {
    /**
     * Get admin_user's stack of recently searched items
     *
     * @param admin_userId
     * @return Array
     */
    get(admin_userId) {
        const items = localStorage.getItem(this._key(admin_userId));

        if (!items) {
            return [];
        }

        return JSON.parse(items);
    }

    /**
     * Add a recently seach entities into the localStorage queue
     *
     * @param admin_userId
     * @param entity
     * @param id
     * @param payload
     * @return void
     */
    add(admin_userId, entity, id, payload = {}) {
        let stack = this.get(admin_userId);

        // Remove already existed entity in stack
        stack = stack.filter((item) => {
            return item.entity !== entity || item.id !== id;
        });

        const newItem = {
            entity,
            id,
            payload,
            timestamp: Date.now(),
        };

        stack.unshift(newItem);

        // If stack size exceeds the maximum size, pop the last item out
        if (stack.length > this._maxStackSize()) {
            stack.pop();
        }

        localStorage.setItem(this._key(admin_userId), JSON.stringify(stack));
    }

    /**
     * @param admin_userId
     * @return {string}
     * @private
     */
    _key(admin_userId) {
        return `recently-search.${admin_userId}`;
    }

    /**
     * @return {number}
     * @private
     */
    _maxStackSize() {
        return 5;
    }
}
