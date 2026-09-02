<template>
    <ct-block name="ct_settings_list">
        <ct-block name="ct_settings_country_index">
            <ct-page class="ct-settings-country-list">
                <template #search-bar>
                    <ct-block name="ct_settings_country_list_search_bar">
                        <mt-search
                            :placeholder="$t('ct-settings-country.general.placeholderSearchBar')"
                            :model-value="term"
                            @change="onSearch"
                        />
                    </ct-block>
                </template>

                <template #smart-bar-header>
                    <ct-block name="ct_settings_country_list_smart_bar_header">
                        <ct-block name="ct_settings_country_list_smart_bar_header_title">
                            <h2>
                                <ct-block name="ct_settings_country_list_smart_bar_header_title_text">
                                    {{ $t('ct-settings.index.title') }}
                                    <mt-icon name="regular-chevron-right-xs" size="12px" />
                                    {{ $t('ct-settings-country.list.textHeadline') }}
                                </ct-block>

                                <ct-block name="ct_settings_country_list_smart_bar_header_amount">
                                    <span v-if="!isLoading" class="ct-page__smart-bar-amount"> ({{ total }}) </span>
                                </ct-block>
                            </h2>
                        </ct-block>
                    </ct-block>
                </template>

                <template #smart-bar-actions>
                    <ct-block name="ct_settings_country_list_smart_bar_actions">
                        <ct-block name="ct_settings_country_list_smart_bar_actions_add">
                            <mt-button
                                v-tooltip.bottom="{
                                    message: $t('ct-privileges.tooltip.warning'),
                                    disabled: acl.can('country.creator'),
                                    showOnDisabledElements: true,
                                }"
                                class="ct-settings-country-list__button-create"
                                variant="primary"
                                :disabled="!acl.can('country.creator') || undefined"
                                size="default"
                                @click="$router.push({ name: 'ct.settings.country.create' })"
                            >
                                {{ $t('global.default.add') }}
                            </mt-button>
                        </ct-block>
                    </ct-block>
                </template>

                <template #language-switch>
                    <ct-block name="ct_settings_country_list_language_switch">
                        <ct-language-switch @on-change="onChangeLanguage" />
                    </ct-block>
                </template>

                <template #content>
                    <ct-block name="ct_settings_country_list_content">
                        <mt-data-table
                            layout="full"
                            :caption="$t('ct-settings-country.list.textHeadline')"
                            :data-source="country"
                            :columns="getCountryColumns()"
                            :is-loading="isLoading"
                            :pagination-total-items="total"
                            :current-page="page"
                            :pagination-limit="limit"
                            disable-search
                            disable-edit
                            :disable-delete="!acl.can('country.deleter')"
                            :additional-context-buttons="additionalContextButtons"
                            @reload="getList"
                            @pagination-current-page-change="onPageChange"
                            @pagination-limit-change="onLimitChange"
                            @sort-change="onSort"
                            @item-delete="onDelete"
                            @context-select="onContextSelect"
                        >
                            <template #column-name="{ data }">
                                <router-link :to="{ name: 'ct.settings.country.detail', params: { id: data.id } }">
                                    {{ data.name }}
                                </router-link>
                            </template>

                            <template #column-active="{ data }">
                                <mt-icon
                                    :name="data.active ? 'regular-checkmark-xs' : 'regular-times-s'"
                                    size="16px"
                                    :class="data.active ? 'is--active' : 'is--inactive'"
                                />
                            </template>

                            <template #empty-state>
                                <mt-empty-state
                                    icon="regular-globe-stand"
                                    :headline="$t('ct-settings-country.list.textHeadline')"
                                />
                            </template>
                        </mt-data-table>
                    </ct-block>
                </template>
            </ct-page>
        </ct-block>
    </ct-block>

    <mt-modal-root v-if="showDeleteModal" :is-open="true" @change="onCloseDeleteModal">
        <mt-modal :title="$t('global.default.warning')" width="s">
            <p class="ct-settings-country-list__confirm-delete-text">
                {{ $t('ct-settings-country.list.textDeleteConfirm', { name: itemToDelete?.name }, 0) }}
            </p>
            <template #footer>
                <mt-button size="small" variant="secondary" @click="onCloseDeleteModal">
                    {{ $t('global.default.cancel') }}
                </mt-button>
                <mt-button variant="critical" size="small" @click="onConfirmDelete">
                    {{ $t('global.default.delete') }}
                </mt-button>
            </template>
        </mt-modal>
    </mt-modal-root>
</template>

