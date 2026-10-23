/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-bulk-edit-blog-visibility', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-inherit-wrapper': {
                        template: '<div class="sw-inherit-wrapper"><slot name="content"></slot></div>',
                    },
                    'sw-blog-visibility-select': true,
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-blog-visibility-detail': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                create: () => {
                                    return Promise.resolve();
                                },
                            };
                        },
                    },
                },
            },
            props: {
                bulkEditProduct: {},
                disabled: false,
            },
        },
    );
}

describe('sw-bulk-edit-blog-visibility', () => {
    let wrapper;

    beforeAll(() => {
        Contena.Store.get('swProductDetail').blog = {
            visibilities: [
                {
                    blogId: 'blogId',
                    blogVersionId: 'blogVersionId',
                    channel: {},
                    channelId: 'channelId',
                    visibility: 30,
                },
            ],
        };
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be disabled correctly', async () => {
        await wrapper.setProps({ disabled: true });
        expect(wrapper.find('sw-blog-visibility-select-stub').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-card__quick-link.advanced-visibility').classes()).toContain('is--disabled');

        await wrapper.setProps({ disabled: false });
        expect(wrapper.find('sw-blog-visibility-select-stub').attributes().disabled).toBeUndefined();
        expect(wrapper.find('.sw-card__quick-link.advanced-visibility').classes()).not.toContain('is--disabled');
    });
});
