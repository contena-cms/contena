/**
 * @ct-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';

async function createWrapper({ mediaRepositoryMock = undefined } = {}) {
    const repositorySpy = jest.fn(() => Promise.resolve(mediaRepositoryMock));
    const wrapper = mount(await wrapTestComponent('ct-category-detail-menu', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'ct-upload-listener': true,
                'ct-media-upload-v2': {
                    template: '<div class="ct-media-upload-v2"></div>',
                    props: ['disabled'],
                },
                'mt-text-editor': {
                    template: '<div class="mt-text-editor"></div>',
                    props: ['disabled'],
                },
                'ct-media-modal-v2': {
                    template: '<div class="ct-media-modal-v2"><button @click="onEmitSelection">Add media</button></div>',
                    methods: {
                        onEmitSelection() {
                            this.$emit('media-modal-selection-change', [
                                { id: 'id' },
                            ]);
                        },
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: repositorySpy,
                            search: () => Promise.resolve({}),
                        };
                    },
                },
            },
        },
        props: {
            category: {
                id: 'id',
                visible: true,
                getEntityName: () => {},
            },
        },
    });
    return { wrapper, repositorySpy };
}

describe('src/module/ct-category/component/ct-category-detail-menu', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should enable the visibility switch field when the acl privilege is missing', async () => {
        global.activeAclRoles = ['category.editor'];

        const { wrapper } = await createWrapper();

        const switchField = wrapper.getComponent('.mt-switch');

        expect(switchField.props('disabled')).toBe(false);
    });

    it('should disable the visibility switch field when the acl privilege is missing', async () => {
        const { wrapper } = await createWrapper();

        const switchField = wrapper.getComponent('.mt-switch');

        expect(switchField.props('disabled')).toBe(true);
    });

    it('should enable the media upload', async () => {
        global.activeAclRoles = ['category.editor'];

        const { wrapper } = await createWrapper();

        const mediaUpload = wrapper.getComponent('.ct-media-upload-v2');

        expect(mediaUpload.props('disabled')).toBe(false);
    });

    it('should disable the media upload', async () => {
        const { wrapper } = await createWrapper();

        const mediaUpload = wrapper.getComponent('.ct-media-upload-v2');

        expect(mediaUpload.props('disabled')).toBe(true);
    });

    it('should enable the text editor for the description', async () => {
        global.activeAclRoles = ['category.editor'];

        const { wrapper } = await createWrapper();

        const textEditor = wrapper.getComponent('.mt-text-editor');

        expect(textEditor.props('disabled')).toBe(false);
    });

    it('should disable the text editor for the description', async () => {
        const { wrapper } = await createWrapper();

        const textEditor = wrapper.getComponent('.mt-text-editor');

        expect(textEditor.props('disabled')).toBe(true);
    });

    it('should open media modal', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.showMediaModal = true;
        await wrapper.vm.$nextTick();

        const mediaModal = wrapper.find('.ct-media-modal-v2');

        expect(mediaModal.exists()).toBe(true);
    });

    it('should turn off media modal', async () => {
        const { wrapper } = await createWrapper();

        const mediaModal = wrapper.find('.ct-media-modal-v2');

        expect(mediaModal.exists()).toBeFalsy();
    });

    it('should be able to change category media', async () => {
        const { wrapper, repositorySpy } = await createWrapper({ mediaRepositoryMock: { id: 'id' } });

        wrapper.vm.showMediaModal = true;
        await wrapper.vm.$nextTick();
        const button = wrapper.find('.ct-media-modal-v2 button');
        await button.trigger('click');
        await flushPromises();

        expect(repositorySpy).toHaveBeenCalledWith('id');
        expect(wrapper.props('category').mediaId).toBe('id');

        repositorySpy.mockRestore();
    });

    it('should not change category media when selected media is null', async () => {
        const { wrapper, repositorySpy } = await createWrapper();

        wrapper.vm.onMediaSelectionChange([]);

        expect(repositorySpy).not.toHaveBeenCalled();

        repositorySpy.mockRestore();
    });
});
