<template>
    <ct-block name="sw_experience_studio_list">
        <ct-page class="ct-experience-studio-list">
            <template #search-bar>
                <ct-block name="sw_experience_studio_list_search_bar">
                    <mt-search :model-value="term" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <h2>{{ $t('ct-experience-studio.general.moduleTitle') }}</h2>
            </template>

            <template #smart-bar-actions>
                <mt-button
                    v-tooltip="{
                        message: $t('ct-privileges.tooltip.warning'),
                        disabled: allowCreate,
                        showOnDisabledElements: true,
                    }"
                    variant="primary"
                    :disabled="!allowCreate || undefined"
                    @click="onCreateNewLayout"
                >
                    {{ $t('ct-experience-studio.general.createNewLayout') }}
                </mt-button>
            </template>

            <template #content>
                <ct-block name="sw_experience_studio_list_content">
                    <div class="ct-experience-studio-list__content">
                        <mt-data-table
                            v-if="layouts && layouts.length > 0"
                            :caption="$t('ct-experience-studio.general.moduleTitle')"
                            :columns="columnConfig"
                            :data-source="layouts"
                            :is-loading="isLoading"
                            :current-page="page"
                            :pagination-limit="limit"
                            :pagination-total-items="total"
                            :sort-by="sortBy"
                            :sort-direction="sortDirection"
                            layout="full"
                            :show-stripes="false"
                            :show-outlines="true"
                            disable-search
                            disable-edit
                            disable-delete
                            @pagination-current-page-change="onTablePageChange"
                            @pagination-limit-change="onTableLimitChange"
                            @sort-change="onTableSortChange"
                            @open-details="onOpenDetails"
                        />

                        <mt-empty-state
                            v-else-if="!isLoading"
                            icon="regular-layout"
                            :headline="$t('ct-experience-studio.list.emptyStateTitle')"
                            :description="$t('ct-experience-studio.list.emptyStateSubline')"
                        />

                        <mt-loader v-else />
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-experience-studio-list.scss';
const { Criteria } = Contena.Data;

defineProps({});

import { ref, computed, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useListing } from 'src/app/composables/use-listing';
import { usePageTitle } from 'src/app/composables/use-page-title';

const router = useRouter();
const { t } = useI18n();
const { page, limit, total, term, onPageChange, onSearch, onSort, initializeListing } = useListing();

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const layouts = ref(null);
const isLoading = ref(false);
const sortBy = ref('createdAt');
const sortDirection = ref('DESC');

const layoutRepository = computed(() => {
    return repositoryFactory.create('content_layout');
});
const columnConfig = computed(() => {
    return [
        {
            property: 'name',
            label: t('ct-experience-studio.list.columnName'),
            renderer: 'text',
            position: 100,
            sortable: true,
            clickable: true,
            allowResize: true,
        },
        {
            property: 'version',
            label: t('ct-experience-studio.list.columnVersion'),
            renderer: 'text',
            position: 200,
            sortable: true,
            allowResize: true,
        },
        {
            property: 'createdAt',
            label: t('ct-experience-studio.list.columnCreatedAt'),
            renderer: 'text',
            position: 300,
            sortable: true,
            allowResize: true,
        },
        {
            property: 'updatedAt',
            label: t('ct-experience-studio.list.columnUpdatedAt'),
            renderer: 'text',
            position: 400,
            sortable: true,
            allowResize: true,
        },
    ];
});
const createCriteria = () => {
    const criteria = new Criteria(page.value, limit.value);

    if (term.value) {
        criteria.setTerm(term.value);
    }

    criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value));

    return criteria;
};
const criteria = computed(createCriteria);
const allowCreate = computed(() => {
    return acl.can('experience_studio.creator');
});

const getList = async () => {
    isLoading.value = true;

    layouts.value = await layoutRepository.value.search(criteria.value, Contena.Context.api);
    total.value = layouts.value.total ?? 0;

    isLoading.value = false;
};
const onCreateNewLayout = () => {
    void router.push({ name: 'ct.experience.studio.create' });
};
const onChangeLanguage = () => {
    void getList();
};
const onTablePageChange = (nextPage: number) => {
    onPageChange({ page: nextPage, limit: limit.value });
};
const onTableLimitChange = (nextLimit: number) => {
    onPageChange({ page: 1, limit: nextLimit });
};
const onTableSortChange = (property: string, direction: 'ASC' | 'DESC') => {
    onSort({ sortBy: property, sortDirection: direction });
};
const onOpenDetails = (row: { id: string }) => {
    void router.push({ name: 'ct.experience.studio.detail', params: { id: row.id } });
};

initializeListing({
    getList,
    sortBy,
    sortDirection,
});

void getList();

swDefinePublic({
    repositoryFactory,
    acl,
    layouts,
    isLoading,
    sortBy,
    sortDirection,
    layoutRepository,
    columnConfig,
    criteria,
    allowCreate,
    getList,
    onCreateNewLayout,
    onChangeLanguage,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
    onOpenDetails,
});
usePageTitle();

defineExpose({
    repositoryFactory,
    acl,
    layouts,
    isLoading,
    sortBy,
    sortDirection,
    layoutRepository,
    columnConfig,
    criteria,
    allowCreate,
    getList,
    onCreateNewLayout,
    onChangeLanguage,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
    onOpenDetails,
});
</script>
