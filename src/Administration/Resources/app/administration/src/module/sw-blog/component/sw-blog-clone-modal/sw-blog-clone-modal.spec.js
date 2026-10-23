/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-blog-clone-modal', { sync: true }), {
        props: {
            blog: {},
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        clone: jest.fn(() =>
                            Promise.resolve({
                                id: '1a2b3c',
                            }),
                        ),
                        save: () => Promise.resolve(),
                        searchIds: () => Promise.resolve({ data: { length: 0 } }),
                    }),
                },
                numberRangeService: {
                    reserve: () => Promise.resolve({ number: 1337 }),
                },
            },
            stubs: {
                'mt-progress-bar': true,
            },
        },
    });
}

describe('src/module/sw-blog/component/sw-blog-clone-modal', () => {
    /** @type Wrapper */
    let wrapper;

    it('should clone parent without mainVariantId', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            blog: {
                name: 'shirt',
                variantListingConfig: {
                    mainVariantId: '1a2b3c',
                },
                childCount: 1,
            },
        });

        await wrapper.vm.cloneParent({
            number: 250,
        });

        expect(wrapper.vm.repository.clone).toHaveBeenCalledWith(
            undefined,
            {
                cloneChildren: false,
                overwrites: {
                    active: false,
                    mainVariantId: null,
                    canonicalProductId: null,
                    name: 'shirt global.default.copy',
                    blogNumber: 250,
                    variantListingConfig: {
                        mainVariantId: null,
                    },
                },
            },
            expect.anything(),
        );
    });

    it('should clone variants without their canonical blog', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const callback = jest.fn();
        wrapper.vm.cloneProgress = 1;

        wrapper.vm.duplicateVariant({ id: 'duplicate-id', blogNumber: 'SW1' }, ['variant-id'], callback);
        await flushPromises();

        expect(wrapper.vm.repository.clone).toHaveBeenLastCalledWith(
            'variant-id',
            {
                cloneChildren: false,
                overwrites: {
                    parentId: 'duplicate-id',
                    blogNumber: 'SW1.1',
                    canonicalProductId: null,
                },
            },
            expect.anything(),
        );
        expect(callback).toHaveBeenCalled();
    });

    it('should not change the original blog', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const blog = {
            name: 'shirt',
            variantListingConfig: {
                mainVariantId: '1a2b3c',
            },
            childCount: 1,
        };

        await wrapper.setProps({
            blog: blog,
        });

        expect(blog.variantListingConfig.mainVariantId).toBe('1a2b3c');
    });

    it('should save a new blog before reserving the duplicate blog number', async () => {
        const blog = {
            id: 'blog-id',
            name: 'shirt',
            blogNumber: 'SW10011',
            childCount: 0,
        };
        const save = jest.fn(() => Promise.resolve());
        const clone = jest.fn(() => Promise.resolve({ id: 'duplicate-id' }));
        const reserve = jest.fn().mockResolvedValueOnce({ number: 'SW10012' }).mockResolvedValueOnce({ number: 'SW10013' });

        wrapper = await mount(await wrapTestComponent('sw-blog-clone-modal', { sync: true }), {
            props: {
                blog,
                blogNumberPreview: 'SW10011',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            clone,
                            save,
                            searchIds: () => Promise.resolve({ data: { length: 0 } }),
                        }),
                    },
                    numberRangeService: {
                        reserve,
                    },
                },
                stubs: {
                    'mt-progress-bar': true,
                },
            },
        });

        await flushPromises();

        expect(save).toHaveBeenCalledWith(blog);
        expect(reserve).toHaveBeenNthCalledWith(1, 'blog');
        expect(reserve).toHaveBeenNthCalledWith(2, 'blog');
        expect(save.mock.invocationCallOrder[0]).toBeLessThan(reserve.mock.invocationCallOrder[1]);
        expect(clone).toHaveBeenCalledWith(
            'blog-id',
            expect.objectContaining({
                overwrites: expect.objectContaining({
                    blogNumber: 'SW10013',
                }),
            }),
            expect.anything(),
        );
    });
});
