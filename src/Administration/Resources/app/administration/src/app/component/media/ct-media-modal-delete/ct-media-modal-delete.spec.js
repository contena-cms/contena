import { shallowMount } from '@vue/test-utils';

const createMedia = (options = {}) => ({
    getEntityName: () => 'media',
    id: 'media-id',
    fileName: 'demo.jpg',
    avatarUsers: [],
    ...options,
});

async function createWrapper(itemsToDelete) {
    return shallowMount(await wrapTestComponent('ct-media-modal-delete', { sync: true }), {
        props: { itemsToDelete },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve() }),
                },
            },
        },
    });
}

describe('components/media/ct-media-modal-delete', () => {
    it('shows quick usage information for a user avatar', async () => {
        const media = createMedia({ avatarUsers: [{ id: 'user-id' }] });
        const wrapper = await createWrapper([media]);

        expect(wrapper.vm.mediaQuickInfo).toStrictEqual(media);
        expect(wrapper.vm.mediaInUsages).toStrictEqual([]);
    });

    it('does not mark an unreferenced media item as used', async () => {
        const wrapper = await createWrapper([createMedia()]);

        expect(wrapper.vm.mediaQuickInfo).toBeNull();
        expect(wrapper.vm.mediaInUsages).toStrictEqual([]);
    });

    it('collects multiple user-avatar media usages', async () => {
        const media = [
            createMedia({ id: 'first', avatarUsers: [{ id: 'first-user' }] }),
            createMedia({ id: 'second', avatarUsers: [{ id: 'second-user' }] }),
        ];
        const wrapper = await createWrapper(media);

        expect(wrapper.vm.mediaInUsages).toStrictEqual(media);
    });

    it('does not treat folders as media usage', async () => {
        const folder = { getEntityName: () => 'media_folder', id: 'folder-id', name: 'Folder' };
        const wrapper = await createWrapper([folder]);

        expect(wrapper.vm.mediaQuickInfo).toBeNull();
        expect(wrapper.vm.mediaInUsages).toStrictEqual([]);
    });
});
