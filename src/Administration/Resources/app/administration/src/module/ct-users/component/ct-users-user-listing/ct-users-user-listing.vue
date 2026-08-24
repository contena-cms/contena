<template>
    <ct-block name="sw_users_user_list">
        <div class="ct-users-user-listing">
            <ct-block name="sw_users_user_list_content">
                <a-table
                    class="ct-users-user-listing__table"
                    :columns="userColumns"
                    :data-source="user ?? []"
                    :loading="isLoading"
                    :pagination="tablePagination"
                    :row-selection="rowSelection"
                    row-key="id"
                    size="middle"
                    :scroll="{ x: 1040 }"
                    @change="onTableChange"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'user'">
                            <ct-block name="sw_users_user_list_column_username">
                                <div class="ct-users-user-listing__identity">
                                    <a-avatar :src="record.avatarMedia?.url">
                                        {{ getUserAvatarText(record) }}
                                    </a-avatar>
                                    <router-link
                                        class="ct-users-user-listing__username"
                                        :to="{ name: userDetailRouterLink, params: { id: record.id } }"
                                    >
                                        <strong>{{ getUserDisplayName(record) }}</strong>
                                        <span>@{{ record.username }}</span>
                                    </router-link>
                                </div>
                            </ct-block>
                        </template>

                        <template v-else-if="column.key === 'contact'">
                            <div class="ct-users-user-listing__contact">
                                <span>{{ record.phoneNumber || '-' }}</span>
                                <span>{{ record.email || '-' }}</span>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'aclRoles'">
                            <ct-block name="sw_users_user_list_column_username_acl_roles">
                                <a-space v-if="record.aclRoles?.length" :size="[4, 4]" wrap>
                                    <a-tag v-for="role in record.aclRoles" :key="role.id ?? role.name">
                                        {{ role.name }}
                                    </a-tag>
                                </a-space>
                                <span v-else class="ct-users-user-listing__empty">-</span>
                            </ct-block>
                        </template>

                        <template v-else-if="column.key === 'active'">
                            <ct-block name="sw_users_user_list_column_active">
                                <a-badge
                                    :status="record.active ? 'success' : 'default'"
                                    :text="
                                        $t(
                                            record.active
                                                ? 'ct-users.filter.statusLabel.active'
                                                : 'ct-users.filter.statusLabel.inactive',
                                        )
                                    "
                                />
                            </ct-block>
                        </template>

                        <template v-else-if="column.key === 'action'">
                            <ct-block name="sw_users_user_list_content_grid_actions">
                                <a-dropdown :trigger="['click']">
                                    <a-button type="text" shape="circle" :aria-label="$t('global.default.edit')">
                                        <ct-icon name="MoreOutlined" :size="18" />
                                    </a-button>
                                    <template #overlay>
                                        <a-menu @click="onActionClick($event.key, record)">
                                            <a-menu-item key="edit" :disabled="!acl.can('users_and_permissions.editor')">
                                                <ct-icon name="EditOutlined" />
                                                {{ $t('global.default.edit') }}
                                            </a-menu-item>
                                            <a-menu-divider />
                                            <a-menu-item
                                                key="delete"
                                                danger
                                                :disabled="
                                                    !acl.can('users_and_permissions.deleter') ||
                                                    record.id === currentUser?.id
                                                "
                                            >
                                                <ct-icon name="DeleteOutlined" />
                                                {{ $t('global.default.delete') }}
                                            </a-menu-item>
                                        </a-menu>
                                    </template>
                                </a-dropdown>
                            </ct-block>
                        </template>
                    </template>
                </a-table>
            </ct-block>

            <ct-block name="sw_users_user_list_delete_modal">
                <a-modal
                    :open="Boolean(itemToDelete)"
                    :title="$t('ct-users.user-grid.titleModalDelete')"
                    :confirm-loading="isDeleting"
                    :ok-text="$t('global.default.delete')"
                    :cancel-text="$t('global.default.cancel')"
                    ok-type="danger"
                    @ok="onConfirmDelete(itemToDelete)"
                    @cancel="onCloseDeleteModal"
                >
                    <p v-if="itemToDelete" class="ct-users-user-listing__confirm-delete-text">
                        {{ $t('ct-users.user-grid.textModalDelete', { name: getUserDisplayName(itemToDelete) }, 0) }}
                    </p>
                </a-modal>
            </ct-block>

            <ct-block name="sw_users_user_list_bulk_delete_modal">
                <a-modal
                    :open="isBulkDeleteModalOpen"
                    :title="$t('ct-users.bulk.title')"
                    :confirm-loading="isDeleting"
                    :ok-text="$t('ct-users.bulk.delete')"
                    :cancel-text="$t('global.default.cancel')"
                    ok-type="danger"
                    @ok="onConfirmBulkDelete"
                    @cancel="onCloseBulkDeleteModal"
                >
                    <p class="ct-users-user-listing__confirm-delete-text">
                        {{ $t('ct-users.bulk.confirm', { count: selectedRowKeys.length }) }}
                    </p>
                </a-modal>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, nextTick, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import type { TablePaginationConfig } from 'ant-design-vue';
