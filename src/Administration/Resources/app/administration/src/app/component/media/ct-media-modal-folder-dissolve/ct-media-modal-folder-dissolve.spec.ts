import { mount } from '@vue/test-utils';

describe('ct-media-modal-folder-dissolve', () => {
    it('dissolves every selected folder and emits their ids', async () => {
        const dissolveFolder = jest.fn().mockResolvedValue(undefined);
        const folders = [
            { id: 'folder-one', name: 'Folder one', isLoading: false },
            { id: 'folder-two', name: 'Folder two', isLoading: false },
        ];
        const wrapper = mount(await wrapTestComponent('ct-media-modal-folder-dissolve', { sync: true }), {
            props: { itemsToDissolve: folders },
            global: {
                provide: {
                    mediaFolderService: { dissolveFolder },
                },
                stubs: {
                    'ct-modal': {
                        template: '<div><slot /><slot name="modal-footer" /></div>',
                    },
                    'mt-button': {
                        template: '<button><slot /></button>',
                    },
                },
            },
        });

        await wrapper.get('.ct-media-modal-folder-dissolve__confirm').trigger('click');
        await flushPromises();

        expect(dissolveFolder).toHaveBeenCalledTimes(2);
        expect(dissolveFolder).toHaveBeenNthCalledWith(1, 'folder-one');
        expect(dissolveFolder).toHaveBeenNthCalledWith(2, 'folder-two');
        expect(wrapper.emitted('media-folder-dissolve-modal-dissolve')).toEqual([
            [
                [
                    'folder-one',
                    'folder-two',
                ],
            ],
        ]);
        expect(folders.every((folder) => folder.isLoading === false)).toBe(true);
    });
});
