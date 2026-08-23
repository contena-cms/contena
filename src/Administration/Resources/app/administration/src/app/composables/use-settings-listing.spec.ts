import { mount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { createI18n } from 'vue-i18n';
import { defineComponent, ref, type ComponentPublicInstance } from 'vue';
import { useSettingsListing } from './use-settings-listing';

interface ListingItem {
    id: string;
    name: string;
}

interface ListingPublicInstance extends ComponentPublicInstance {
    items: ListingItem[];
    total: number;
    page: number;
    entityName: string;
    showDeleteModal: string | boolean;
    getList: () => Promise<unknown>;
    onDelete: (id: string) => void;
    onConfirmDelete: (id: string) => Promise<void>;
    onInlineEditSave: (item: ListingItem) => Promise<void>;
}

describe('src/app/composables/use-settings-listing', () => {
    let wrapper: VueWrapper;
    let router: Router;
    let search: jest.Mock;
    let save: jest.Mock;
    let remove: jest.Mock;
    let createNotification: jest.Mock;

    async function createWrapper(): Promise<VueWrapper<ListingPublicInstance>> {
        const response = Object.assign([{ id: 'one', name: 'One' }], { total: 1 });
        search = jest.fn(() => Promise.resolve(response));
        save = jest.fn(() => Promise.resolve());
        remove = jest.fn(() => Promise.resolve());
        createNotification = jest.fn();
        jest.spyOn(Contena.Store, 'get').mockReturnValue({ createNotification } as never);

        const ListingComponent = defineComponent({
            name: 'TestSettingsListing',
            setup() {
                const state = useSettingsListing();
                const entityName = ref('custom_field_set');

                state.initializeSettingsListing({
                    disableRouteParams: ref(true),
                    entityName,
                });

                return state;
            },
            template: '<div />',
        });

        router = createRouter({
            history: createMemoryHistory(),
            routes: [{ name: 'settings-list', path: '/', component: ListingComponent }],
        });
        await router.push({ name: 'settings-list' });

        wrapper = mount(defineComponent({ template: '<router-view />' }), {
            global: {
                plugins: [
                    router,
                    createI18n({ legacy: false, locale: 'en-GB', messages: {} }),
                ],
                provide: {
                    repositoryFactory: {
                        create: () => ({ search, save, delete: remove }),
                    },
                    searchRankingService: {
                        getSearchFieldsByEntity: jest.fn(() => ({})),
                        isValidTerm: jest.fn(() => false),
                        buildSearchQueriesForEntity: jest.fn(),
                    },
                },
            },
        });
        await flushPromises();

        return wrapper.findComponent(ListingComponent) as unknown as VueWrapper<ListingPublicInstance>;
    }

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('loads a generic settings repository with listing pagination', async () => {
        const listing = await createWrapper();

        expect(listing.vm.entityName).toBe('custom_field_set');
        expect(search).toHaveBeenCalledTimes(1);
        expect(listing.vm.items).toHaveLength(1);
        expect(listing.vm.total).toBe(1);
        expect(listing.vm.page).toBe(1);
    });

    it('persists inline edits through the settings repository', async () => {
        const listing = await createWrapper();
        const item = { id: 'one', name: 'Updated' };

        await listing.vm.onInlineEditSave(item);

        expect(save).toHaveBeenCalledWith(item);
    });

    it('deletes the selected item, closes the modal, and refreshes the listing', async () => {
        const listing = await createWrapper();

        listing.vm.onDelete('one');
        expect(listing.vm.showDeleteModal).toBe('one');

        await listing.vm.onConfirmDelete('one');

        expect(remove).toHaveBeenCalledWith('one');
        expect(listing.vm.showDeleteModal).toBe(false);
        expect(createNotification).toHaveBeenCalledWith({
            variant: 'success',
            title: 'global.default.success',
            message: 'global.notification.messageDeleteSuccess',
        });
        expect(search).toHaveBeenCalledTimes(2);
    });
});