import type { SorterResult } from 'ant-design-vue/es/table/interface';
import type AclService from 'src/app/service/acl.service';
import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';
import type { TableColumnSetting } from 'src/app/component/base/ct-table-column-setting';

const { Criteria } = Contena.Data;

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

const props = withDefaults(
    defineProps<{
        columnSettings?: TableColumnSetting[];
    }>(),
    {
        columnSettings: () => [],
    },
);
const emit = defineEmits<{
    'get-list': [];
    'loading-change': [loading: boolean];
    'selection-change': [count: number];
    'total-change': [total: number];
}>();

interface UserRecord {
    id: string;
    username: string;
    userCode?: string;
    name?: string;
    phoneNumber?: string;
    email?: string;
    active?: boolean;
    avatarMedia?: { url?: string };
    aclRoles?: Array<{ id?: string; name: string }>;
}

interface Repository {
    search(criteria: unknown): Promise<any>;
    delete(id: string, context: unknown): Promise<void>;
}

interface RepositoryFactory {
    create(entity: string): Repository;
}

const { t } = useI18n();
const router = useRouter();
const { page, limit, total, sortDirection, term, initializeListing } = useListing();
const { createNotificationSuccess, createNotificationError } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');

if (!repositoryFactory || !acl) {
    throw new Error('The user listing services are unavailable.');
}

const user = ref<any>(null);
const isLoading = ref(false);
const isDeleting = ref(false);
const itemToDelete = ref<UserRecord | null>(null);
const isBulkDeleteModalOpen = ref(false);
const selectedRowKeys = ref<Array<string | number>>([]);
const disableRouteParams = ref(true);
const sortBy = ref('username');
const roles = ref<Array<{ id: string; name: string }>>([]);
const statusFilter = ref('all');
const roleFilter = ref<string[]>([]);

const userRepository = computed(() => repositoryFactory.create('user'));
const roleRepository = computed(() => repositoryFactory.create('acl_role'));
const currentUser = computed<UserRecord | null>(() => Contena.Store.get('session').currentUser);
const userDetailRouterLink = computed(() => 'ct.users.user.detail');
const userCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);

    if (term.value) {
        criteria.setTerm(term.value);
    }

    if (sortBy.value) {
        criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value || 'ASC'));
    }

    if (statusFilter.value !== 'all') {
        criteria.addFilter(Criteria.equals('active', statusFilter.value === 'active'));
    }

    if (roleFilter.value.length > 0) {
        criteria.addFilter(Criteria.equalsAny('aclRoles.id', roleFilter.value));
    }

    criteria.addAssociation('aclRoles');
    criteria.addAssociation('avatarMedia');

    return criteria;
});
const allUserColumns = computed(() => [
    {
        title: t('ct-users.user-grid.labelName'),
        key: 'user',
        dataIndex: 'name',
        sorter: true,
        width: 240,
    },
    { title: t('ct-users.user-grid.labelUserCode'), key: 'userCode', dataIndex: 'userCode', sorter: true, width: 140 },
    {
        title: t('ct-users.user-grid.labelContact'),
        key: 'contact',
        width: 240,
    },
    { title: t('ct-users.user-grid.labelRoles'), key: 'aclRoles', dataIndex: 'aclRoles', width: 220 },
    { title: t('ct-users.user-grid.status'), key: 'active', dataIndex: 'active', align: 'center', width: 100 },
    { title: '', key: 'action', align: 'center', width: 64 },
]);
const defaultColumnSettings = computed<TableColumnSetting[]>(() =>
    allUserColumns.value.map((column) => ({
        key: String(column.key),
        title: String(column.title ?? ''),
        checked: true,
        fixed: column.key === 'user' ? 'left' : column.key === 'action' ? 'right' : false,
        required: column.key === 'user' || column.key === 'action',
    })),
);
const userColumns = computed(() => {
    const settings = props.columnSettings.length > 0 ? props.columnSettings : defaultColumnSettings.value;
    const columnsByKey = new Map(
        allUserColumns.value.map((column) => [
            String(column.key),
            column,
        ]),
    );

    return settings.reduce<typeof allUserColumns.value>((columns, setting) => {
        const column = columnsByKey.get(setting.key);
        if (!column || (!setting.checked && !setting.required)) {
            return columns;
        }

        columns.push({
            ...column,
            ...(setting.fixed ? { fixed: setting.fixed } : {}),
        });
        return columns;
    }, []);
});
const statusFilterOptions = computed(() => [
    { value: 'all', label: t('ct-users.filter.allStatuses') },
    { value: 'active', label: t('ct-users.filter.active') },
    { value: 'inactive', label: t('ct-users.filter.inactive') },
]);
const roleFilterOptions = computed(() => roles.value.map((role) => ({ value: role.id, label: role.name })));
const filterCount = computed(() => Number(statusFilter.value !== 'all') + Number(roleFilter.value.length > 0));
const selectedUsers = computed<UserRecord[]>(() => {
    const records = Array.from(user.value ?? []) as UserRecord[];

    return records.filter((record) => selectedRowKeys.value.includes(record.id));
});
const rowSelection = computed(() => {
    if (!acl.can('users_and_permissions.deleter')) {
        return undefined;
    }

    return {
        selectedRowKeys: selectedRowKeys.value,
        onChange: (keys: Array<string | number>) => {
            selectedRowKeys.value = keys;
            emit('selection-change', keys.length);
        },
        getCheckboxProps: (record: UserRecord) => ({
            disabled: record.id === currentUser.value?.id,
        }),
    };
});
const tablePagination = computed<TablePaginationConfig>(() => ({
    current: page.value,
    pageSize: limit.value,
    total: total.value,
    showSizeChanger: true,
    hideOnSinglePage: true,
}));

