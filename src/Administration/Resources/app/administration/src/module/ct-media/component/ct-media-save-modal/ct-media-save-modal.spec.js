import { mount } from '@vue/test-utils';

const createWrapper = async (propsData = {}) => {
    return mount(await wrapTestComponent('ct-media-save-modal', { sync: true }), {
        props: {
            ...propsData,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'ct-modal': {
                    template: `
                        <div class="ct-modal">
                          <slot name="modal-header"></slot>
                          <slot></slot>
                          <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'ct-media-grid': true,
                'ct-media-breadcrumbs': true,
                'ct-media-library': true,
                'ct-media-media-item': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: () =>
                            Promise.resolve({
                                id: 'folderId',
                                name: 'Test Folder',
                            }),
                        create: () => ({
                            id: 'mediaId',
                        }),
                        save: () => Promise.resolve(),
                    }),
                },
            },
        },
    });
};

describe('src/module/ct-media/component/ct-media-save-modal', () => {
    it('should emit save-media when clicking on save button', async () => {
        const wrapper = await createWrapper({
            initialFileName: 'Media name',
            initialFolderId: 'folderId',
        });

        // Wait for component to be fully mounted
        await wrapper.vm.$nextTick();

        // Mock mediaRepository.create to have been called
        const spyMediaRepositoryCreate = jest.spyOn(wrapper.vm.mediaRepository, 'create');
        const spyMediaRepositorySave = jest.spyOn(wrapper.vm.mediaRepository, 'save');

        const saveButton = wrapper.find('.ct-media-save-modal__button-save');
        await saveButton.trigger('click');

        expect(spyMediaRepositoryCreate).toHaveBeenCalled();
        expect(spyMediaRepositorySave).toHaveBeenCalledWith(
            expect.objectContaining({
                mediaFolderId: 'folderId',
                id: 'mediaId',
            }),
            expect.any(Object),
        );

        expect(wrapper.emitted('save-media')).toBeTruthy();
        expect(wrapper.emitted('save-media')[0]).toEqual([
            {
                fileName: 'Media name',
                mediaId: 'mediaId',
                folderId: 'folderId',
            },
        ]);

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should emit modal-close when clicking on cancel button', async () => {
        const wrapper = await createWrapper();

        const cancelButton = wrapper.find('.ct-media-save-modal__button-cancel');
        await cancelButton.trigger('click');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should set fileName to initialFileName prop when component is created', async () => {
        const wrapper = await createWrapper({
            initialFileName: 'Media name',
        });

        const input = wrapper.find('.ct-media-save-modal__input-file-name input');
        expect(input.element.value).toBe('Media name');
    });

    it('should update folderId when media-folder-change event is emitted from ct-media-library', async () => {
        const wrapper = await createWrapper();

        const mediaLibrary = wrapper.findComponent({ name: 'ct-media-library' });
        await mediaLibrary.vm.$emit('media-folder-change', 'newFolderId');
        await flushPromises();

        expect(wrapper.vm.folderId).toBe('newFolderId');
        expect(wrapper.vm.currentFolder).toEqual({ id: 'folderId', name: 'Test Folder' });
    });
});
