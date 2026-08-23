<template>
    <ct-block name="sw_settings_search_searchable_content">
        <mt-card
            class="ct-settings-search-searchable-content"
            position-identifier="ct-settings-search-searchable-content"
            :title="t('ct-settings-search.generalTab.labelSearchableContent')"
        >
            <ct-block name="sw_settings_search_searchable_content_title">
                <p class="ct-settings-search-searchable-content__title">
                    {{ t('ct-settings-search.generalTab.textDescriptionSearchableContent') }}
                </p>
            </ct-block>

            <ct-block name="sw_settings_search_searchable_actions">
                <div class="ct-settings-search-searchable-content__actions">
                    <div class="ct-settings-search-searchable-content__button-group">
                        <ct-block name="sw_settings_search_searchable_add_item">
                            <mt-button
                                v-if="defaultTab === tabNames.customTab"
                                class="ct-settings-search__searchable-content-add-button"
                                size="small"
                                variant="primary"
                                :disabled="!acl.can('blog_search_config.creator') || undefined"
                                @click="onAddNewConfig"
                            >
                                {{ t('ct-settings-search.generalTab.buttonAddContent') }}
                            </mt-button>
                        </ct-block>
                        <ct-block name="sw_settings_search_searchable_reset_default">
                            <mt-button
                                v-if="defaultTab === tabNames.generalTab"
                                class="ct-settings-search__searchable-content-reset-button"
                                size="small"
                                variant="critical"
                                :disabled="isEnabledReset || !acl.can('blog_search_config.editor') || undefined"
                                @click="onResetToDefault"
                            >
                                {{ t('ct-settings-search.generalTab.buttonResetDefault') }}
                            </mt-button>
                        </ct-block>
                        <ct-block name="sw_settings_search_searchable_show_example">
                            <mt-button
                                class="ct-settings-search__searchable-content-show-example-link"
                                size="small"
                                variant="secondary"
                                @click="onShowExampleModal"
                            >
                                {{ t('ct-settings-search.generalTab.linkExample') }}
                            </mt-button>
                        </ct-block>
                    </div>

                    <mt-link :to="{ name: 'ct.settings.search.index.liveSearch' }" type="internal">
                        {{ t('ct-settings-search.liveSearchTab.linkRebuildSearchIndex') }}
                    </mt-link>
                </div>
                <ct-settings-search-example-modal v-if="showExampleModal" @modal-close="onCloseExampleModal" />
            </ct-block>

            <ct-block name="sw_settings_search_searchable_content_tabs">
                <mt-tabs
                    class="ct-settings-search-searchable-content__tabs"
                    position-identifier="ct-settings-search-searchable-content"
                    :default-item="defaultTab"
                    :items="searchableContentTabs"
                    :small="true"
                    @new-item-active="onChangeTab"
                />
                <div class="ct-settings-search-searchable-content__tab-content">
                    <ct-block name="sw_settings_search_searchable_content_general_tab_item">
                        <ct-settings-search-searchable-content-general
                            v-if="defaultTab === tabNames.generalTab"
                            :is-empty="isListEmpty"
                            :is-loading="isLoading"
                            :columns="columns"
                            :search-configs="searchConfigFields"
                            :field-configs="fieldConfigs"
                            @data-load="loadData"
                            @config-save="saveConfig"
                        />
                    </ct-block>
                    <ct-block name="sw_settings_search_searchable_content_customfields_tab_item">
                        <ct-settings-search-searchable-content-customfields
                            v-if="defaultTab === tabNames.customTab"
                            :is-empty="isListEmpty"
                            :is-loading="isLoading"
                            :columns="columns"
                            :search-configs="searchConfigFields"
                            @data-load="loadData"
                            @config-add="onAddNewConfig"
                            @config-save="saveConfig"
                            @config-delete="deleteConfig"
                        />
                    </ct-block>
                </div>
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, ref, watch, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { useNotification } from 'src/app/composables/use-notification';

import { SEARCH_CONFIG_FIELD_SNIPPETS, type SearchConfigField } from '../../constant/search-config-fields.constant';
import './ct-settings-search-searchable-content.scss';