const getUserDisplayName = (record: UserRecord) => record.name || record.username;
const getUserAvatarText = (record: UserRecord) => getUserDisplayName(record).trim().charAt(0).toUpperCase();
const loadRoles = async () => {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(Criteria.sort('name', 'ASC'));
    roles.value = await roleRepository.value.search(criteria);
};
const onSearch = (value: string) => {
    term.value = value;
    page.value = 1;
    return getList();
};
const onFilterChange = () => {
    page.value = 1;
    return getList();
};
const setStatusFilter = async (value: string) => {
    statusFilter.value = value;
    await nextTick();
    return onFilterChange();
};
const setRoleFilter = (value: string[]) => {
    roleFilter.value = value;
    return onFilterChange();
};
const resetFilters = () => {
    statusFilter.value = 'all';
    roleFilter.value = [];
    return onFilterChange();
};
const getList = async () => {
    isLoading.value = true;
    emit('get-list');
    emit('loading-change', true);

    try {
        const users = await userRepository.value.search(userCriteria.value);
        total.value = users.total;
        user.value = users;
        emit('total-change', users.total);
    } finally {
        isLoading.value = false;
        emit('loading-change', false);
    }
};
const onTableChange = (
    pagination: TablePaginationConfig,
    _filters: Record<string, unknown>,
    sorter: SorterResult<UserRecord> | SorterResult<UserRecord>[],
) => {
    page.value = pagination.current ?? 1;
    limit.value = pagination.pageSize ?? limit.value;

    const activeSorter = Array.isArray(sorter) ? sorter[0] : sorter;
    if (activeSorter?.field && activeSorter.order) {
        sortBy.value = String(activeSorter.field);
        sortDirection.value = activeSorter.order === 'descend' ? 'DESC' : 'ASC';
    }

    return getList();
};
const onEdit = (record: UserRecord) => {
    if (!acl.can('users_and_permissions.editor')) {
        return;
    }

    void router.push({ name: userDetailRouterLink.value, params: { id: record.id } });
};
const onDelete = (record: UserRecord) => {
    itemToDelete.value = record;
};
const onActionClick = (key: string, record: UserRecord) => {
    if (key === 'edit') {
        onEdit(record);
    } else if (key === 'delete') {
        onDelete(record);
    }
};
const onConfirmDelete = async (record: UserRecord | null) => {
    if (!record) {
        return;
    }

    if (record.id === currentUser.value?.id) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('ct-users.user-grid.notification.deleteUserLoggedInError.message'),
        });
        onCloseDeleteModal();
        return;
    }

    isDeleting.value = true;
    try {
        await userRepository.value.delete(record.id, Contena.Context.api);
        createNotificationSuccess({
            title: t('global.default.success'),
            message: t('ct-users.user-grid.notification.deleteSuccess.message', { name: getUserDisplayName(record) }, 0),
        });
        onCloseDeleteModal();
        await getList();
    } catch {
        createNotificationError({
            title: t('global.default.error'),
            message: t('ct-users.user-grid.notification.deleteError.message', { name: getUserDisplayName(record) }, 0),
        });
    } finally {
        isDeleting.value = false;
    }
};
const onCloseDeleteModal = () => {
    itemToDelete.value = null;
};
const requestBulkDelete = () => {
    if (selectedRowKeys.value.length === 0 || !acl.can('users_and_permissions.deleter')) {
        return;
    }

    isBulkDeleteModalOpen.value = true;
};
const onCloseBulkDeleteModal = () => {
    isBulkDeleteModalOpen.value = false;
};
const onConfirmBulkDelete = async () => {
    const records = selectedUsers.value.filter((record) => record.id !== currentUser.value?.id);
    if (records.length === 0) {
        onCloseBulkDeleteModal();
        return;
    }

    isDeleting.value = true;
    try {
        await Promise.all(records.map((record) => userRepository.value.delete(record.id, Contena.Context.api)));
        createNotificationSuccess({
            title: t('global.default.success'),
            message: t('ct-users.bulk.deleteSuccess', { count: records.length }),
        });
        selectedRowKeys.value = [];
        emit('selection-change', 0);
        onCloseBulkDeleteModal();
        await getList();
    } catch {
        createNotificationError({
            title: t('global.default.error'),
            message: t('ct-users.bulk.deleteError'),
        });
        await getList();
    } finally {
        isDeleting.value = false;
    }
};

