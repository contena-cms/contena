import { mount } from '@vue/test-utils';

let repositoryFactoryCreateMock;
let repositoryFactorySearchMock;
let repositoryFactorySearchIdsMock;
let repositoryFactorySaveMock;

async function createWrapper() {
    repositoryFactoryCreateMock = jest.fn(() => Promise.resolve());
    repositoryFactorySearchMock = jest.fn(() => Promise.resolve([]));
    repositoryFactorySearchIdsMock = jest.fn(() => Promise.resolve([]));
    repositoryFactorySaveMock = jest.fn(() => Promise.resolve());

    return mount(
        await wrapTestComponent('ct-media-modal-folder-settings', {
            sync: true,
        }),
        {
            props: {
                mediaFolderId: '12345',
                disabled: false,
            },
            global: {
                stubs: {
                    'ct-modal': await wrapTestComponent('ct-modal', {
                        sync: true,
                    }),
                    'ct-text-field': true,
                    'ct-highlight-text': true,
                    'ct-select-result': true,
                    'ct-entity-single-select': true,
                    'ct-container': true,
                    'ct-field': true,
                    'mt-number-field': true,
                    'ct-media-add-thumbnail-form': true,
                    'ct-loader': true,
                    'mt-tabs': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            return {
                                create: (...args) => repositoryFactoryCreateMock(...args),
                                search: (...args) => repositoryFactorySearchMock(...args),
                                searchIds: (...args) => repositoryFactorySearchIdsMock(...args),
                                save: repositoryFactorySaveMock,
                                get: () => {
                                    switch (entity) {
                                        case 'media_folder_configuration':
                                            return Promise.resolve({
                                                mediaThumbnailSizes: {
                                                    entity: 'media_thumbnail_size',
                                                    source: 'media_thumbnail_size',
                                                },
                                            });
                                        default:
                                            return Promise.resolve({
                                                id: '12345',
                                                name: 'Test folder',
                                                parentId: null,
                                                configurationId: '12345',
                                            });
                                    }
                                },
                            };
                        },
                    },
                },
            },
        },
    );
}

describe('src/app/asyncComponent/media/ct-media-modal-folder-settings', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should get thumbnail sizes and unused thumbnail sizes with the correct criteria', async () => {
        const searchIds = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'searchIds');
        const search = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'search');

        await wrapper.vm.createdComponent();

        expect(searchIds).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'mediaFolderConfigurations.mediaFolders.id',
                        type: 'equals',
                        value: null,
                    },
                ],
            }),
        );
        expect(search).toHaveBeenCalledWith(
            expect.objectContaining({
                sortings: [
                    {
                        field: 'width',
                        naturalSorting: false,
                        order: 'ASC',
                    },
                ],
            }),
        );
    });

    it('should update thumbnail sizes correctly', async () => {
        repositoryFactorySearchIdsMock = jest.fn(() => {
            return Promise.resolve({
                data: ['12345'],
            });
        });
        repositoryFactorySearchMock = jest.fn(() => {
            return Promise.resolve([
                {
                    id: '12345',
                    width: 100,
                    height: 100,
                },
                {
                    id: '67890',
                    width: 200,
                    height: 200,
                },
            ]);
        });

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.unusedThumbnailSizes).toEqual(['12345']);
        expect(wrapper.vm.thumbnailSizes).toEqual([
            {
                id: '12345',
                width: 100,
                height: 100,
                deletable: true,
            },
            {
                id: '67890',
                width: 200,
                height: 200,
                deletable: false,
            },
        ]);
    });

    it('should be able to add a new thumbnail size', async () => {
        repositoryFactoryCreateMock = jest.fn(() => {
            return { _isNew: true };
        });

        Object.assign(wrapper.vm, {
            thumbnailSizes: [
                {
                    width: 10,
                    height: 10,
                    deletable: true,
                },
                {
                    width: 20,
                    height: 20,
                    deletable: false,
                },
            ],
        });
        await wrapper.vm.$nextTick();
        await wrapper.vm.addThumbnail({
            width: 30,
            height: 30,
        });

        expect(repositoryFactoryCreateMock).toHaveBeenCalled();
        expect(repositoryFactorySaveMock).toHaveBeenCalledWith(
            expect.objectContaining({
                _isNew: true,
                width: 30,
                height: 30,
            }),
            expect.any(Object),
        );
    });

    it('should not be able to add a new thumbnail size if the size already exists', async () => {
        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        Object.assign(wrapper.vm, {
            thumbnailSizes: [
                {
                    width: 10,
                    height: 10,
                    deletable: true,
                },
                {
                    width: 20,
                    height: 20,
                    deletable: false,
                },
            ],
        });
        await wrapper.vm.$nextTick();
        await wrapper.vm.addThumbnail({
            width: 10,
            height: 10,
        });

        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'global.ct-media-modal-folder-settings.notification.error.messageThumbnailSizeExisted',
            }),
        );
    });
});
