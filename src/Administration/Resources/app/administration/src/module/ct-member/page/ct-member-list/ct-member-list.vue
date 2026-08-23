<template>
    <ct-block name="sw_member_list">
        <ct-page class="ct-member-list">
            <template #search-bar>
                <ct-block name="sw_member_list_search">
                    <mt-search :model-value="term" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_member_list_header">
                    <h2>
                        {{ t('ct-member.list.textHeadline') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_member_list_actions">
                    <mt-button
                        v-tooltip="createTooltip"
                        variant="primary"
                        :disabled="!canCreate || undefined"
                        @click="onCreateMember"
                    >
                        {{ t('ct-member.list.buttonAddMember') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="sw_member_list_filters">
                    <div class="ct-member-list__filters">
                        <mt-entity-select
                            v-model="groupFilter"
                            entity="member_group"
                            small
                            :placeholder="t('ct-member.baseForm.placeholderMemberGroup')"
                            @update:model-value="loadMembers"
                        />
                        <mt-entity-select
                            v-model="channelFilter"
                            entity="channel"
                            small
                            :placeholder="t('ct-member.baseForm.placeholderChannel')"
                            @update:model-value="loadMembers"
                        />
                    </div>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_member_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="t('ct-member.list.textHeadline')"
                        :data-source="members"
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
                        @reload="loadMembers"
                        @pagination-current-page-change="onPageChange"
                        @pagination-limit-change="onLimitChange"
                        @sort-change="onSort"
                        @item-delete="onItemDelete"
                        @context-select="onContextSelect"
                    >
                        <template #column-name="{ data }">
                            <router-link :to="{ name: 'ct.member.detail.base', params: { id: data.id } }">
                                {{ data.name }}
                            </router-link>
                        </template>
                        <!-- eslint-disable-next-line vue/no-unused-vars -->
                        <template #column-group="{ data }">
                            {{ data.group?.translated?.name || data.group?.name || '-' }}
                        </template>
                        <!-- eslint-disable-next-line vue/no-unused-vars -->
                        <template #column-channel="{ data }">
                            {{ data.channel?.translated?.name || data.channel?.name || '-' }}
                        </template>
                        <template #column-active="{ data }">
                            <mt-badge :variant="data.active ? 'positive' : 'neutral'">
                                {{ data.active ? t('global.default.yes') : t('global.default.no') }}
                            </mt-badge>
                        </template>
                    </mt-data-table>

                    <mt-empty-state
                        v-if="!isLoading && members.length === 0"
                        icon="regular-users"
                        :headline="t('ct-member.list.emptyState')"
                    />
                </ct-block>
            </template>
        </ct-page>

        <mt-modal-root v-if="memberToDelete" :is-open="true" @change="onDeleteModalChange">
            <mt-modal :title="t('global.default.warning')" width="s">
                {{ memberToDelete.name }}
                <template #footer>
                    <mt-button variant="secondary" @click="memberToDelete = null">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="critical" :is-loading="isDeleting" @click="deleteMember">
                        {{ t('global.default.delete') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-member-list.scss';

type SortDirection = 'ASC' | 'DESC';
type Column = {
    property: string;
    label: string;
    position: number;
    renderer: 'text';
    sortable?: boolean;
    width?: number;
};

defineProps({});
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) throw new Error('The Member list services are unavailable.');
const memberRepository = repositoryFactory.create('member');
const members = ref<Entity<'member'>[]>([]);
const isLoading = ref(false);
const isDeleting = ref(false);
const page = ref(1);
const limit = ref(25);
const total = ref(0);
const term = ref('');
const sortBy = ref('createdAt');
const sortDirection = ref<SortDirection>('DESC');
const groupFilter = ref<string | null>(null);
const channelFilter = ref<string | null>(null);
const memberToDelete = ref<Entity<'member'> | null>(null);
const canCreate = computed(() => acl.can('member.creator'));
const canDelete = computed(() => acl.can('member.deleter'));
const createTooltip = computed(() => ({
    message: t('ct-privileges.tooltip.warning'),
    disabled: canCreate.value,
    showOnDisabledElements: true,
}));
const columns: Column[] = [
    {
        property: 'memberNumber',
        label: t('ct-member.list.columnMemberNumber'),
        position: 100,
        renderer: 'text',
        width: 160,
    },
    { property: 'name', label: t('ct-member.list.columnName'), position: 200, renderer: 'text', width: 220 },
    { property: 'email', label: t('ct-member.list.columnEmail'), position: 300, renderer: 'text', width: 240 },
    { property: 'group', label: t('ct-member.list.columnGroup'), position: 400, renderer: 'text', width: 180 },
    { property: 'channel', label: t('ct-member.list.columnChannel'), position: 500, renderer: 'text', width: 180 },
    { property: 'active', label: t('ct-member.list.columnActive'), position: 600, renderer: 'text', width: 110 },
];
const additionalContextButtons = computed(() => {
    const buttons = [];

    if (acl.can('member.editor')) {
        buttons.push({ key: 'edit', label: t('global.default.edit') });
    }

    return buttons;
});
const loadMembers = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const criteria = new Contena.Data.Criteria(page.value, limit.value);
        criteria.addAssociation('group');
        criteria.addAssociation('channel');
        criteria.addAssociation('requestedGroup');
        criteria.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));
        if (term.value) criteria.setTerm(term.value);
        if (groupFilter.value) criteria.addFilter(Contena.Data.Criteria.equals('groupId', groupFilter.value));
        if (channelFilter.value) criteria.addFilter(Contena.Data.Criteria.equals('channelId', channelFilter.value));
        const result = await memberRepository.search(criteria, Contena.Context.api);
        members.value = Array.from(result);
        total.value = result.total;
    } catch {
        createNotificationError({ message: t('ct-member.list.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const onSearch = (value: string): void => {
    term.value = value;
    page.value = 1;
    void loadMembers();
};
const onPageChange = (value: number): void => {
    page.value = value;
    void loadMembers();
};
const onLimitChange = (value: number): void => {
    limit.value = value;
    page.value = 1;
    void loadMembers();
};
const onSort = ({ sortBy: property, sortDirection: direction }: { sortBy: string; sortDirection: SortDirection }): void => {
    sortBy.value = property;
    sortDirection.value = direction;
    void loadMembers();
};
const onCreateMember = (): void => void router.push({ name: 'ct.member.create' });
const onOpenDetails = (id: string): void => void router.push({ name: 'ct.member.detail.base', params: { id } });
const onItemDelete = (item: Entity<'member'>): void => {
    memberToDelete.value = item;
};
const onContextSelect = ({ key, data }: { key: string; data: Entity<'member'> }): void => {
    if (key === 'edit') onOpenDetails(data.id);
};
const onDeleteModalChange = (open: boolean): void => {
    if (!open) memberToDelete.value = null;
};
const deleteMember = async (): Promise<void> => {
    if (!memberToDelete.value) return;
    isDeleting.value = true;
    try {
        await memberRepository.delete(memberToDelete.value.id, Contena.Context.api);
        memberToDelete.value = null;
        await loadMembers();
    } catch {
        createNotificationError({ message: t('ct-member.list.deleteError') });
    } finally {
        isDeleting.value = false;
    }
};

watch(
    [
        page,
        limit,
    ],
    () => undefined,
);
void loadMembers();

swDefinePublic({
    members,
    columns,
    isLoading,
    isDeleting,
    page,
    limit,
    total,
    term,
    sortBy,
    sortDirection,
    additionalContextButtons,
    groupFilter,
    channelFilter,
    memberToDelete,
    canCreate,
    canDelete,
    createTooltip,
    loadMembers,
    onSearch,
    onPageChange,
    onLimitChange,
    onSort,
    onCreateMember,
    onOpenDetails,
    onItemDelete,
    onContextSelect,
    onDeleteModalChange,
    deleteMember,
});

defineExpose({
    members,
    columns,
    isLoading,
    isDeleting,
    page,
    limit,
    total,
    term,
    sortBy,
    sortDirection,
    additionalContextButtons,
    groupFilter,
    channelFilter,
    memberToDelete,
    canCreate,
    canDelete,
    createTooltip,
    loadMembers,
    onSearch,
    onPageChange,
    onLimitChange,
    onSort,
    onCreateMember,
    onOpenDetails,
    onItemDelete,
    onContextSelect,
    onDeleteModalChange,
    deleteMember,
});
</script>
