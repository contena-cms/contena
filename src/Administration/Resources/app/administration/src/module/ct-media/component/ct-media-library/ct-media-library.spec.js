import { mount } from '@vue/test-utils';

class Repository {
    constructor(entityName, amounts, total) {
        this.#entityName = entityName;
        this.#amounts = amounts;
        this.#total = total;
    }

    #entityName = '';

    #amounts = [];

    #total;

    invocation = 0;

    lastUsedCriteria;

    create() {
        return {
            id: '',
            getEntityName: () => this.#entityName,
        };
    }

    search(criteria) {
        const desiredAmount = this.#amounts[this.invocation];

        this.invocation += 1;
        this.lastUsedCriteria = criteria;

        const data = [];

        if (desiredAmount === null) {
            return Promise.reject();
        }

        for (let i = 0; i < desiredAmount; i += 1) {
            data.push({
                id: `${this.#entityName}-${this.invocation}-${i}`,
                getEntityName: () => this.#entityName,
            });
        }

        if (this.#total !== undefined) {
            data.total = this.#total;
        }

        return Promise.resolve(data);
    }
}

async function createWrapper({
    mediaAmount = [5],
    folderAmount = [5],
    limit = 5,
    mediaTotal,
    folderTotal,
    props: componentProps = {},
} = {}) {
    const mediaRepositoryMock = new Repository('media', mediaAmount, mediaTotal);
    const folderRepositoryMock = new Repository('media_folder', folderAmount, folderTotal);

    const props = { selection: [], ...componentProps };
    if (limit !== null) {
        props.limit = limit;
    }

    return mount(await wrapTestComponent('ct-media-library', { sync: true }), {
        props,
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'ct-media-display-options': true,
                'ct-media-entity-mapper': true,
                'ct-media-grid': true,
                'ct-skeleton': true,
                'ct-media-folder-item': true,
                'router-link': true,
                'ct-extension-teaser-popover': true,
            },

            provide: {
                repositoryFactory: {
                    create: (repositoryName) => {
                        switch (repositoryName) {
                            case 'media':
                                return mediaRepositoryMock;
                            case 'media_folder':
                                return folderRepositoryMock;
                            case 'media_folder_configuration':
                                return {};
                            default:
                                throw new Error(`No Repository found for ${repositoryName}`);
                        }
                    },
                },
                mediaService: {},
                searchRankingService: {
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
        },
    });
}

