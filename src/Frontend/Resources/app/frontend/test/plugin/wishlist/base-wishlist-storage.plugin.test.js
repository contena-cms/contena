/* eslint-disable */
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';

/**
 * @package checkout
 */
describe('BaseWishlistStoragePlugin tests', () => {
    let wishlistStoragePlugin = undefined;
    let spyInitializePlugins = jest.fn();

    beforeEach(() => {
        // create mocks
        window.wishlistEnabled = true;

        const mockElement = document.createElement('div');

        wishlistStoragePlugin = new BaseWishlistStoragePlugin(mockElement);
    });

    afterEach(() => {
        wishlistStoragePlugin = undefined;
        spyInitializePlugins.mockClear();
    });

    test('BaseWishlistStoragePlugin exists', () => {
        expect(typeof wishlistStoragePlugin).toBe('object');
    });

    test('BaseWishlistStoragePlugin methods test', () => {
        const blogs = {
            'BLOG_1': 'blog 1',
            'BLOG_2': 'blog 2',
            'BLOG_3': 'blog 3',
        };

        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(0);
        wishlistStoragePlugin.blogs = blogs;

        let loadedEventFired = false;
        let addedProductEventFired = false;
        let removedProductEventFired = false;

        wishlistStoragePlugin.$emitter.subscribe('Wishlist/onProductsLoaded', e => {
            expect(e.detail.blogs).toEqual(blogs);
            loadedEventFired = true;
        });

        wishlistStoragePlugin.$emitter.subscribe('Wishlist/onProductRemoved', e => {
            expect(e.detail.blogs).toEqual(blogs);
            expect(e.detail.blogId).toEqual('BLOG_NEW');
            removedProductEventFired = true;
        });

        wishlistStoragePlugin.$emitter.subscribe('Wishlist/onProductAdded', e => {
            expect(e.detail.blogs).toEqual(blogs);
            expect(e.detail.blogId).toEqual('BLOG_NEW');
            addedProductEventFired = true;
        });

        wishlistStoragePlugin.load();

        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(3);

        expect(wishlistStoragePlugin.has('BLOG_1')).toEqual(true);
        expect(wishlistStoragePlugin.has('BLOG_NEW')).toEqual(false);

        wishlistStoragePlugin.add('BLOG_NEW');
        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(4);
        expect(wishlistStoragePlugin.has('BLOG_NEW')).toEqual(true);

        wishlistStoragePlugin.remove('BLOG_NEW');
        expect(wishlistStoragePlugin.has('BLOG_NEW')).toEqual(false);
        expect(wishlistStoragePlugin.getCurrentCounter()).toEqual(3);

        expect(loadedEventFired).toEqual(true);
        expect(removedProductEventFired).toEqual(true);
        expect(addedProductEventFired).toEqual(true);

        expect(wishlistStoragePlugin.getProducts()).toEqual(blogs)
    });
});


