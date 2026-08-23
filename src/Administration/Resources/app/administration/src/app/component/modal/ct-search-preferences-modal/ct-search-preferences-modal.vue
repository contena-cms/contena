<template>
    <!-- eslint-disable vuejs-accessibility/click-events-have-key-events -->
    <ct-block name="sw_search_preferences_modal">
        <ct-modal
            class="ct-search-preferences-modal"
            :title="$t('global.ct-search-preferences-modal.title')"
            :is-loading="isLoading"
            @modal-close="onClose"
        >
            <ct-block name="sw_search_preferences_modal_description">
                <p
                    class="ct-search-preferences-modal__description"
                    v-html="$t('global.ct-search-preferences-modal.description')"
                ></p>
            </ct-block>

            <ct-block name="sw_search_preferences_modal_grid">
                <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
                <ct-data-grid
                    :show-selection="false"
                    :show-actions="false"
                    :plain-appearance="true"
                    :data-source="searchPreferences"
                    :columns="searchPreferencesColumns"
                >
                    <template #column-active="{ item }">
                        <ct-block name="sw_search_preferences_modal_grid_column_active">
                            <mt-checkbox
                                v-model:checked="item._searchable"
                                @update:checked="onChangeSearchPreference(item)"
                            />
                        </ct-block>
                    </template>

                    <template #column-moduleName="{ item }">
                        <ct-block name="sw_search_preferences_modal_grid_column_module_name">
                            <span>{{ getModuleName(item.entityName) }}</span>
                        </ct-block>
                    </template>
                </ct-data-grid>
            </ct-block>

            <template #modal-footer>
                <ct-block name="sw_search_preferences_modal_button_cancel">
                    <mt-button
                        size="small"
                        class="ct-search-preferences-modal__button-cancel"
                        variant="secondary"
                        @click="onCancel"
                    >
                        {{ $t('global.default.cancel') }}
                    </mt-button>
                </ct-block>

                <ct-block name="sw_search_preferences_modal_button_save">
                    <mt-button
                        variant="primary"
                        size="small"
                        class="ct-search-preferences-modal__button-save"
                        :disabled="isLoading"
                        @click="onSave"
                    >
                        {{ $t('global.default.save') }}
                    </mt-button>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import { KEY_USER_SEARCH_PREFERENCE } from 'src/app/service/search-ranking.service';
import './ct-search-preferences-modal.scss';
const { Module } = Contena;

defineProps({});
const emit = defineEmits(['modal-close']);

import { ref, computed, inject, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const searchPreferencesService = inject('searchPreferencesService');
const searchRankingService = inject('searchRankingService');
const userConfigService = inject('userConfigService');

const isLoading = ref(false);
const searchPreferences = ref([]);
const userSearchPreferences = ref(null);

const defaultSearchPreferences = computed(() => {
    const defaultSearchPreferences = searchPreferencesService.getDefaultSearchPreferences();

    if (userSearchPreferences.value === null) {
        return defaultSearchPreferences;
    }

    return defaultSearchPreferences.reduce((accumulator, currentValue) => {
        const value = userSearchPreferences.value.find((item) => {
            return Object.keys(item)[0] === Object.keys(currentValue)[0];
        });

        accumulator.push(value || currentValue);

        return accumulator;
    }, []);
});
const searchPreferencesColumns = computed(() => {
    return [
        {
            property: 'active',
            label: t('global.ct-search-preferences-modal.columnActive'),
            sortable: false,
            width: '100px',
            align: 'center',
        },
        {
            property: 'moduleName',
            label: t('global.ct-search-preferences-modal.columnModuleName'),
            sortable: false,
        },
    ];
});

const createdComponent = () => {
    void getDataSource();
};
const mountedComponent = () => {
    addEventListeners();
};
const beforeDestroyComponent = () => {
    removeEventListeners();
};
async function getDataSource() {
    isLoading.value = true;
    try {
        userSearchPreferences.value = await searchPreferencesService.getUserSearchPreferences();
        searchPreferences.value = searchPreferencesService.processSearchPreferences(defaultSearchPreferences.value);
    } catch (error) {
        createNotificationError({
            message: error.message,
        });
        searchPreferences.value = [];
        userSearchPreferences.value = null;
    } finally {
        isLoading.value = false;
    }
}
function addEventListeners() {
    document.getElementById('ct-search-preferences-modal-link')?.addEventListener('click', onOpenSearchSettings);
}
function removeEventListeners() {
    document.getElementById('ct-search-preferences-modal-link')?.removeEventListener('click', onOpenSearchSettings);
}
const getModuleName = (entityName) => {
    const module = Module.getModuleByEntityName(entityName);

    return t(module?.manifest.title);
};
const onChangeSearchPreference = (searchPreference) => {
    if (searchPreference._searchable && searchPreference.fields.every((field) => !field._searchable)) {
        searchPreference.fields.forEach((field) => {
            field._searchable = true;
        });
    }
};
const onClose = () => {
    emit('modal-close');
};
function onOpenSearchSettings() {
    emit('modal-close');
    void nextTick(() => {
        void router.push({
            name: 'ct.profile.index.searchPreferences',
        });
    });
}
const onCancel = () => {
    emit('modal-close');
};
const onSave = () => {
    userSearchPreferences.value = userSearchPreferences.value ?? searchPreferencesService.createUserSearchPreferences();
    userSearchPreferences.value.value = searchPreferences.value.map(({ entityName, _searchable, fields }) => {
        return {
            [entityName]: {
                _searchable,
                ...searchPreferencesService.processSearchPreferencesFields(fields),
            },
        };
    });

    searchRankingService.clearCacheUserSearchConfiguration();

    isLoading.value = true;
    return userConfigService
        .upsert({
            [KEY_USER_SEARCH_PREFERENCE]: userSearchPreferences.value.value,
        })
        .then(() => {
            isLoading.value = false;
            emit('modal-close');
            Contena.Utils.EventBus.emit('ct-search-preferences-modal-close');
        })
        .catch((error) => {
            isLoading.value = false;
            createNotificationError({ message: error.message });
        });
};

createdComponent();

onMounted(() => {
    mountedComponent();
});
onBeforeUnmount(() => {
    beforeDestroyComponent();
});

swDefinePublic({
    searchPreferencesService,
    searchRankingService,
    userConfigService,
    isLoading,
    searchPreferences,
    userSearchPreferences,
    defaultSearchPreferences,
    searchPreferencesColumns,
    createdComponent,
    mountedComponent,
    beforeDestroyComponent,
    getDataSource,
    addEventListeners,
    removeEventListeners,
    getModuleName,
    onChangeSearchPreference,
    onClose,
    onOpenSearchSettings,
    onCancel,
    onSave,
});

defineExpose({
    searchPreferencesService,
    searchRankingService,
    userConfigService,
    isLoading,
    searchPreferences,
    userSearchPreferences,
    defaultSearchPreferences,
    searchPreferencesColumns,
    createdComponent,
    mountedComponent,
    beforeDestroyComponent,
    getDataSource,
    addEventListeners,
    removeEventListeners,
    getModuleName,
    onChangeSearchPreference,
    onClose,
    onOpenSearchSettings,
    onCancel,
    onSave,
});
</script>
