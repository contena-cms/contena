/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';

type BlogDetailWrapper = VueWrapper<{
    blog: { id: string; isNew: () => boolean };
    blogRepository: { hasChanges: jest.Mock };
    syncRepository: { save: jest.Mock };
    saveProduct: () => Promise<unknown>;
    loadProduct: () => Promise<void>;
    savePreferenceUnits: () => Promise<void>;
    loadAll: () => Promise<unknown>;
}>;

async function createWrapper({ blogType = 'digital', isNew = false } = {}) {
    const component = await wrapTestComponent('sw-blog-detail', { sync: true });
    const blog = {
        id: 'blog-a',
        type: blogType,
        purchasePrices: [{}],
        isNew: () => isNew,
    };
    const repository = {
        get: jest.fn().mockResolvedValue(blog),
        save: jest.fn().mockResolvedValue(undefined),
        hasChanges: jest.fn().mockReturnValue(true),
    };

    const wrapper = shallowMount(component, {
        props: { blogId: blog.id, creationType: blogType },
        global: {
            stubs: { 'sw-page': true },
            mocks: { $route: { name: 'sw.blog.detail.base', params: { id: blog.id } } },
            provide: {
                repositoryFactory: { create: () => repository },
                mediaService: {},
                numberRangeService: {},
                seoUrlService: {},
                systemConfigApiService: {},
                customFieldDataProviderService: {},
                entityValidationService: {},
            },
        },
    }) as unknown as BlogDetailWrapper;

    Object.assign(Contena.Store.get('swProductDetail'), { blog, creationType: blogType });
    jest.spyOn(wrapper.vm, 'savePreferenceUnits').mockResolvedValue(undefined);
    jest.spyOn(wrapper.vm, 'loadAll').mockResolvedValue(undefined);

    return wrapper;
}

describe('module/sw-blog/page/sw-blog-detail analytics', () => {
    let trackSpy: jest.SpyInstance;
    let wrapper: BlogDetailWrapper;

    beforeAll(() => {
        Contena.Component.override('sw-blog-detail', {
            // Skip page initialization so each test controls when loading and saving happen.
            methods: { createdComponent() {} },
        });
    });

    beforeEach(() => {
        trackSpy = jest.spyOn(Contena.Telemetry, 'track').mockImplementation(() => {});
        Contena.Store.get('swProductDetail').$reset();
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    const blogSaveCases = [
        { blogType: 'digital', action: 'creation', isNew: true },
        { blogType: 'digital', action: 'update', isNew: false },
        { blogType: 'physical', action: 'creation', isNew: true },
        { blogType: 'physical', action: 'update', isNew: false },
    ];

    it.each(blogSaveCases)('tracks successful $blogType blog $action', async ({ blogType, isNew }) => {
        wrapper = await createWrapper({ blogType, isNew });
        // Saving changes the entity state; analytics must retain its original creation status.
        wrapper.vm.syncRepository.save.mockImplementation(() => {
            wrapper.vm.blog.isNew = () => false;
            return Promise.resolve();
        });

        await wrapper.vm.saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.blog_saved',
            blog_type: blogType,
            blog_is_new: isNew,
        });
    });

    it('does not track unchanged blogs', async () => {
        wrapper = await createWrapper();
        wrapper.vm.blogRepository.hasChanges.mockReturnValue(false);

        await wrapper.vm.saveProduct();

        expect(trackSpy).not.toHaveBeenCalled();
    });

    it('does not track failed saves', async () => {
        wrapper = await createWrapper();
        wrapper.vm.syncRepository.save.mockRejectedValue(new Error('Save failed'));

        await wrapper.vm.saveProduct();

        expect(trackSpy).not.toHaveBeenCalled();
    });

    it.each(['digital', 'physical'])('tracks opening a %s blog', async (blogType) => {
        wrapper = await createWrapper({ blogType });

        await wrapper.vm.loadProduct();

        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.blog_viewed',
            blog_type: blogType,
        });
    });

    it('does not repeat view events when the same blog reloads', async () => {
        wrapper = await createWrapper();

        await wrapper.vm.loadProduct();
        await wrapper.vm.loadProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
    });
});
