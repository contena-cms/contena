/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const defaultElementConfig = {
    blog: null,
    boxLayout: {
        source: 'static',
        value: 'standard',
    },
    displayMode: {
        source: 'static',
        value: 'standard',
    },
    verticalAlign: {
        source: 'static',
        value: null,
    },
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-blog-box', {
            sync: true,
        }),
        {
            props: {
                element: {
                    config: { ...defaultElementConfig },
                },
                defaultConfig: {
                    displayMode: {
                        value: null,
                    },
                    verticalAlign: {
                        value: null,
                    },
                },
            },
            global: {
                provide: {
                    cmsService: Contena.Service('cmsService'),
                },
                stubs: {},
            },
        },
    );
}

describe('module/sw-cms/elements/blog-box/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/blog-box');
    });

    it('should display skeleton when blog data is null', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                config: { ...defaultElementConfig },
                data: {
                    blog: null,
                },
            },
        });

        expect(wrapper.find('.sw-cms-el-blog-box__skeleton-name').exists()).toBe(true);
    });

    it('should not display skeleton when blog data is not null', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                config: { ...defaultElementConfig },
                data: {
                    blog: {
                        name: 'Lorem Ipsum dolor',
                        description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr,
                          sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
                          sed diam voluptua.`.trim(),
                        price: [{ gross: 19.9 }],
                        cover: {
                            media: {
                                url: '/administration/administration/static/img/cms/preview_glasses_large.webp',
                                alt: 'Lorem Ipsum dolor',
                            },
                        },
                    },
                },
            },
        });

        expect(wrapper.find('.sw-cms-el-blog-box__skeleton-name').exists()).toBe(false);
    });
});
