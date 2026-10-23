/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const blogMock = {
    name: 'Lorem Ipsum dolor',
    id: '1234',
    blogNumber: '1234',
    minPurchase: 1,
    deliveryTime: {
        name: '1-3 days',
    },
    price: [{ gross: 100 }],
};

async function createWrapper({ featureActive = false } = {}) {
    return mount(
        await wrapTestComponent('sw-cms-el-config-buy-box', {
            sync: true,
        }),
        {
            props: {
                element: {
                    type: 'buy-box',
                    data: {},
                    config: {},
                },
                defaultConfig: {
                    blog: {
                        value: null,
                    },
                    alignment: {
                        value: null,
                    },
                },
            },
            global: {
                stubs: {
                    'sw-tabs': {
                        template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
                    },
                    'sw-tabs-item': true,
                    'sw-entity-single-select': true,
                    'sw-blog-variant-info': true,
                    'sw-select-result': true,
                    'sw-select-field': true,
                    'mt-select': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        emits: ['new-item-active'],
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                        template: '<div class="mt-tabs"></div>',
                    },
                    'sw-cms-inherit-wrapper': {
                        template: '<div><slot :isInherited="false"></slot></div>',
                        props: [
                            'field',
                            'element',
                            'contentEntity',
                            'label',
                        ],
                    },
                },
                provide: {
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
                    cmsService: {
                        getCmsElementRegistry: () => ({
                            'buy-box': {
                                defaultConfig: {
                                    blog: {
                                        source: 'static',
                                        value: null,
                                    },
                                    alignment: {
                                        source: 'static',
                                        value: null,
                                    },
                                },
                            },
                        }),
                    },
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve(blogMock),
                                search: () => Promise.resolve(blogMock),
                            };
                        },
                    },
                },
            },
        },
    );
}

describe('module/sw-cms/elements/buy-box/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/buy-box');
    });

    afterEach(() => {
        Contena.Store.get('cmsPage').resetCmsPageState();
    });

    it('should show blog selector if page type is not blog detail', async () => {
        const wrapper = await createWrapper();
        const blogSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(blogSelector.exists()).toBeTruthy();
        expect(alert.exists()).toBeFalsy();
    });

    it('should show alert information if page type is blog detail', async () => {
        Contena.Store.get('cmsPage').setCurrentPage({
            type: 'blog_detail',
        });
        const wrapper = await createWrapper();

        const blogSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('[role="banner"]');

        expect(blogSelector.exists()).toBeFalsy();
        expect(alert.exists()).toBeTruthy();
    });

    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-cms-element-config-buy-box');
        expect(tabs.props('defaultItem')).toBe('content');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-cms.elements.general.config.tab.content',
                name: 'content',
            },
            {
                label: 'sw-cms.elements.general.config.tab.options',
                name: 'options',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
        expect(wrapper.find('sw-entity-single-select-stub').exists()).toBe(true);
    });

    it('should switch meteor tab content when the active tab changes', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        await tabs.vm.$emit('new-item-active', 'options');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('options');
        expect(wrapper.find('sw-entity-single-select-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-buy-box__alignment').exists()).toBe(true);
    });

    it('should fetch blogs via API if blog is selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.onProductChange(blogMock.id);

        expect(wrapper.vm.element.config.blog.value).toBe(blogMock.id);
        expect(wrapper.vm.element.data.blogId).toBe(blogMock.id);
        expect(wrapper.vm.element.data.blog).toMatchObject(blogMock);
    });

    it('should delete blog if no blog is selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.onProductChange(null);

        expect(wrapper.vm.element.config.blog.value).toBeNull();
        expect(wrapper.vm.element.data.blogId).toBeNull();
        expect(wrapper.vm.element.data.blog).toBeNull();
    });
});
