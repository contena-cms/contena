import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import Storage from 'src/helper/storage/storage.helper';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';

/**
 * @package checkout
 */
export default class WishlistPersistStoragePlugin extends BaseWishlistStoragePlugin {
    init() {
        super.init();
        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this.httpClient = new HttpClient();
        this.httpClient.setErrorHandlingInternal(true);
    }

    load() {
        this._merge(() => this._loadProducts())
            .catch(error => this._handleRequestError(error));
    }

    add(blogId, router) {
        this._fetchJson(router.path, {
            method: 'POST',
        })
            .then(response => {
                if (response === null) {
                    return;
                }

                if (response.success) {
                    super.add(blogId);

                    return;
                }

                console.warn('unable to add blog to wishlist');
            })
            .catch(error => this._handleRequestError(error));
    }

    remove(blogId, router) {
        this._fetchJson(router.path, {
            method: 'POST',
        })
            .then(response => {
                if (response === null) {
                    return;
                }

                if (Object.prototype.hasOwnProperty.call(response, 'success')) {
                    if (response.success === false) {
                        console.warn('unable to remove blog to wishlist');
                    }
                    super.remove(blogId);
                }
            })
            .catch(error => this._handleRequestError(error));
    }

    /**
     * @private
     */
    async _merge(callback) {
        this.storage = Storage;
        const key = 'wishlist-' + (window.channelId || '');

        const blogStr = this.storage.getItem(key);

        const blogs = JSON.parse(blogStr);

        let mergePromise = Promise.resolve();

        if (blogs) {
            mergePromise = this._mergeProducts(blogs, key, callback);
        }

        await callback();
        await mergePromise;
    }

    /**
     * @private
     */
    async _pagelet() {
        try {
            const response = await this._fetchText(this.options.pageletPath, {
                method: 'POST',
            });

            if (response === null || response === '') {
                return;
            }

            this._block = document.querySelector('.cms-listing-row');
            this._block.innerHTML = response;
        } catch (error) {
            this._handleRequestError(error);
        }
    }

    /**
     * @private
     */
    async _loadProducts() {
        try {
            const response = await this._fetchJson(this.options.listPath);

            if (response === null) {
                return;
            }

            this.blogs = response;

            super.load();
        } catch (error) {
            this._handleRequestError(error);
        }
    }

    /**
     * @private
     */
    async _mergeProducts(blogs, key, callback) {
        try {
            const response = await this._fetchText(this.options.mergePath, {
                method: 'POST',
                body: JSON.stringify({ blogIds: Object.keys(blogs) }),
            });

            if (response === null) {
                return;
            }

            if (response === '') {
                throw new Error('Unable to merge blog wishlist from anonymous admin_user');
            }

            this.$emitter.publish('Wishlist/onProductMerged', {
                blogs: blogs,
            });

            this.storage.removeItem(key);
            this._block = document.querySelector('.flashbags');
            this._block.innerHTML = response;
            await this._pagelet();
            await callback();
        } catch (error) {
            this._handleRequestError(error);
        }
    }

    /**
     * @private
     */
    async _fetchJson(path, options = {}) {
        const response = await fetch(path, this._createRequestOptions(options));

        if (!response.ok) {
            return null;
        }

        return response.json();
    }

    /**
     * @private
     */
    async _fetchText(path, options = {}) {
        const response = await fetch(path, this._createRequestOptions(options));

        if (!response.ok) {
            return null;
        }

        return response.text();
    }

    /**
     * @private
     */
    _createRequestOptions(options = {}) {
        return {
            ...options,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                ...options.headers,
            },
        };
    }

    /**
     * @private
     */
    _handleRequestError(error) {
        console.warn(error);
    }
}
