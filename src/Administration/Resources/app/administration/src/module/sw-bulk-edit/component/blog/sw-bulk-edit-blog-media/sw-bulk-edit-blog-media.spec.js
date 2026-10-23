import { mount } from '@vue/test-utils';

/**
 * @sw-package inventory
 * @returns {Promise<Wrapper<Vue>>}
 */
async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-blog-media', { sync: true }), {
        global: {
            stubs: {
                'sw-bulk-edit-blog-media-form': true,
                'sw-media-modal-v2': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            create: () => {
                                return Promise.resolve();
                            },
                            search: () => {
                                return Promise.resolve({
                                    first: () => null,
                                });
                            },
                        };
                    },
                },
            },
        },
        props: {
            disabled: false,
        },
    });
}

describe('sw-bulk-edit-blog-media', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be disabled correctly', async () => {
        await wrapper.setProps({ disabled: true });
        expect(wrapper.find('sw-bulk-edit-blog-media-form-stub').attributes().disabled).toBeTruthy();

        await wrapper.setProps({ disabled: false });
        expect(wrapper.find('sw-bulk-edit-blog-media-form-stub').attributes().disabled).toBeUndefined();
    });
});
