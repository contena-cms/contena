<template>
    <ct-block name="ct_settings_member_group_list">
        <ct-page class="ct-settings-member-group-list">
            <template #search-bar>
                <ct-block name="ct_settings_member_group_list_search">
                    <mt-search :model-value="term" @change="onSearch" />
                </ct-block>
            </template>
            <template #smart-bar-header>
                <ct-block name="ct_settings_member_group_list_header">
                    <h2>{{ t('ct-settings-member-group.list.title') }} ({{ total }})</h2>
                </ct-block>
            </template>
            <template #smart-bar-actions>
                <ct-block name="ct_settings_member_group_list_actions">
                    <mt-button variant="primary" :disabled="!canCreate || undefined" @click="onCreate">
                        {{ t('ct-settings-member-group.list.buttonAdd') }}
                    </mt-button>
                </ct-block>
            </template>
            <template #content>
                <ct-block name="ct_settings_member_group_list_content">
                    <mt-data-table
                        :caption="t('ct-settings-member-group.list.title')"
                        :data-source="memberGroups"
                        :columns="columns"
                        :is-loading="isLoading"
                        :pagination-total-items="total"
                        :current-page="page"
                        :pagination-limit="limit"
                        :sort-by="sortBy"
                        :sort-direction="sortDirection"
                        :disable-edit="true"
                        :disable-delete="!canDelete"
                        :additional-context-buttons="additionalContextButtons"
                        @pagination-current-page-change="onPageChange"
                        @pagination-limit-change="onLimitChange"
                        @sort-change="onSort"
                        @item-delete="onItemDelete"
                        @context-select="onContextSelect"
                    >
                        <template #column-name="{ data }">
                            <router-link :to="{ name: 'ct.settings.member.group.detail', params: { id: data.id } }">
                                {{ data.translated?.name || data.name }}
                            </router-link>
                        </template>
                        <template #column-registrationActive="{ data }">
                            <mt-badge :variant="data.registrationActive ? 'positive' : 'neutral'">
                                {{ data.registrationActive ? t('global.default.yes') : t('global.default.no') }}
                            </mt-badge>
                        </template>
                    </mt-data-table>
                    <mt-empty-state
                        v-if="!isLoading && memberGroups.length === 0"
                        icon="regular-user-group"
                        :headline="t('ct-settings-member-group.list.emptyState')"
                    />
                </ct-block>
            </template>
        </ct-page>

        <mt-modal-root v-if="memberGroupToDelete" :is-open="true" @change="onDeleteModalChange">
            <mt-modal :title="t('global.default.warning')" width="s">
                {{ memberGroupToDelete.translated?.name || memberGroupToDelete.name }}
                <template #footer>
                    <mt-button variant="secondary" @click="memberGroupToDelete = null">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="critical" :is-loading="isDeleting" @click="deleteMemberGroup">
                        {{ t('global.default.delete') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-settings-member-group-list.scss';

type SortDirection = 'ASC' | 'DESC';
type Column = { property: string; label: string; position: number; renderer: 'text'; sortable?: boolean; width?: number };
defineProps({});
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) throw new Error('The MemberGroup list services are unavailable.');
const repository = repositoryFactory.create('member_group');
const memberGroups = ref<Entity<'member_group'>[]>([]);
const isLoading = ref(false);
const isDeleting = ref(false);
const page = ref(1);
const limit = ref(25);
const total = ref(0);
const term = ref('');
const sortBy = ref('name');
const sortDirection = ref<SortDirection>('ASC');
const memberGroupToDelete = ref<Entity<'member_group'> | null>(null);
const canCreate = computed(() => acl.can('member_groups.creator'));
const canDelete = computed(() => acl.can('member_groups.deleter'));
const columns: Column[] = [
    {
        property: 'name',
        label: t('ct-settings-member-group.list.columnName'),
        position: 100,
        renderer: 'text',
        width: 360,
    },
    {
        property: 'registrationActive',
        label: t('ct-settings-member-group.list.columnRegistration'),
        position: 200,
        renderer: 'text',
        width: 180,
    },
];
const additionalContextButtons = computed(() => {
    const buttons = [];

    if (acl.can('member_groups.editor')) {
        buttons.push({ key: 'edit', label: t('global.default.edit') });
    }

    return buttons;
});
const loadMemberGroups = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const criteria = new Contena.Data.Criteria(page.value, limit.value);
        criteria.addAssociation('members');
        criteria.addAssociation('channels');
        criteria.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));
        if (term.value) criteria.setTerm(term.value);
        const result = await repository.search(criteria, Contena.Context.api);
        memberGroups.value = Array.from(result);
        total.value = result.total;
    } finally {
        isLoading.value = false;
    }
};
const onSearch = (value: string): void => {
    term.value = value;
    page.value = 1;
    void loadMemberGroups();
};
const onPageChange = (value: number): void => {
    page.value = value;
    void loadMemberGroups();
};
const onLimitChange = (value: number): void => {
    limit.value = value;
    page.value = 1;
    void loadMemberGroups();
};
const onSort = ({ sortBy: property, sortDirection: direction }: { sortBy: string; sortDirection: SortDirection }): void => {
    sortBy.value = property;
    sortDirection.value = direction;
    void loadMemberGroups();
};
const onCreate = (): void => void router.push({ name: 'ct.settings.member.group.create' });
const onEdit = (id: string): void => void router.push({ name: 'ct.settings.member.group.detail', params: { id } });
const onItemDelete = (item: Entity<'member_group'>): void => {
    memberGroupToDelete.value = item;
};
const onContextSelect = ({ key, data }: { key: string; data: Entity<'member_group'> }): void => {
    if (key === 'edit') onEdit(data.id);
};
const onDeleteModalChange = (open: boolean): void => {
    if (!open) memberGroupToDelete.value = null;
};
const deleteMemberGroup = async (): Promise<void> => {
    if (!memberGroupToDelete.value) return;
    if ((memberGroupToDelete.value.members?.length ?? 0) > 0 || (memberGroupToDelete.value.channels?.length ?? 0) > 0) {
        createNotificationError({ message: t('ct-settings-member-group.list.deleteError') });
        memberGroupToDelete.value = null;
        return;
    }
    isDeleting.value = true;
    try {
        await repository.delete(memberGroupToDelete.value.id, Contena.Context.api);
        memberGroupToDelete.value = null;
        await loadMemberGroups();
    } catch {
        createNotificationError({ message: t('ct-settings-member-group.list.deleteError') });
    } finally {
        isDeleting.value = false;
    }
};
void loadMemberGroups();

ctDefinePublic({
    memberGroups,
    isLoading,
    isDeleting,
    page,
    limit,
    total,
    term,
    sortBy,
    sortDirection,
    columns,
    additionalContextButtons,
    memberGroupToDelete,
    canCreate,
    canDelete,
    loadMemberGroups,
    onSearch,
    onPageChange,
    onLimitChange,
    onSort,
    onCreate,
    onEdit,
    onItemDelete,
    onContextSelect,
    onDeleteModalChange,
    deleteMemberGroup,
});

defineExpose({
    memberGroups,
    isLoading,
    isDeleting,
    page,
    limit,
    total,
    term,
    sortBy,
    sortDirection,
    columns,
    additionalContextButtons,
    memberGroupToDelete,
    canCreate,
    canDelete,
    loadMemberGroups,
    onSearch,
    onPageChange,
    onLimitChange,
    onSort,
    onCreate,
    onEdit,
    onItemDelete,
    onContextSelect,
    onDeleteModalChange,
    deleteMemberGroup,
});
</script>
