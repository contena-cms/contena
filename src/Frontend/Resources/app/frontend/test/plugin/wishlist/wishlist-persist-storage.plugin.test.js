import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import WishlistPersistStoragePlugin from 'src/plugin/wishlist/persist-wishlist.plugin';
import Storage from 'src/helper/storage/storage.helper';

/**
 * @package checkout
 */
describe('WishlistPersistStoragePlugin tests', () => {
    const flushPromises = () => new Promise(process.nextTick);
    const defaultHeaders = {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
    };

    let originalFetch = undefined;
    let wishlistStoragePlugin = undefined;
    let storageKey = undefined;

    function createJsonResponse(data, ok = true) {
        return {
            ok,
            json: jest.fn(() => Promise.resolve(data)),
        };
    }

    function createTextResponse(data, ok = true) {
        return {
            ok,
            text: jest.fn(() => Promise.resolve(data)),
        };
    }

    beforeEach(() => {
        originalFetch = global.fetch;
        window.channelId = 'test-sales-channel';
        storageKey = 'wishlist-test-sales-channel';
        Storage.removeItem(storageKey);
        document.body.innerHTML = `
            <div class="flashbags"></div>
            <div class="cms-listing-row"></div>
        `;

        wishlistStoragePlugin = new WishlistPersistStoragePlugin(
            document.createElement('div'),
            {
                listPath: '/wishlist/list',
                mergePath: '/wishlist/merge',
                pageletPath: '/wishlist/pagelet',
            },
        );
    });

    afterEach(() => {
        global.fetch = originalFetch;
        Storage.removeItem(storageKey);
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        wishlistStoragePlugin = undefined;
        storageKey = undefined;
    });

    test('load does not parse unsuccessful response', async () => {
        const response = createJsonResponse({}, false);
        const loadSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'load');

        global.fetch = jest.fn(() => Promise.resolve(response));

        const result = wishlistStoragePlugin.load();

        expect(result).toBeUndefined();

        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/wishlist/list', {
            headers: defaultHeaders,
        });
        expect(response.json).not.toHaveBeenCalled();
        expect(loadSpy).not.toHaveBeenCalled();
    });

    test('load applies successful response', async () => {
        const blogs = ['blog-1'];
        const loadSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'load');

        global.fetch = jest.fn(() => Promise.resolve(createJsonResponse(blogs)));

        const result = wishlistStoragePlugin.load();

        expect(result).toBeUndefined();

        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/wishlist/list', {
            headers: defaultHeaders,
        });
        expect(wishlistStoragePlugin.blogs).toEqual(blogs);
        expect(loadSpy).toHaveBeenCalledTimes(1);
    });

    test('add persists blog locally when the request succeeds', async () => {
        const addSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'add');

        global.fetch = jest.fn(() => Promise.resolve(createJsonResponse({ success: true })));

        const result = wishlistStoragePlugin.add('blog-1', { path: '/wishlist/add/blog-1' });

        expect(result).toBeUndefined();

        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/wishlist/add/blog-1', {
            method: 'POST',
            headers: defaultHeaders,
        });
        expect(addSpy).toHaveBeenCalledWith('blog-1');
        expect(wishlistStoragePlugin.has('blog-1')).toBe(true);
    });

    test('add warns and keeps local blogs unchanged when the request fails in the response body', async () => {
        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const addSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'add');

        global.fetch = jest.fn(() => Promise.resolve(createJsonResponse({ success: false })));

        const result = wishlistStoragePlugin.add('blog-1', { path: '/wishlist/add/blog-1' });

        expect(result).toBeUndefined();

        await flushPromises();

        expect(warnSpy).toHaveBeenCalledWith('unable to add blog to wishlist');
        expect(addSpy).not.toHaveBeenCalled();
        expect(wishlistStoragePlugin.has('blog-1')).toBe(false);
    });

    test('add does not parse unsuccessful response', async () => {
        const response = createJsonResponse({}, false);
        const addSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'add');

        global.fetch = jest.fn(() => Promise.resolve(response));

        const result = wishlistStoragePlugin.add('blog-1', { path: '/wishlist/add/blog-1' });

        expect(result).toBeUndefined();

        await flushPromises();

        expect(response.json).not.toHaveBeenCalled();
        expect(addSpy).not.toHaveBeenCalled();
    });

    test('remove deletes blog locally when the response contains success', async () => {
        const removeSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'remove');
        wishlistStoragePlugin.blogs = { 'blog-1': '2026-05-06T00:00:00.000Z' };

        global.fetch = jest.fn(() => Promise.resolve(createJsonResponse({ success: true })));

        const result = wishlistStoragePlugin.remove('blog-1', { path: '/wishlist/remove/blog-1' });

        expect(result).toBeUndefined();

        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/wishlist/remove/blog-1', {
            method: 'POST',
            headers: defaultHeaders,
        });
        expect(removeSpy).toHaveBeenCalledWith('blog-1');
        expect(wishlistStoragePlugin.has('blog-1')).toBe(false);
    });

    test('remove warns but still deletes blog locally when success is false', async () => {
        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const removeSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'remove');
        wishlistStoragePlugin.blogs = { 'blog-1': '2026-05-06T00:00:00.000Z' };

        global.fetch = jest.fn(() => Promise.resolve(createJsonResponse({ success: false })));

        const result = wishlistStoragePlugin.remove('blog-1', { path: '/wishlist/remove/blog-1' });

        expect(result).toBeUndefined();

        await flushPromises();

        expect(warnSpy).toHaveBeenCalledWith('unable to remove blog to wishlist');
        expect(removeSpy).toHaveBeenCalledWith('blog-1');
        expect(wishlistStoragePlugin.has('blog-1')).toBe(false);
    });

    test('remove keeps blog locally when response has no success property', async () => {
        const removeSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'remove');
        wishlistStoragePlugin.blogs = { 'blog-1': '2026-05-06T00:00:00.000Z' };

        global.fetch = jest.fn(() => Promise.resolve(createJsonResponse({})));

        const result = wishlistStoragePlugin.remove('blog-1', { path: '/wishlist/remove/blog-1' });

        expect(result).toBeUndefined();

        await flushPromises();

        expect(removeSpy).not.toHaveBeenCalled();
        expect(wishlistStoragePlugin.has('blog-1')).toBe(true);
    });

    test('remove does not parse unsuccessful response', async () => {
        const response = createJsonResponse({}, false);
        const removeSpy = jest.spyOn(BaseWishlistStoragePlugin.prototype, 'remove');
        wishlistStoragePlugin.blogs = { 'blog-1': '2026-05-06T00:00:00.000Z' };

        global.fetch = jest.fn(() => Promise.resolve(response));

        const result = wishlistStoragePlugin.remove('blog-1', { path: '/wishlist/remove/blog-1' });

        expect(result).toBeUndefined();

        await flushPromises();

        expect(response.json).not.toHaveBeenCalled();
        expect(removeSpy).not.toHaveBeenCalled();
        expect(wishlistStoragePlugin.has('blog-1')).toBe(true);
    });

    test('merge only calls callback when anonymous wishlist storage is empty', async () => {
        const callback = jest.fn();

        global.fetch = jest.fn();

        await wishlistStoragePlugin._merge(callback);

        expect(global.fetch).not.toHaveBeenCalled();
        expect(callback).toHaveBeenCalledTimes(1);
    });

    test('merge posts anonymous blogs, clears storage, renders feedback, and reloads pagelet', async () => {
        const blogs = {
            'blog-1': '2026-05-06T00:00:00.000Z',
            'blog-2': '2026-05-06T00:00:00.000Z',
        };
        const callback = jest.fn();
        const pageletSpy = jest.spyOn(wishlistStoragePlugin, '_pagelet');
        let mergedProducts = undefined;

        wishlistStoragePlugin.$emitter.subscribe('Wishlist/onProductMerged', event => {
            mergedProducts = event.detail.blogs;
        });

        Storage.setItem(storageKey, JSON.stringify(blogs));

        global.fetch = jest.fn()
            .mockResolvedValueOnce(createTextResponse('<div class="alert">Merged</div>'))
            .mockResolvedValueOnce(createTextResponse('<div class="blog-box">Blog</div>'));

        const mergePromise = wishlistStoragePlugin._merge(callback);

        expect(callback).toHaveBeenCalledTimes(1);

        await mergePromise;

        expect(global.fetch).toHaveBeenNthCalledWith(1, '/wishlist/merge', {
            method: 'POST',
            body: JSON.stringify({ blogIds: Object.keys(blogs) }),
            headers: defaultHeaders,
        });
        expect(global.fetch).toHaveBeenNthCalledWith(2, '/wishlist/pagelet', {
            method: 'POST',
            headers: defaultHeaders,
        });
        expect(mergedProducts).toEqual(blogs);
        expect(Storage.getItem(storageKey)).toBeNull();
        expect(document.querySelector('.flashbags').innerHTML).toBe('<div class="alert">Merged</div>');
        expect(document.querySelector('.cms-listing-row').innerHTML).toBe('<div class="blog-box">Blog</div>');
        expect(pageletSpy).toHaveBeenCalledTimes(1);
        expect(callback).toHaveBeenCalledTimes(2);
    });

    test('merge keeps anonymous blogs when the merge request is unsuccessful', async () => {
        const blogs = { 'blog-1': '2026-05-06T00:00:00.000Z' };
        const callback = jest.fn();
        const pageletSpy = jest.spyOn(wishlistStoragePlugin, '_pagelet');
        const response = createTextResponse('', false);

        Storage.setItem(storageKey, JSON.stringify(blogs));
        global.fetch = jest.fn(() => Promise.resolve(response));

        await wishlistStoragePlugin._merge(callback);

        expect(response.text).not.toHaveBeenCalled();
        expect(Storage.getItem(storageKey)).toBe(JSON.stringify(blogs));
        expect(document.querySelector('.flashbags').innerHTML).toBe('');
        expect(pageletSpy).not.toHaveBeenCalled();
        expect(callback).toHaveBeenCalledTimes(1);
    });

    test('merge handles empty successful response as request error', async () => {
        const blogs = { 'blog-1': '2026-05-06T00:00:00.000Z' };
        const callback = jest.fn();
        const pageletSpy = jest.spyOn(wishlistStoragePlugin, '_pagelet');
        const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

        Storage.setItem(storageKey, JSON.stringify(blogs));
        global.fetch = jest.fn(() => Promise.resolve(createTextResponse('')));

        await wishlistStoragePlugin._merge(callback);

        expect(warnSpy).toHaveBeenCalledTimes(1);
        expect(warnSpy.mock.calls[0][0]).toBeInstanceOf(Error);
        expect(warnSpy.mock.calls[0][0].message).toBe('Unable to merge blog wishlist from anonymous admin_user');
        expect(Storage.getItem(storageKey)).toBe(JSON.stringify(blogs));
        expect(pageletSpy).not.toHaveBeenCalled();
        expect(callback).toHaveBeenCalledTimes(1);
    });

    test('pagelet replaces listing row content when request succeeds', async () => {
        global.fetch = jest.fn(() => Promise.resolve(createTextResponse('<div class="blog-box">Blog</div>')));

        await wishlistStoragePlugin._pagelet();

        expect(global.fetch).toHaveBeenCalledWith('/wishlist/pagelet', {
            method: 'POST',
            headers: defaultHeaders,
        });
        expect(document.querySelector('.cms-listing-row').innerHTML).toBe('<div class="blog-box">Blog</div>');
    });

    test('pagelet does not parse unsuccessful response', async () => {
        const response = createTextResponse('', false);

        document.querySelector('.cms-listing-row').innerHTML = '<div class="blog-box">Existing</div>';
        global.fetch = jest.fn(() => Promise.resolve(response));

        await wishlistStoragePlugin._pagelet();

        expect(response.text).not.toHaveBeenCalled();
        expect(document.querySelector('.cms-listing-row').innerHTML).toBe('<div class="blog-box">Existing</div>');
    });
});