type SearchConfig = Entity<'blog_search_config_field'>;
type FieldConfig = {
    value: SearchConfigField;
    label: string;
    defaultConfigs: Pick<SearchConfig, 'searchable' | 'ranking' | 'tokenize'>;
};
type Column = {
    property: string;
    label: string;
    renderer: 'text' | 'number';
    position: number;
    sortable: boolean;
    width?: number;
};

const props = defineProps({
    searchConfigId: { type: String, required: true },
    blogSearchConfigs: { type: Object as PropType<Entity<'blog_search_config'> | null>, default: null },
});
const emit = defineEmits<{ 'edit-change': [changed: boolean] }>();
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const acl = inject<AclService>('acl');
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!acl || !repositoryFactory) throw new Error('Search settings dependencies are unavailable.');

const showExampleModal = ref(false);
const defaultTab = ref('general');
const tabNames = { generalTab: 'general', customTab: 'customfields' } as const;
const isLoading = ref(false);
const isEnabledReset = ref(true);
const searchConfigFields = ref<SearchConfig[]>([]);
const defaults: Array<Omit<FieldConfig, 'label'>> = [
    { value: 'name', defaultConfigs: { searchable: true, ranking: 500, tokenize: true } },
    { value: 'description', defaultConfigs: { searchable: true, ranking: 80, tokenize: true } },
    { value: 'descriptionTeaser', defaultConfigs: { searchable: false, ranking: 0, tokenize: false } },
    { value: 'keywords', defaultConfigs: { searchable: true, ranking: 250, tokenize: true } },
    { value: 'customSearchKeywords', defaultConfigs: { searchable: true, ranking: 500, tokenize: true } },
    { value: 'categories.name', defaultConfigs: { searchable: false, ranking: 0, tokenize: false } },
    { value: 'categories.customFields', defaultConfigs: { searchable: false, ranking: 0, tokenize: false } },
    { value: 'tags.name', defaultConfigs: { searchable: false, ranking: 0, tokenize: false } },
    { value: 'metaTitle', defaultConfigs: { searchable: true, ranking: 80, tokenize: true } },
    { value: 'metaDescription', defaultConfigs: { searchable: true, ranking: 80, tokenize: true } },
];
const fieldConfigs = computed<FieldConfig[]>(() =>
    defaults.map((fieldConfig) => ({
        ...fieldConfig,
        label: t(`ct-settings-search.generalTab.configFields.${SEARCH_CONFIG_FIELD_SNIPPETS[fieldConfig.value]}`),
    })),
);
const repository = repositoryFactory.create('blog_search_config_field');
const criteria = computed(() => {
    const searchCriteria = new Contena.Data.Criteria(1, 25);
    searchCriteria.addFilter(Contena.Data.Criteria.equals('searchConfigId', props.searchConfigId || null));
    searchCriteria.addSorting(Contena.Data.Criteria.sort('field', 'DESC'));
    searchCriteria.addFilter(
        defaultTab.value === tabNames.generalTab
            ? Contena.Data.Criteria.equals('customFieldId', null)
            : Contena.Data.Criteria.not('AND', [Contena.Data.Criteria.equals('customFieldId', null)]),
    );
    return searchCriteria;
});
const isListEmpty = computed(() => searchConfigFields.value.length === 0);
const columns = computed<Column[]>(() => [
    {
        property: 'field',
        label: t('ct-settings-search.generalTab.list.columnContent'),
        renderer: 'text',
        position: 100,
        sortable: true,
        width: 250,
    },
    {
        property: 'searchable',
        label: t('ct-settings-search.generalTab.list.columnSearchable'),
        renderer: 'text',
        position: 200,
        sortable: true,
    },
    {
        property: 'ranking',
        label: t('ct-settings-search.generalTab.list.columnRankingPoints'),
        renderer: 'number',
        position: 300,
        sortable: true,
    },
    {
        property: 'tokenize',
        label: t('ct-settings-search.generalTab.list.columnSplitKeywords'),
        renderer: 'text',
        position: 400,
        sortable: true,
    },
    {
        property: 'actions',
        label: t('global.default.actions'),
        renderer: 'text',
        position: 500,
        sortable: false,
        width: 112,
    },
]);
const searchableContentTabs = computed(() => [
    { label: t('ct-settings-search.generalTab.labelGeneralTab'), name: tabNames.generalTab },
    { label: t('ct-settings-search.generalTab.labelCustomFieldsTab'), name: tabNames.customTab },
]);
const onShowExampleModal = (): void => {
    showExampleModal.value = true;
};
const onCloseExampleModal = (): void => {
    showExampleModal.value = false;
};
const createNewConfigItem = (): SearchConfig => {
    const item = repository.create(Contena.Context.api);
    item.searchConfigId = props.searchConfigId;
    item.searchable = false;
    item.ranking = 0;
    item.field = '';
    item.tokenize = false;
    item.useExactSubfield = false;
    return item;
};
const onAddNewConfig = (): void => {
    searchConfigFields.value.unshift(createNewConfigItem());
    emit('edit-change', true);
};
const getConfigFieldDefault = (fieldName: string): FieldConfig['defaultConfigs'] =>
    fieldConfigs.value.find(({ value }) => value === fieldName)?.defaultConfigs ?? {
        ranking: 0,
        searchable: false,
        tokenize: false,
    };
