import { mount } from '@vue/test-utils';
import { reactive, ref } from 'vue';
import { routeLocationKey, routerKey } from 'vue-router';

const refreshList = jest.fn();
const createFolder = jest.fn();
const setPresentation = jest.fn();
const setSorting = jest.fn();
const setMediaType = jest.fn();

async function createWrapper({ route, props = {}, repositorySearch = {}, libraryLoading = false } = {}) {
    const $route = reactive(route ?? { query: {} });
    const isLibraryLoading = ref(libraryLoading);

    return mount(await wrapTestComponent('ct-media-index', { sync: true }), {
        props,
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'ct-context-button': true,
                'ct-context-menu-item': true,
                'ct-page': {
                    template:
                        '<div><slot name="search-bar"></slot><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'ct-search-bar': true,
                'ct-media-sidebar': true,
                'ct-upload-listener': true,
                'ct-language-switch': true,
                'router-link': true,
                'ct-media-upload-v2': true,
                'ct-media-library': {
                    template: '<div></div>',
                    setup(_, { expose }) {
                        expose({
                            refreshList,
                            createFolder,
                            setPresentation,
                            setSorting,
                            setMediaType,
                            subFolders: [],
                            folderTotal: 2,
                            itemTotal: 3,
                            isLoading: isLibraryLoading,
                        });
                    },
                },
                'ct-loader': true,
            },
            mocks: {
                $route,
            },
            provide: {
                [routerKey]: {
                    push: jest.fn(),
                },
                [routeLocationKey]: $route,
                repositoryFactory: {
                    create: (entityName) => ({
                        create: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve();
                        },
                        search: () => {
                            return Promise.resolve(repositorySearch[entityName]);
                        },
                        save: jest.fn().mockResolvedValue(undefined),
                    }),
                },
                mediaService: {},
            },
        },
    });
}
describe('src/module/ct-media/page/ct-media-index', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
        refreshList.mockClear();
        createFolder.mockClear();
        setPresentation.mockClear();
        setSorting.mockClear();
        setMediaType.mockClear();
    });

    it('should contain the default accept value', async () => {
        const wrapper = await createWrapper();
        const fileInput = wrapper.find('ct-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('*/*');
    });

    it('should contain "application/pdf" value', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            fileAccept: 'application/pdf',
        });
        const fileInput = wrapper.find('ct-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('application/pdf');
    });

    it('should not be able to upload a new medium', async () => {
        global.activeAclRoles = ['media.viewer'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('ct-media-upload-v2-stub');
        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to upload a new medium', async () => {
        global.activeAclRoles = ['media.creator'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('ct-media-upload-v2-stub');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should keep the details sidebar collapsed until a media item is selected', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('ct-media-sidebar-stub').exists()).toBe(false);

        wrapper.vm.selectedItems = [
            {
                id: 'media-id',
                getEntityName: () => 'media',
            },
        ];
        await wrapper.vm.$nextTick();

        expect(wrapper.find('ct-media-sidebar-stub').exists()).toBe(true);
    });

    it('renders the media library as a sectioned workspace', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.ct-media-index__workspace-header').exists()).toBe(true);
        expect(wrapper.find('.ct-media-index__workspace-heading').text()).toContain('ct-media.index.titleAllMedia');

        const mediaLibrary = wrapper.get('.ct-media-index__media-library');
        expect(mediaLibrary.attributes('hide-search')).toBe('');
        expect(mediaLibrary.attributes('hide-create-folder')).toBe('');
        expect(mediaLibrary.attributes('hide-display-options')).toBe('');
        expect(mediaLibrary.attributes('show-section-headers')).toBe('');
        expect(mediaLibrary.attributes('inline-display-options')).toBeUndefined();
        expect(mediaLibrary.attributes('show-type-filter')).toBeUndefined();
    });

    it('uses the page search bar instead of a second workspace search field', async () => {
        const wrapper = await createWrapper({ route: { query: { term: 'hero' } } });

        expect(wrapper.get('ct-search-bar-stub').attributes('initial-search')).toBe('hero');
        expect(wrapper.get('.ct-media-index__media-library').attributes('hide-search')).toBe('');
    });

    it('keeps sorting and presentation controls in the workspace heading', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-media-index__workspace-actions').exists()).toBe(true);
        expect(wrapper.find('ct-media-display-options').exists()).toBe(true);
    });

    it('falls back to all media when the type control emits an empty value', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onMediaTypeChanged(null);

        expect(wrapper.vm.mediaType).toBe('all');
        expect(setMediaType).toHaveBeenCalledWith('all');
    });

    it('creates a folder from the folder tree', async () => {
        global.activeAclRoles = ['media.creator'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.onTreeAddFolder(null);

        expect(createFolder).toHaveBeenCalledTimes(1);
    });

    it('waits for the target folder to load before creating a child folder', async () => {
        global.activeAclRoles = ['media.creator'];

        const wrapper = await createWrapper({ libraryLoading: true });
        await wrapper.vm.onTreeAddFolder('child-parent-id');
        await wrapper.setProps({ routeFolderId: 'child-parent-id' });
        await flushPromises();

        expect(createFolder).not.toHaveBeenCalled();

        wrapper.vm.$refs.mediaLibrary.isLoading = false;
        await flushPromises();

        expect(createFolder).toHaveBeenCalledTimes(1);
    });

    it('expands nested folders from the explicit root node', async () => {
        const folders = [
            {
                id: 'parent-id',
                parentId: null,
                name: 'Parent',
            },
            {
                id: 'child-id',
                parentId: 'parent-id',
                name: 'Child',
            },
        ];

        const wrapper = await createWrapper({ repositorySearch: { media_folder: folders, media: [] } });
        await flushPromises();

        expect(wrapper.vm.visibleFolderEntries.map((entry) => entry.folder.id)).toEqual(['parent-id']);

        wrapper.vm.toggleTreeFolder('parent-id');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.visibleFolderEntries.map((entry) => entry.folder.id)).toEqual([
            'parent-id',
            'child-id',
        ]);
    });

    it('keeps root folders available in the navigation tree', async () => {
        const rootFolders = [
            {
                id: 'root-folder-id',
                name: 'Root folder',
            },
        ];
        rootFolders.total = 1;
        const allMedia = [];
        allMedia.total = 7;

        const wrapper = await createWrapper({
            repositorySearch: {
                media_folder: rootFolders,
                media: allMedia,
            },
        });
        await flushPromises();

        expect(wrapper.vm.navigationFolders).toEqual(rootFolders);
        expect(wrapper.vm.navigationMediaTotal).toBe(7);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });

    it('refreshes the list when the last upload finishes', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;

        wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(refreshList).toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
    });

    it('refreshes the list when the last upload fails', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;

        wrapper.vm.onUploadFailed({ targetId: 'upload-id' });

        expect(refreshList).toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
    });

    it('does not refresh the list before all uploads are finished', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 2;

        wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(refreshList).not.toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
        expect(wrapper.vm.pendingUploadsCount).toBe(1);
    });

    it('seeds the search term from the initial route query', async () => {
        const wrapper = await createWrapper({ route: { query: { term: 'logo.png' } } });

        expect(wrapper.vm.term).toBe('logo.png');
    });

    it('syncs the search term when the route query.term changes in the same folder', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.$route.query = { term: 'logo.png' };
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.term).toBe('logo.png');
        expect(wrapper.vm.selectedItems).toHaveLength(0);
    });

    it('adopts the route query.term when the folder changes', async () => {
        const wrapper = await createWrapper({ route: { query: { term: 'first.png' } } });
        expect(wrapper.vm.term).toBe('first.png');

        // Simulate clicking a search suggestion that targets a different folder
        // with a different term — both the routeFolderId prop and $route.query.term change.
        wrapper.vm.$route.query = { term: 'second.png' };
        await wrapper.setProps({ routeFolderId: 'folder-id' });

        expect(wrapper.vm.term).toBe('second.png');
    });

    it('clears the search term when the folder changes without a route query.term', async () => {
        const wrapper = await createWrapper({ route: { query: { term: 'first.png' } } });
        expect(wrapper.vm.term).toBe('first.png');

        wrapper.vm.$route.query = {};
        await wrapper.setProps({ routeFolderId: 'folder-id' });

        expect(wrapper.vm.term).toBe('');
    });
});
