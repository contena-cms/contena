/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-blog-detail-cross-selling', {
            sync: true,
        }),
        {
            props: {
                crossSelling: null,
            },
            global: {
                mocks: {
                    $router: {
                        resolve: jest.fn((route) => ({
                            href: `#/sw/blog/detail/${route.params.id}/cross-selling`,
                        })),
                    },
                },
                stubs: {
                    'sw-blog-cross-selling-form': true,
                    'sw-skeleton': true,
                    'sw-inheritance-switch': true,

                    'router-link': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve('bar'),
                        }),
                    },
                    acl: { can: () => true },
                },
            },
        },
    );
}

function buildProduct() {
    return {
        crossSellings: [
            {
                assignedProducts: ['bar'],
            },
        ],
    };
}

describe('src/module/sw-blog/view/sw-blog-detail-cross-selling', () => {
    let wrapper;

    beforeEach(async () => {
        Contena.Store.get('swProductDetail').$reset();

        if (Contena.Store.get('context')) {
            Contena.Store.unregister('context');
        }
        Contena.Store.register({
            id: 'context',

            getters: {
                isSystemDefaultLanguage() {
                    return true;
                },
            },

            state() {
                return {
                    api: {
                        assetsPath: '/',
                    },
                };
            },
        });
    });

    it('should load assigned blogs', async () => {
        const customProduct = buildProduct();

        wrapper = await createWrapper();
        await wrapper.setData({ blog: customProduct });
        await flushPromises();

        expect(customProduct.crossSellings[0].assignedProducts).toStrictEqual(['bar']);
    });

    it('should show inherited state when blog is a variant', async () => {
        Contena.Store.get('swProductDetail').blog = {
            id: 'blogId',
            parentId: 'parentProductId',
            crossSellings: [],
        };
        Contena.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(true);
        expect(wrapper.vm.isInherited).toBe(true);
    });

    it('should show empty state for main blog', async () => {
        Contena.Store.get('swProductDetail').blog = {
            id: 'blogId',
            parentId: null,
            crossSellings: [],
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(false);
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('should render the empty state without a parent link for a main blog', async () => {
        Contena.Store.get('swProductDetail').blog = {
            id: 'blogId',
            parentId: null,
            crossSellings: [],
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const emptyState = wrapper.find('.sw-blog-detail-cross-selling__empty-state');
        expect(emptyState.exists()).toBe(true);
        expect(emptyState.text()).toContain('sw-blog.crossselling.emptyStateDescription');
        expect(emptyState.find('.mt-empty-state__link').exists()).toBe(false);
    });

    it('should link to the parent cross sellings while the variant inherits', async () => {
        Contena.Store.get('swProductDetail').blog = {
            id: 'blogId',
            parentId: 'parentProductId',
            crossSellings: [],
        };
        Contena.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const emptyState = wrapper.find('.sw-blog-detail-cross-selling__empty-state');
        expect(emptyState.text()).toContain('sw-blog.crossselling.inheritedEmptyStateDescription');
        expect(emptyState.find('.mt-empty-state__link').exists()).toBe(true);
    });

    it('should describe the removed inheritance without a parent link', async () => {
        Contena.Store.get('swProductDetail').blog = {
            id: 'blogId',
            parentId: 'parentProductId',
            crossSellings: [],
        };
        Contena.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.isInherited = false;
        await wrapper.vm.$nextTick();

        const emptyState = wrapper.find('.sw-blog-detail-cross-selling__empty-state');
        expect(emptyState.text()).toContain('sw-blog.crossselling.notInheritedEmptyStateDescription');
        expect(emptyState.find('.mt-empty-state__link').exists()).toBe(false);
    });

    it('should only allow toggling the inherit switch when the inheritance is removed', async () => {
        Contena.Store.get('swProductDetail').blog = {
            id: 'blogId',
            parentId: 'parentProductId',
            crossSellings: [],
        };
        Contena.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const switchInput = () => wrapper.find('.sw-blog-detail-cross-selling__inherit-switch input');
        expect(switchInput().attributes('disabled')).toBeDefined();

        wrapper.vm.isInherited = false;
        await wrapper.vm.$nextTick();

        expect(switchInput().attributes('disabled')).toBeUndefined();
    });

    it('should keep the deprecated assetFilter for template overrides', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toBe(Contena.Filter.getByName('asset'));
    });
});