const saveConfig = async (): Promise<void> => {
    isLoading.value = true;
    try {
        await repository.saveAll(searchConfigFields.value, Contena.Context.api);
        createNotificationSuccess({ message: t('ct-settings-search.notification.saveSuccess') });
        emit('edit-change', false);
    } catch {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
    } finally {
        isLoading.value = false;
        await getBlogSearchFieldsList();
    }
};
const onResetToDefault = (): void => {
    const general = defaultTab.value === tabNames.generalTab;
    searchConfigFields.value.forEach((item) => {
        const defaultConfig = general
            ? getConfigFieldDefault(item.field)
            : { ranking: 0, searchable: false, tokenize: false };
        Object.assign(item, defaultConfig);
    });
    void saveConfig();
};
const getBlogSearchFieldsList = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const items = await repository.search(criteria.value, Contena.Context.api);
        isEnabledReset.value = !items.total;
        searchConfigFields.value = [...items];
    } catch {
        createNotificationError({ message: t('ct-settings-search.notification.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const loadData = (): void => {
    void getBlogSearchFieldsList();
};
const onChangeTab = (tab: string): void => {
    defaultTab.value = tab;
    loadData();
};
const deleteConfig = async (id: string): Promise<void> => {
    if (!id) return;
    isLoading.value = true;
    try {
        await repository.delete(id, Contena.Context.api);
        createNotificationSuccess({ message: t('ct-settings-search.notification.saveSuccess') });
    } catch {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
    } finally {
        isLoading.value = false;
        await getBlogSearchFieldsList();
    }
};

watch(() => props.searchConfigId, loadData);
watch(() => props.blogSearchConfigs, loadData);
loadData();

swDefinePublic({
    showExampleModal,
    defaultTab,
    tabNames,
    isLoading,
    isEnabledReset,
    searchConfigFields,
    fieldConfigs,
    repository,
    criteria,
    isListEmpty,
    columns,
    searchableContentTabs,
    onShowExampleModal,
    onCloseExampleModal,
    onAddNewConfig,
    createNewConfigItem,
    getConfigFieldDefault,
    onResetToDefault,
    onChangeTab,
    loadData,
    getBlogSearchFieldsList,
    saveConfig,
    deleteConfig,
});

defineExpose({
    showExampleModal,
    defaultTab,
    tabNames,
    isLoading,
    isEnabledReset,
    searchConfigFields,
    fieldConfigs,
    repository,
    criteria,
    isListEmpty,
    columns,
    searchableContentTabs,
    onShowExampleModal,
    onCloseExampleModal,
    onAddNewConfig,
    createNewConfigItem,
    getConfigFieldDefault,
    onResetToDefault,
    onChangeTab,
    loadData,
    getBlogSearchFieldsList,
    saveConfig,
    deleteConfig,
});
</script>
