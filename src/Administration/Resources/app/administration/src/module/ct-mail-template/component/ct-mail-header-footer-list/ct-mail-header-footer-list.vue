<template>
    <ct-block name="sw_mail_header_footer_list_grid">
        <mt-card
            position-identifier="ct-mail-header-footer-list"
            :title="$t('ct-mail-header-footer.list.textMailHeaderFooterOverview')"
            :is-loading="isLoading"
        >
            <ct-block name="sw_mail_header_footer_list_grid_empty_state">
                <ct-empty-state
                    v-if="!isLoading && !showListing"
                    :title="$t('ct-mail-header-footer.list.emptyStateTitle')"
                    :subline="$t('ct-mail-header-footer.list.emptyStateSubTitle')"
                    :absolute="false"
                >
                    <template #icon>
                        <img
                            :src="
                                assetFilter(
                                    '/administration/administration/static/img/empty-states/settings-empty-state.svg',
                                )
                            "
                            :alt="$t('ct-mail-header-footer.list.emptyStateTitle')"
                        />
                    </template>
                </ct-empty-state>
            </ct-block>

            <template #grid>
                <ct-entity-listing
                    v-if="isLoading || showListing"
                    id="mailHeaderFooterGrid"
                    identifier="ct-mail-header-footer-list"
                    detail-route="ct.mail.template.detail_head_foot"
                    :data-source="items"
                    :columns="columns"
                    :repository="repository"
                    :is-loading="isLoading"
                    :disable-data-fetching="true"
                    :show-selection="acl.can('mail_templates.deleter') || undefined"
                    :full-page="false"
                    :allow-view="acl.can('mail_templates.viewer')"
                    :allow-edit="acl.can('mail_templates.editor')"
                    :allow-delete="acl.can('mail_templates.deleter')"
                    :skeleton-item-amount="skeletonItemAmount"
                    @page-change="onPageChange"
                    @update-records="updateRecords"
                >
                    <template #more-actions="{ item }">
                        <ct-context-menu-item
                            class="ct-mail-header-footer-list-grid__duplicate-action"
                            :disabled="!acl.can('mail_templates.creator') || undefined"
                            @click="onDuplicate(item.id)"
                        >
                            {{ $t('global.default.duplicate') }}
                        </ct-context-menu-item>
                    </template>
                </ct-entity-listing>
            </template>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* global EntityCollection */
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

defineProps({});
const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { Criteria } = Contena.Data;

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) {
    throw new Error('Required Administration services are unavailable.');
}

const repository = repositoryFactory.create('mail_header_footer');
const items = ref<EntityCollection<'mail_header_footer'> | null>(null);
const isLoading = ref(false);
const page = ref(1);
const limit = ref(25);
const assetFilter = Contena.Filter.getByName('asset');
const skeletonItemAmount = computed(() => (items.value?.length ? items.value.length : 3));
const showListing = computed(() => Boolean(items.value?.length));
const columns = computed(() => [
    {
        property: 'name',
        dataIndex: 'name',
        label: t('ct-mail-header-footer.list.columnName'),
        routerLink: 'ct.mail.template.detail_head_foot',
        primary: true,
        allowResize: true,
        width: '240px',
    },
    {
        property: 'description',
        dataIndex: 'description',
        label: t('ct-mail-header-footer.list.columnDescription'),
        allowResize: true,
    },
]);
const criteria = computed(() => {
    const query = new Criteria(page.value, limit.value);
    // Criteria is a local mutable query object, not component state.
    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
    query.addSorting(Criteria.sort('name', 'ASC'));
    if (typeof route.query.term === 'string' && route.query.term) {
        query.setTerm(route.query.term);
    }
    query.setTitle('mail-header-footer-list');
    return query;
});

async function getList(): Promise<void> {
    isLoading.value = true;
    try {
        items.value = await repository.search(criteria.value);
    } finally {
        isLoading.value = false;
    }
}

function onPageChange(event: { page: number; limit: number }): void {
    page.value = event.page;
    limit.value = event.limit;
    void getList();
}

async function onEdit(id: string): Promise<void> {
    await router.push({ name: 'ct.mail.template.detail_head_foot', params: { id } });
}

async function onDuplicate(id: string): Promise<void> {
    const duplicate = await repository.clone(id);
    await router.push({ name: 'ct.mail.template.detail_head_foot', params: { id: duplicate.id } });
}

function updateRecords(result: EntityCollection<'mail_header_footer'>): void {
    items.value = result;
}

void getList();

swDefinePublic({
    acl,
    repository,
    items,
    isLoading,
    page,
    limit,
    columns,
    criteria,
    skeletonItemAmount,
    showListing,
    assetFilter,
    getList,
    onPageChange,
    onEdit,
    onDuplicate,
    updateRecords,
});

defineExpose({
    acl,
    repository,
    items,
    isLoading,
    page,
    limit,
    columns,
    criteria,
    skeletonItemAmount,
    showListing,
    assetFilter,
    getList,
    onPageChange,
    onEdit,
    onDuplicate,
    updateRecords,
});
</script>
