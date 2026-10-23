/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const currentDemoProducts = [
    { id: 'BLOG-0' },
    { id: 'BLOG-1' },
    { id: 'BLOG-2' },
    { id: 'BLOG-3' },
];

const defaultConfig = {
    config: {
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
    },
    data: null,
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-blog-listing', {
            sync: true,
        }),
        {
            props: {
                element: {
                    config: {
                        boxLayout: {
                            value: 'standard',
                        },
                    },
                },
            },
            global: {
                stubs: {
                    'sw-cms-el-blog-box': {
                        name: 'sw-cms-el-blog-box',
                        template: '<div>Blog-Box</div>',
                        props: ['element'],
                    },
                },
                provide: {
                    cmsService: {
                        getCmsBlockRegistry: () => {
                            return {};
                        },
                        getCmsElementRegistry: () => {
                            return { 'blog-listing': {} };
                        },
                    },
                },
            },
        },
    );
}

describe('module/sw-cms/elements/blog-listing/component/index', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/blog-listing');
    });

    beforeEach(async () => {
        Contena.Store.get('cmsPage').resetCmsPageState();
    });

    it('should use demo blogs', async () => {
        const wrapper = await createWrapper();
        Contena.Store.get('cmsPage').setCurrentDemoProducts(currentDemoProducts);

        await wrapper.vm.$nextTick();
        const blogBoxes = wrapper.findAllComponents({
            name: 'sw-cms-el-blog-box',
        });

        expect(blogBoxes).toHaveLength(currentDemoProducts.length);
        blogBoxes.forEach((blogBox, index) => {
            const blog = currentDemoProducts[index];

            expect(blogBox.props('element')).toMatchObject({
                ...defaultConfig,
                data: { blog },
            });
        });
    });

    it('should use fallback to empty blogs', async () => {
        const wrapper = await createWrapper();
        Contena.Store.get('cmsPage').setCurrentDemoProducts([]);

        await wrapper.vm.$nextTick();
        const blogBoxes = wrapper.findAllComponents({
            name: 'sw-cms-el-blog-box',
        });

        expect(blogBoxes).toHaveLength(8);
        blogBoxes.forEach((blogBox) => {
            expect(blogBox.props('element')).toMatchObject({
                ...defaultConfig,
                data: null,
            });
        });
    });
});