<script setup>
import './ct-settings-country-list.scss';
const { Criteria } = Contena.Data;

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

defineProps({});

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const router = useRouter();
const { page, limit, total, term, onSearch, initializeListing } = useListing();

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const entityName = ref('country');
const country = ref([]);
const sortBy = ref('country.name');
const isLoading = ref(false);
const sortDirection = ref('ASC');
const naturalSorting = ref(true);
const showDeleteModal = ref(false);
const itemToDelete = ref(null);

const countryRepository = computed(() => {
    return repositoryFactory.create('country');
});
const detailPageLinkText = computed(() => {
    if (!acl.can('country.editor') && acl.can('country.viewer')) {
        return t('global.default.view');
    }

    return t('global.default.edit');
});

const getList = () => {
    const criteria = new Criteria(page.value, limit.value);

    isLoading.value = true;

    naturalSorting.value = sortBy.value === 'name';
    criteria.setTerm(term.value);
    criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value, naturalSorting.value));

    countryRepository.value
        .search(criteria, Contena.Context.api)
        .then((items) => {
            total.value = items.total;
            country.value = items;
            isLoading.value = false;

            return items;
        })
        .catch(() => {
            isLoading.value = false;
        });
};
const onChangeLanguage = (languageId) => {
    Contena.Store.get('context').api.languageId = languageId;
    getList();
};
const onDelete = (item) => {
    itemToDelete.value = typeof item === 'string' ? country.value.find((entry) => entry.id === item) : item;
    showDeleteModal.value = true;
};
const onCloseDeleteModal = () => {
    showDeleteModal.value = false;
    itemToDelete.value = null;
};
const onConfirmDelete = () => {
    if (!itemToDelete.value) return Promise.resolve();
    const id = itemToDelete.value.id;
    onCloseDeleteModal();

    return countryRepository.value.delete(id).then(() => {
        getList();
    });
};
const onPageChange = (nextPage) => {
    page.value = nextPage;
    void getList();
};
const onLimitChange = (nextLimit) => {
    limit.value = nextLimit;
    page.value = 1;
    void getList();
};
const onSort = ({ sortBy: nextSortBy, sortDirection: nextSortDirection }) => {
    sortBy.value = nextSortBy;
    sortDirection.value = nextSortDirection;
    page.value = 1;
    void getList();
};
const additionalContextButtons = computed(() => {
    if (!acl.can('country.editor') && !acl.can('country.viewer')) return [];
    return [{ key: 'edit', label: detailPageLinkText.value }];
});
const onContextSelect = ({ key, data }) => {
    if (key !== 'edit') return;
    void router.push({
        name: 'ct.settings.country.detail',
        params: { id: data.id },
        query: acl.can('country.editor') ? { edit: 'edit' } : undefined,
    });
};
const getCountryColumns = () => {
    return [
        {
            property: 'name',
            dataIndex: 'name',
            label: t('ct-settings-country.list.columnName'),
            position: 100,
            renderer: 'text',
        },
        {
            property: 'position',
            label: t('ct-settings-country.list.columnPosition'),
            position: 200,
            renderer: 'text',
        },
        {
            property: 'iso',
            label: t('ct-settings-country.list.columnIso'),
            position: 300,
            renderer: 'text',
        },
        {
            property: 'iso3',
            label: t('ct-settings-country.list.columnIso3'),
            position: 400,
            renderer: 'text',
        },
        {
            property: 'active',
            label: t('ct-settings-country.list.columnActive'),
            position: 500,
            renderer: 'text',
        },
    ];
};

initializeListing({
    getList,
    sortBy,
    sortDirection,
    naturalSorting,
});

ctDefinePublic({
    repositoryFactory,
    acl,
    entityName,
    country,
    sortBy,
    isLoading,
    sortDirection,
    naturalSorting,
    showDeleteModal,
    countryRepository,
    detailPageLinkText,
    getList,
    onChangeLanguage,
    onDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    itemToDelete,
    additionalContextButtons,
    onPageChange,
    onLimitChange,
    onSort,
    onContextSelect,
    getCountryColumns,
});

defineExpose({
    repositoryFactory,
    acl,
    entityName,
    country,
    sortBy,
    isLoading,
    sortDirection,
    naturalSorting,
    showDeleteModal,
    countryRepository,
    detailPageLinkText,
    getList,
    onChangeLanguage,
    onDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    itemToDelete,
    additionalContextButtons,
    onPageChange,
    onLimitChange,
    onSort,
    onContextSelect,
    getCountryColumns,
});
</script>
