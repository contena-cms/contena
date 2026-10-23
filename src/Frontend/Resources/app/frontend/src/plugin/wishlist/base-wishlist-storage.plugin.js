import Plugin from 'src/plugin-system/plugin.class';

/**
 * @package checkout
 */
export default class BaseWishlistStoragePlugin extends Plugin {
    init() {
        this.blogs = {};
    }

    load() {
        this.$emitter.publish('Wishlist/onProductsLoaded', {
            blogs: this.blogs,
        });
    }

    has(blogId) {
        return !!this.blogs[blogId];
    }

    add(blogId) {
        this.blogs[blogId] = (new Date()).toISOString();

        this.$emitter.publish('Wishlist/onProductAdded', {
            blogs: this.blogs,
            blogId,
        });
    }

    remove(blogId) {
        delete this.blogs[blogId];

        this.$emitter.publish('Wishlist/onProductRemoved', {
            blogs: this.blogs,
            blogId,
        });
    }

    getCurrentCounter() {
        return this.blogs ? Object.keys(this.blogs).length : 0;
    }

    getProducts() {
        return this.blogs;
    }
}
