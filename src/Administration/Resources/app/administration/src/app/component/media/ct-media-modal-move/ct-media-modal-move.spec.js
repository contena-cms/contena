import { mount } from '@vue/test-utils';
import Entity from 'src/core/data/entity.data';

const rootFolderObject = {
    id: null,
    name: 'ct-media.index.rootFolderName',
};

const createMediaEntity = (options = {}) => {
    return new Entity(Contena.Utils.createId(), 'media', {
        fileName: 'test.png',
        ...options,
    });
};

const createFolderEntity = (options = {}) => {
    return new Entity(Contena.Utils.createId(), 'media_folder', {
        name: 'test',
        parentId: null,
        ...options,
    });
};

let repositoryFactoryMock;
async function createWrapper() {
    repositoryFactoryMock = {
        search: jest.fn(() => Promise.resolve([])),
    };

    return mount(await wrapTestComponent('ct-media-modal-move', { sync: true }), {
        props: {
            itemsToMove: [createMediaEntity()],
        },
        global: {
            stubs: {
                'ct-media-folder-content': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => repositoryFactoryMock,
                },
            },
        },
    });
}

describe('components/media/ct-media-modal-move', () => {
    it('removes parent folder if current folder is root folder', async () => {
        const wrapper = await createWrapper();

        Object.assign(wrapper.vm, {
            parentFolder: createFolderEntity(),
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.fetchParentFolder = jest.fn();

        await wrapper.vm.updateParentFolder(rootFolderObject);
        expect(wrapper.vm.fetchParentFolder).not.toHaveBeenCalled();
        expect(wrapper.vm.parentFolder).toBeNull();
    });

    it('correctly uses root folder as parent folder', async () => {
        const wrapper = await createWrapper();

        const childFolder = createFolderEntity({ parentId: null });
        wrapper.vm.fetchParentFolder = jest.fn();

        await wrapper.vm.updateParentFolder(childFolder);
        expect(wrapper.vm.fetchParentFolder).not.toHaveBeenCalled();
        expect(wrapper.vm.parentFolder).toMatchObject(rootFolderObject);
    });

    it('fetches parent folder when a parentId is given', async () => {
        const wrapper = await createWrapper();

        const mockedParent = createFolderEntity();
        const mockedChild = createFolderEntity({ parentId: mockedParent.id });

        repositoryFactoryMock.search = jest.fn(() =>
            Promise.resolve([
                mockedParent,
            ]),
        );

        await wrapper.vm.updateParentFolder(mockedChild);

        expect(repositoryFactoryMock.search).toHaveBeenCalled();
        expect(wrapper.vm.parentFolder).toMatchObject(mockedParent);
    });

    it('handles fetchParentFolder Admin API error gracefully', async () => {
        const wrapper = await createWrapper();

        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);
        wrapper.vm.mediaFolderRepository.search = jest.fn(Promise.reject);

        await wrapper.vm.fetchParentFolder(Contena.Utils.createId());

        expect(notificationSpy).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
    });
});