describe('src/module/ct-media/component/ct-media-library/index', () => {
    it('emits the local search term for the owning browser', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onTermChanged('hero');

        expect(wrapper.emitted('media-term-change')).toEqual([['hero']]);
    });

    it('separates folders and media into dedicated sections', async () => {
        const wrapper = await createWrapper({
            folderAmount: [2],
            mediaAmount: [3],
            folderTotal: 2,
            mediaTotal: 3,
        });
        await flushPromises();

        const folderSection = wrapper.get('.ct-media-library__folder-section');
        const mediaSection = wrapper.get('.ct-media-library__media-section');

        expect(folderSection.findAll('ct-media-entity-mapper-stub')).toHaveLength(2);
        expect(mediaSection.findAll('ct-media-entity-mapper-stub')).toHaveLength(3);
        expect(folderSection.get('ct-media-grid-stub').attributes('presentation')).toBe('list-preview');
    });

    it('notifies the owning page after a folder mutation refreshes the library', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                1,
                1,
            ],
            mediaAmount: [
                0,
                0,
            ],
        });
        await flushPromises();

        wrapper.getComponent({ name: 'ct-media-entity-mapper' }).vm.$emit('media-folder-changed');
        await flushPromises();

        expect(wrapper.emitted('media-folder-changed')).toHaveLength(1);
    });

    it('filters media by MIME type from the inline type control', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.mediaType = 'image';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.nextMediaCriteria.parse().filter).toEqual(
            expect.arrayContaining([
                {
                    type: 'prefix',
                    field: 'mimeType',
                    value: 'image/',
                },
            ]),
        );
    });

    it('forwards a media card click to the selection model', async () => {
        const wrapper = await createWrapper({
            folderAmount: [0],
            mediaAmount: [1],
        });
        await flushPromises();

        const mediaItem = wrapper.vm.items[0];
        wrapper.findComponent({ name: 'ct-media-entity-mapper' }).vm.$emit('media-item-click', {
            originalDomEvent: new MouseEvent('click'),
            item: mediaItem,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:selection').at(-1)).toEqual([[mediaItem]]);
    });

    it('hides the local toolbar when controls are owned by the page heading', async () => {
        const wrapper = await createWrapper({
            props: {
                hideSearch: true,
                hideCreateFolder: true,
                hideDisplayOptions: true,
            },
        });

        expect(wrapper.get('.ct-media-library').classes()).toContain('ct-media-library--without-toolbar');
        expect(wrapper.find('.ct-media-library__options-container').exists()).toBe(false);
    });

    it('accepts presentation, sorting, and type changes from the page heading', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.setPresentation('list-preview');
        wrapper.vm.setSorting({ sortBy: 'fileName', sortDirection: 'asc' });
        wrapper.vm.setMediaType('image');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.presentation).toBe('list-preview');
        expect(wrapper.vm.sorting).toEqual({ sortBy: 'fileName', sortDirection: 'asc' });
        expect(wrapper.vm.mediaType).toBe('image');
    });

    it('treats an empty media type as the all-media filter', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.setMediaType(null);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.mediaType).toBe('all');
        expect(wrapper.vm.nextMediaCriteria.parse().filter ?? []).not.toEqual(
            expect.arrayContaining([
                expect.objectContaining({ field: 'mimeType' }),
            ]),
        );
    });

    it('should load a further page of folders and media via "Load more"', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                5,
                5,
                3,
            ],
            mediaAmount: [
                5,
                5,
                3,
            ],
            folderTotal: 13,
            mediaTotal: 13,
        });
        await flushPromises();

        // First page is loaded, more than one further page remains
        expect(wrapper.vm.subFolders).toHaveLength(5);
        expect(wrapper.vm.items).toHaveLength(5);
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // "Load more" cannot finish everything, so both buttons are offered
        expect(wrapper.get('.ct-media-library__load-more-button').exists()).toBe(true);
        expect(wrapper.get('.ct-media-library__load-all-button').exists()).toBe(true);

        // Load one more page of folders and media
        wrapper.vm.loadNextItems();
        await flushPromises();

        expect(wrapper.vm.subFolders).toHaveLength(10);
        expect(wrapper.vm.items).toHaveLength(10);

        // Now only one page remains, so "Load more" is hidden and only "Load all" stays
        expect(wrapper.find('.ct-media-library__load-more-button').exists()).toBe(false);
        expect(wrapper.get('.ct-media-library__load-all-button').exists()).toBe(true);
    });

    it('should load every remaining element via "Load all"', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                5,
                5,
                3,
            ],
            mediaAmount: [
                5,
                5,
                3,
            ],
            folderTotal: 13,
            mediaTotal: 13,
        });
        await flushPromises();

        const loadAllButton = wrapper.get('.ct-media-library__load-all-button');
        expect(loadAllButton.exists()).toBe(true);

        // Load everything in one go
        await wrapper.vm.loadAll();
        await flushPromises();

        expect(wrapper.vm.subFolders).toHaveLength(13);
        expect(wrapper.vm.items).toHaveLength(13);
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Both buttons disappear once everything is loaded
        expect(wrapper.find('.ct-media-library__load-all-button').exists()).toBe(false);
        expect(wrapper.find('.ct-media-library__load-more-button').exists()).toBe(false);
    });

    it('should only show "Load all" when a single "Load more" would already load everything', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                5,
                3,
            ],
            mediaAmount: [
                5,
                3,
            ],
            folderTotal: 8,
            mediaTotal: 8,
        });
        await flushPromises();

        // One more page per loader would finish everything
        expect(wrapper.vm.loadMoreLoadsEverything).toBe(true);
        expect(wrapper.vm.showLoadMoreButton).toBe(false);
        expect(wrapper.vm.showLoadAllButton).toBe(true);

        expect(wrapper.find('.ct-media-library__load-more-button').exists()).toBe(false);
        expect(wrapper.get('.ct-media-library__load-all-button').exists()).toBe(true);
    });

    it('should show no load buttons when everything fits on the first page', async () => {
        const wrapper = await createWrapper({
            folderAmount: [3],
            mediaAmount: [2],
            folderTotal: 3,
            mediaTotal: 2,
        });
        await flushPromises();

        expect(wrapper.vm.allLoaded).toBe(true);
        expect(wrapper.find('.ct-media-library__load-more-button').exists()).toBe(false);
        expect(wrapper.find('.ct-media-library__load-all-button').exists()).toBe(false);
    });

    it('should limit association loading to 25', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.nextMedia();

        const usedCriteria = wrapper.vm.mediaRepository.lastUsedCriteria;

        expect(wrapper.vm.mediaRepository.invocation).toBe(2);

        [
            'tags',
            'avatarUsers',
        ].forEach((association) => {
            const associationParts = association.split('.');

            let path = null;
            associationParts.forEach((currentPart) => {
                path = path ? `${path}.${currentPart}` : currentPart;

                expect(usedCriteria.getAssociation(path).getLimit()).toBe(25);
            });
        });
    });

    it('should show the load more button if the folder request fails', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                null,
                3,
            ],
            mediaAmount: [
                3,
                undefined,
            ],
        });
        await flushPromises();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders).toHaveLength(0);
        expect(wrapper.vm.items).toHaveLength(3);
        expect(wrapper.vm.selectableItems).toHaveLength(3);

        // Check that additional media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(false);

        // Initiate another load
        let loadMoreButton = wrapper.get('.ct-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();
        await flushPromises();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders).toHaveLength(3);
        expect(wrapper.vm.items).toHaveLength(3);
        expect(wrapper.vm.selectableItems).toHaveLength(6);

        // Check that additional folders can be loaded, but not media
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        loadMoreButton = wrapper.find('.ct-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });

    it('should show the load more button if the media request fails', async () => {
        const wrapper = await createWrapper({
            folderAmount: [
                3,
                undefined,
            ],
            mediaAmount: [
                null,
                3,
            ],
        });
        await flushPromises();

        // Check that it starts with the correct amounts
        expect(wrapper.vm.subFolders).toHaveLength(3);
        expect(wrapper.vm.items).toHaveLength(0);
        expect(wrapper.vm.selectableItems).toHaveLength(3);

        // Check that additional media and folders can be loaded
        expect(wrapper.vm.itemLoaderDone).toBe(false);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        // Initiate another load
        let loadMoreButton = wrapper.get('.ct-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(true);
        wrapper.vm.loadNextItems();
        await flushPromises();

        // Check that appropriate amounts were loaded
        expect(wrapper.vm.subFolders).toHaveLength(3);
        expect(wrapper.vm.items).toHaveLength(3);
        expect(wrapper.vm.selectableItems).toHaveLength(6);

        // Check that additional folders can be loaded, but not media
        expect(wrapper.vm.itemLoaderDone).toBe(true);
        expect(wrapper.vm.folderLoaderDone).toBe(true);

        loadMoreButton = wrapper.find('.ct-media-library__load-more-button');
        expect(loadMoreButton.exists()).toBe(false);
    });

    it('should have a computed property for nextMediaCriteria', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.nextMediaCriteria.parse()).toEqual({
            page: 1,
            limit: 5,
            term: '',
            filter: [{ type: 'equals', field: 'mediaFolderId', value: null }],
            sort: [{ field: 'createdAt', order: 'desc', naturalSorting: false }],
            associations: {
                tags: { limit: 25, 'total-count-mode': 1 },
                avatarUsers: { limit: 25, 'total-count-mode': 1 },
            },
            'total-count-mode': 1,
        });
    });

    it('should have a computed property for nextFoldersCriteria', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.nextFoldersCriteria.parse()).toEqual({
            page: 1,
            limit: 5,
            term: '',
            filter: [{ type: 'equals', field: 'parentId', value: null }],
            sort: [{ field: 'name', order: 'asc', naturalSorting: false }],
            'total-count-mode': 1,
        });
    });

    it('should sort folders by name ascending', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.nextFoldersCriteria.parse().sort).toEqual([
            { field: 'name', order: 'asc', naturalSorting: false },
        ]);
    });

    it('should default the limit to 100 for folders and media', async () => {
        const wrapper = await createWrapper({ limit: null });

        expect(wrapper.vm.limit).toBe(100);
        expect(wrapper.vm.nextFoldersCriteria.limit).toBe(100);
        expect(wrapper.vm.nextMediaCriteria.limit).toBe(100);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });

    it('should refresh media item in items and selectedItems arrays', async () => {
        const wrapper = await createWrapper();

        const mockMediaItems = [
            {
                id: 'test-media-id-foo',
                getEntityName: () => 'media',
                title: 'Foo Title',
            },
            {
                id: 'test-media-id-bar',
                getEntityName: () => 'media',
                title: 'Bar Title',
            },
        ];

        wrapper.vm.items.push(...mockMediaItems);
        wrapper.vm.selectedItems.push(...mockMediaItems);

        const refreshMediaItem = {
            id: 'test-media-id-foo',
            getEntityName: () => 'media',
            title: 'New Title',
        };

        wrapper.vm.mediaRepository.get = jest.fn().mockResolvedValue(refreshMediaItem);

        await wrapper.vm.refreshItem(refreshMediaItem.id);

        expect(wrapper.vm.mediaRepository.get).toHaveBeenCalledWith(refreshMediaItem.id, expect.any(Object));
        expect(wrapper.vm.items).toContainEqual(refreshMediaItem);
        expect(wrapper.vm.selectedItems).toContainEqual(refreshMediaItem);
    });

    it('should handle refreshItem when media item not found in arrays', async () => {
        const wrapper = await createWrapper();

        const mockMediaItems = [
            {
                id: 'test-media-id-foo',
                getEntityName: () => 'media',
                title: 'Foo Title',
            },
            {
                id: 'test-media-id-bar',
                getEntityName: () => 'media',
                title: 'Bar Title',
            },
        ];

        wrapper.vm.items.push(...mockMediaItems);
        wrapper.vm.selectedItems.push(...mockMediaItems);

        const refreshMediaItem = {
            id: 'test-media-id-new',
            getEntityName: () => 'media',
            title: 'New Title',
        };

        wrapper.vm.mediaRepository.get = jest.fn().mockResolvedValue(refreshMediaItem);

        await wrapper.vm.refreshItem(refreshMediaItem.id);

        expect(wrapper.vm.mediaRepository.get).toHaveBeenCalledWith(refreshMediaItem.id, expect.any(Object));
        expect(wrapper.vm.items).not.toContainEqual(refreshMediaItem);
        expect(wrapper.vm.selectedItems).not.toContainEqual(refreshMediaItem);
    });

    it('should show Add folder button when canCreateFolder is true', async () => {
        const wrapper = await createWrapper();

        let addFolderButton = wrapper.find('.ct-media-index__create-folder-action');
        expect(addFolderButton.exists()).toBe(false);

        await wrapper.setProps({ allowCreateFolder: true });

        addFolderButton = wrapper.find('.ct-media-index__create-folder-action');
        expect(addFolderButton.exists()).toBe(true);
    });
});
