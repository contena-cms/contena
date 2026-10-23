/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(propsOverride) {
    return mount(
        await wrapTestComponent('sw-cms-el-blog-name', {
            sync: true,
        }),
        {
            props: {
                element: {
                    config: {
                        content: {
                            source: 'static',
                            value: '',
                        },
                        verticalAlign: {
                            source: 'static',
                            value: null,
                        },
                    },
                },
                defaultConfig: {},
                ...propsOverride,
            },
            global: {
                mocks: {
                    $sanitize: (key) => key,
                },
                provide: {
                    cmsService: Contena.Service('cmsService'),
                },
                stubs: {
                    'sw-text-editor': true,
                },
            },
        },
    );
}

describe('module/sw-cms/elements/blog-name/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    afterEach(() => {
        Contena.Store.get('cmsPage').resetCmsPageState();
    });

    it('should map to a blog name if the component is in a blog page', async () => {
        Contena.Store.get('cmsPage').setCurrentPage({
            type: 'blog_detail',
        });
        const wrapper = await createWrapper();

        expect(wrapper.vm.element.config.content.source).toBe('mapped');
        expect(wrapper.vm.element.config.content.value).toBe('blog.name');
    });

    it('should not initially map to a blog name if element translated config exists', async () => {
        Contena.Store.get('cmsPage').setCurrentPage({
            type: 'blog_detail',
        });
        const wrapper = await createWrapper({
            element: {
                config: {
                    content: {
                        source: 'static',
                        value: 'Sample Blog',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
                translated: {
                    config: {
                        content: {
                            source: 'static',
                            value: 'Sample Blog',
                        },
                        verticalAlign: {
                            source: 'static',
                            value: null,
                        },
                    },
                },
            },
        });

        expect(wrapper.vm.element.config.content.source).toBe('static');
        expect(wrapper.vm.element.config.content.value).toBe('Sample Blog');
    });

    it('should display skeleton on blog name block if entity demo is null', async () => {
        Contena.Store.get('cmsPage').setCurrentPage({
            type: 'blog_detail',
        });
        const wrapper = await createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentDemoEntity: null,
            },
        });

        expect(wrapper.find('.sw-cms-el-blog-name__placeholder').exists()).toBeTruthy();
    });

    it('should display placeholder on blog name block if data mapping is set to "blog.name"', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'blog.name',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
            },
        });

        await wrapper.setData({
            cmsPageState: {
                currentDemoEntity: null,
            },
        });

        expect(wrapper.find('.sw-cms-el-blog-name__placeholder').exists()).toBeTruthy();
    });

    it('should display skeleton on blog name block if data mapping is not set to "blog.name"', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'blog.ean',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
            },
        });

        await wrapper.setData({
            cmsPageState: {
                currentDemoEntity: null,
            },
        });

        expect(wrapper.find('.sw-cms-el-blog-name__skeleton').exists()).toBeTruthy();
    });

    it('demoValue is retrieved from cms state, if it exists', async () => {
        Contena.Store.get('cmsPage').setCurrentDemoEntity({
            name: 'Test blog',
            ean: 'test-ean',
        });

        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                config: {
                    content: {
                        source: 'mapped',
                        value: 'blog.ean',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                },
            },
        });

        expect(wrapper.vm.demoValue).toBe('test-ean');
    });
});
