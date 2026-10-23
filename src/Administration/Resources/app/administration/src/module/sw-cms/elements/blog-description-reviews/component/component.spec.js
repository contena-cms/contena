/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const blogMock = {
    name: 'Awesome Blog',
    description: 'This blog is awesome',
};

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('sw-cms-el-blog-description-reviews', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    cmsService: {
                        getCmsBlockRegistry: () => {
                            return {};
                        },
                        getCmsElementRegistry: () => {
                            return { 'blog-description-reviews': {} };
                        },
                    },
                },
            },
            props: {
                element: {
                    config: {},
                    data: {},
                },
                defaultConfig: {
                    alignment: {
                        value: null,
                    },
                },
                ...props,
            },
        },
    );
}

describe('src/module/sw-cms/elements/blog-description-reviews/component', () => {
    beforeAll(() => {
        Contena.Store.register({
            id: 'cmsPage',
            state() {
                return {
                    currentPage: {
                        type: 'landingpage',
                    },
                    currentMappingEntity: null,
                    currentDemoEntity: blogMock,
                };
            },
        });
    });

    beforeEach(() => {
        Contena.Store.get('cmsPage').$reset();
    });

    it('should display placeholder when page type is not blog page and no blog is selected', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('.sw-cms-el-blog-description-reviews__detail').exists()).toBeTruthy();
    });

    it('should display skeleton when page type is blog page and no blog is selected', async () => {
        const wrapper = await createWrapper();

        Contena.Store.get('cmsPage').currentPage.type = 'blog_detail';
        await flushPromises();

        expect(wrapper.find('.sw-cms-el-blog-description-reviews__placeholder').exists()).toBeTruthy();
    });

    it('should not change the persisted lock state on blog detail pages', async () => {
        const element = {
            config: {},
            data: {},
        };
        Contena.Store.get('cmsPage').currentPage.type = 'blog_detail';

        await createWrapper({ element });

        expect(element.locked).toBeUndefined();
    });

    it('should display data when blog is selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                data: {
                    blog: {
                        name: 'Blog information',
                        description: 'lorem',
                    },
                },
                config: {},
            },
        });

        expect(wrapper.find('.sw-cms-el-blog-description-reviews__detail-title').text()).toBe('Blog information');
    });

    it('should show current demo data if mapping entity is blog', async () => {
        const wrapper = await createWrapper();

        const cmsPageState = Contena.Store.get('cmsPage');
        cmsPageState.currentPage.type = 'blog_detail';
        cmsPageState.currentMappingEntity = 'blog';
        cmsPageState.currentDemoEntity = blogMock;

        await flushPromises();

        expect(wrapper.find('.sw-cms-el-blog-description-reviews__detail-title').text()).toBe('Awesome Blog');
    });

    it('should show dummy data initially if mapping entity is not blog', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'landingpage',
                },
                currentMappingEntity: null,
                currentDemoEntity: blogMock,
            },
        });

        expect(wrapper.find('.sw-cms-el-blog-description-reviews__detail-title').text()).toBe('Blog information');
    });
});
