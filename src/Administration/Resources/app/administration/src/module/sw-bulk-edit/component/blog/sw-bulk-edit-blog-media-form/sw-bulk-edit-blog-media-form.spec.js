/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-bulk-edit-blog-media-form', {
            sync: true,
        }),
        {
            attachTo: document.body,
            global: {
                directives: {
                    draggable: {},
                    droppable: {},
                    popover: {},
                },
                stubs: {
                    'sw-upload-listener': true,
                    'sw-blog-image': await wrapTestComponent('sw-blog-image'),
                    'sw-media-upload-v2': true,
                    'sw-media-preview-v2': true,
                    'sw-blog-media-form': true,
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                    'mt-floating-ui': {
                        template: '<div><slot /></div>',
                    },
                    'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                    'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                    'sw-context-button': await wrapTestComponent('sw-context-button'),
                    'sw-loader': true,
                    'sw-label': true,
                    'router-link': true,
                },
                provide: {
                    repositoryFactory: {},
                    systemConfigApiService: {
                        getValues: () => {
                            return Promise.resolve({});
                        },
                    },
                },
            },
        },
    );
}

describe('src/module/sw-bulk-edit/component/blog/sw-bulk-edit-blog-media-form', () => {
    beforeAll(() => {
        const blog = {
            cover: {
                mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                position: 1,
                id: '520a8b95abc2446db77b173fcd718567',
                media: {
                    id: 'c621b5f556424911964e848fa1b7e8a5',
                },
            },
            coverId: '520a8b95abc2446db77b173fcd718567',
            media: [
                {
                    mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                    position: 1,
                    id: '520a8b95abc2446db77b173fcd718567',
                    media: {
                        id: 'c621b5f556424911964e848fa1b7e8a5',
                    },
                },
                {
                    mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                    position: 1,
                    id: '5a73a7f88b544a9ab52b2e795c95c7a7',
                    media: {
                        id: 'c621b5f556424911964e848fa1b7e8a5',
                    },
                },
            ],
        };
        blog.getEntityName = () => 'T-Shirt';

        Contena.Store.get('swProductDetail').blog = blog;
    });

    it('should show the sw-media-upload-v2 component', async () => {
        global.activeAclRoles = ['blog.editor'];

        const wrapper = await createWrapper();
        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeFalsy();
    });

    it('should not show button Use as cover', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.is--cover').exists()).toBeFalsy();

        await wrapper.find('.sw-blog-media-form__previews').find('.sw-blog-image__context-button').trigger('click');
        await flushPromises();

        const buttons = wrapper.find('.sw-context-menu').findAll('.sw-context-menu-item__text');
        expect(buttons).toHaveLength(1);
        expect(buttons.at(0).text()).toContain('global.default.remove');
    });
});