void loadRoles();
initializeListing({ getList, sortBy, disableRouteParams });

swDefinePublic({
    repositoryFactory,
    acl,
    user,
    isLoading,
    isDeleting,
    itemToDelete,
    isBulkDeleteModalOpen,
    selectedRowKeys,
    disableRouteParams,
    sortBy,
    roles,
    statusFilter,
    roleFilter,
    userRepository,
    roleRepository,
    currentUser,
    userDetailRouterLink,
    userCriteria,
    userColumns,
    allUserColumns,
    selectedUsers,
    rowSelection,
    statusFilterOptions,
    roleFilterOptions,
    filterCount,
    tablePagination,
    getUserDisplayName,
    getUserAvatarText,
    loadRoles,
    onSearch,
    onFilterChange,
    setStatusFilter,
    setRoleFilter,
    resetFilters,
    getList,
    onTableChange,
    onEdit,
    onDelete,
    onActionClick,
    onConfirmDelete,
    onCloseDeleteModal,
    requestBulkDelete,
    onCloseBulkDeleteModal,
    onConfirmBulkDelete,
});

defineExpose({
    statusFilter,
    roleFilter,
    statusFilterOptions,
    roleFilterOptions,
    filterCount,
    selectedRowKeys,
    getList,
    onSearch,
    setStatusFilter,
    setRoleFilter,
    resetFilters,
    requestBulkDelete,
});
</script>

<style lang="scss">
.ct-users-user-listing {
    display: flex;
    min-height: 0;
    overflow: hidden;
    flex: 1;
    flex-direction: column;

    &__table,
    &__table .ant-spin-nested-loading,
    &__table .ant-spin-container {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
    }

    &__table .ant-table {
        flex: 1;
    }

    &__table .ant-table {
        border-radius: 0;
    }

    &__table .ant-table-thead > tr > th {
        color: var(--ct-color-text-secondary);
        background: var(--ct-color-bg-container);
        border-bottom-color: var(--ct-color-border-secondary);
        font-size: var(--ct-font-size-sm);
        font-weight: 500;
    }

    &__table .ant-table-tbody > tr > td {
        padding-block: 14px;
    }

    &__identity {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 10px;

        .ant-avatar {
            flex: 0 0 auto;
            color: var(--ct-color-primary);
            background: var(--ct-color-primary-bg);
        }
    }

    &__username {
        display: flex;
        overflow: hidden;
        min-width: 0;
        flex-direction: column;
        text-decoration: none;

        strong,
        span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        strong {
            color: var(--ct-color-text);
            font-size: var(--ct-font-size);
            font-weight: 500;
            line-height: 20px;
        }

        span {
            color: var(--ct-color-text-tertiary);
            font-size: var(--ct-font-size-sm);
            line-height: 18px;
        }
    }

    &__contact {
        display: flex;
        min-width: 0;
        flex-direction: column;

        span {
            overflow: hidden;
            color: var(--ct-color-text-secondary);
            font-size: var(--ct-font-size-sm);
            line-height: 20px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        span + span {
            color: var(--ct-color-text-tertiary);
        }
    }

    &__empty {
        color: var(--ct-color-text-tertiary);
    }

    &__confirm-delete-text {
        margin: 0;
        color: var(--ct-color-text-secondary);
        line-height: 24px;
    }

    .ant-pagination {
        margin: var(--ct-spacing) var(--ct-spacing-lg);
    }
}
</style>
