import Criteria from '@contena/meteor-admin-sdk/es/data/Criteria';
import { computed, inject, ref, type Ref, type WritableComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useListing, type InitializeListingOptions } from './use-listing';
import { useNotification } from './use-notification';

interface SettingsListingItem {
    id: string;
    name?: string;
    translated?: Record<string, unknown>;
    [key: string]: unknown;
}

interface SettingsListingItems extends Array<SettingsListingItem> {
    total: number;
}

interface SettingsRepository {
    search(criteria: Criteria): Promise<SettingsListingItems>;
    delete(id: string): Promise<void>;
    save(item: SettingsListingItem): Promise<void>;
}

interface RepositoryFactory {
    create(entityName: string): SettingsRepository;
}

interface SettingsListingState {
    entityName: string;
    items: SettingsListingItems;
    isLoading: boolean;
    showDeleteModal: string | boolean;
    deleteEntity: SettingsListingItem | null;
    steps: number[];
}

type SettingsListingStateRefs = {
    [Key in keyof SettingsListingState]: Ref<SettingsListingState[Key]>;
};

type SettingsListingOptions = InitializeListingOptions &
    Partial<{
        [Key in keyof SettingsListingState]: Ref<unknown>;
    }>;

/** @private */
export function useSettingsListing() {
    const listing = useListing();
    const notification = useNotification();
    const i18n = useI18n();
    const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
    const state: SettingsListingStateRefs = {
        entityName: ref(''),
        items: ref([] as unknown as SettingsListingItems),
        isLoading: ref(false),
        showDeleteModal: ref(false),
        deleteEntity: ref(null),
        steps: ref([
            10,
            25,
            50,
        ]),
    };
    let initialized = false;

    const createStateProxy = <Key extends keyof SettingsListingState>(
        key: Key,
    ): WritableComputedRef<SettingsListingState[Key]> =>
        computed({
            get: () => state[key].value,
            set: (value: SettingsListingState[Key]) => {
                state[key].value = value;
            },
        });

    const entityName = createStateProxy('entityName');
    const items = createStateProxy('items');
    const isLoading = createStateProxy('isLoading');
    const showDeleteModal = createStateProxy('showDeleteModal');
    const deleteEntity = createStateProxy('deleteEntity');
    const steps = createStateProxy('steps');

    const entityRepository = computed(() => repositoryFactory?.create(entityName.value));
    const listingCriteria = computed(() => {
        const criteria = new Criteria(listing.page.value, listing.limit.value);

        if (listing.term.value) {
            criteria.setTerm(listing.term.value);
        }

        return criteria;
    });
    const titleSaveSuccess = computed(() => {
        const key = `ct-settings-${entityName.value.replace(/[_]/g, '-')}.list.titleDeleteSuccess`;

        return i18n.te(key) ? i18n.t(key) : i18n.t('global.default.success');
    });
    const messageSaveSuccess = computed(() => {
        if (!deleteEntity.value) {
            return '';
        }

        let name = deleteEntity.value.name ?? '';
        if (typeof deleteEntity.value.translated?.name === 'string') {
            name = deleteEntity.value.translated.name;
        }

        const key = `ct-settings-${entityName.value.replace(/[_]/g, '-')}.list.messageDeleteSuccess`;
        if (i18n.te(`${key})`)) {
            return i18n.t(key, { name });
        }

        return i18n.t('global.notification.messageDeleteSuccess', { name });
    });

    function getList(): Promise<SettingsListingItems | undefined> {
        isLoading.value = true;
        const repository = entityRepository.value;

        if (!repository) {
            isLoading.value = false;
            return Promise.resolve(undefined);
        }

        return repository
            .search(listingCriteria.value)
            .then((response) => {
                items.value = response;
                listing.total.value = response.total;

                return response;
            })
            .finally(() => {
                isLoading.value = false;
            });
    }

    function initializeSettingsListing(options: SettingsListingOptions = {}): void {
        if (initialized) {
            return;
        }
        initialized = true;

        (Object.keys(state) as (keyof SettingsListingState)[]).forEach((key) => {
            const override = options[key];
            if (override) {
                const mutableState = state as unknown as Record<keyof SettingsListingState, Ref<unknown>>;
                mutableState[key] = override;
            }
        });

        if (entityName.value === '') {
            Contena.Utils.debug.warn('ct-settings-list composable', 'You need to define the data property "entityName".');
        }

        listing.initializeListing({
            ...options,
            getList: options.getList ?? getList,
        });
    }

    function onChangeLanguage(): void {
        listing.getList();
    }

    function onDelete(id: string): void {
        showDeleteModal.value = id;
    }

    function onCloseDeleteModal(): void {
        showDeleteModal.value = false;
    }

    async function onConfirmDelete(id: string): Promise<void> {
        deleteEntity.value = items.value.find((item) => item.id === id) ?? null;
        onCloseDeleteModal();

        try {
            await entityRepository.value?.delete(id);
            notification.createNotificationSuccess({
                title: titleSaveSuccess.value,
                message: messageSaveSuccess.value,
            });
        } finally {
            deleteEntity.value = null;
            listing.getList();
        }
    }

    async function onInlineEditSave(item: SettingsListingItem): Promise<void> {
        isLoading.value = true;

        try {
            await entityRepository.value?.save(item);
        } finally {
            isLoading.value = false;
        }
    }

    function onInlineEditCancel(): void {
        listing.getList();
    }

    return {
        ...listing,
        ...notification,
        getList,
        entityName,
        items,
        isLoading,
        showDeleteModal,
        deleteEntity,
        steps,
        entityRepository,
        listingCriteria,
        titleSaveSuccess,
        messageSaveSuccess,
        initializeSettingsListing,
        onChangeLanguage,
        onDelete,
        onCloseDeleteModal,
        onConfirmDelete,
        onInlineEditSave,
        onInlineEditCancel,
    };
}
