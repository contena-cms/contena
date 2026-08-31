<template>
    <ct-block name="ct_users_user_list">
        <div class="ct-users-user-listing__content">
            <mt-data-table
                class="ct-users-user-listing__table"
                layout="full"
                :caption="$t('ct-users.general.cardLabel')"
                :data-source="users"
                :columns="userColumns"
                :is-loading="isLoading"
                :pagination-total-items="total"
                :current-page="page"
                :pagination-limit="limit"
                :sort-by="sortBy"
                :sort-direction="sortDirection"
                :search-value="term || ''"
                :number-of-results="total"
                disable-search
                enable-reload
                disable-edit
                :allow-row-selection="acl.can('users_and_permissions.deleter')"
                :allow-bulk-delete="acl.can('users_and_permissions.deleter')"
                :selected-rows="selectedUserIds"
                :disable-delete="!acl.can('users_and_permissions.deleter')"
                :additional-context-buttons="additionalContextButtons"
                :filters="filters"
                :applied-filters="appliedFilters"
                @reload="getList"
                @pagination-current-page-change="onPageChange"
                @pagination-limit-change="onLimitChange"
                @sort-change="onSort"
                @search-value-change="onSearch"
                @item-delete="onDelete"
                @bulk-delete="onBulkDelete"
                @selection-change="onSelectionChange"
                @multiple-selection-change="onMultipleSelectionChange"
                @update:applied-filters="onAppliedFiltersChange"
                @context-select="onContextSelect"
            >
                <template #column-username="{ data: item }">
                    <span class="ct-users-user-listing__username-click-target">
                        <ct-block name="ct_users_user_list_column_username">
                            <mt-link
                                as="button"
                                type="button"
                                class="ct-users-user-listing__columns"
                                @click.prevent="emit('edit', item)"
                            >
                                <mt-avatar size="xs" :name="item.name" variant="square" :image-url="item.avatarMedia?.url" />
                                {{ item.username }}
                            </mt-link>
                        </ct-block>
                    </span>
                </template>

                <template #column-aclRoles="{ data: item }">
                    <ct-block name="ct_users_user_list_column_acl_roles">
                        {{ item.aclRoles?.map((role) => role.name).join(', ') || '' }}
                    </ct-block>
                </template>

                <template #column-active="{ data: item }">
                    <ct-block name="ct_users_user_list_column_active">
                        <mt-badge :variant="item.active ? 'positive' : 'critical'" size="s">
                            {{
                                $t(
                                    item.active
                                        ? 'ct-users.filter.statusLabel.active'
                                        : 'ct-users.filter.statusLabel.inactive',
                                )
                            }}
                        </mt-badge>
                    </ct-block>
                </template>

                <template #toolbar>
                    <div class="ct-users-user-listing__toolbar">
                        <ct-block name="ct_users_user_list_toolbar">
                            <mt-button
                                v-tooltip.bottom="{
                                    message: $t('ct-privileges.tooltip.warning'),
                                    disabled: acl.can('users_and_permissions.creator'),
                                    showOnDisabledElements: true,
                                }"
                                class="ct-users__create-user"
                                variant="primary"
                                size="default"
                                :disabled="!acl.can('users_and_permissions.creator') || undefined"
                                @click.prevent="emit('create')"
                            >
                                {{ $t('global.default.add') }}
                            </mt-button>
                        </ct-block>
                    </div>
                </template>

                <template #empty-state>
                    <mt-empty-state icon="regular-users" :headline="$t('ct-users.general.cardLabel')" />
                </template>
            </mt-data-table>
        </div>
    </ct-block>

    <mt-modal-root v-if="itemToDelete" :is-open="true" @change="onCloseDeleteModal">
        <mt-modal :title="$t('global.default.warning')" width="s">
            <p class="ct-users-user-listing__confirm-delete-text">
                <template v-if="itemsToDelete.length > 1">
                    {{
                        $t(
                            'ct-users.user-grid.textModalDeleteMultiple',
                            { count: itemsToDelete.length },
                            itemsToDelete.length,
                        )
                    }}
                </template>
                <template v-else>
                    {{ $t('ct-users.user-grid.textModalDelete', { name: getUserDisplayName(itemToDelete) }, 0) }}
                </template>
            </p>
            <template #footer>
                <mt-button size="small" variant="secondary" @click="onCloseDeleteModal">
                    {{ $t('global.default.cancel') }}
                </mt-button>
                <mt-button variant="critical" size="small" :is-loading="isDeleting" @click="onConfirmDelete(itemToDelete)">
                    {{ $t('global.default.delete') }}
                </mt-button>
            </template>
        </mt-modal>
    </mt-modal-root>
</template>

<script setup>
const { Data } = Contena;
const { Criteria } = Data;

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

defineProps({});
const emit = defineEmits([
    'get-list',
    'loading-change',
    'total-change',
    'edit',
    'create',
]);

import { ref, computed, inject, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const {
    page,
    limit,
    total,
    sortDirection,
    term,
    onPageChange: updatePage,
    onSort: updateSort,
    initializeListing,
} = useListing();
const { createNotificationSuccess, createNotificationError } = useNotification();

const userService = inject('userService');
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const user = ref(null);
const isLoading = ref(false);
const isDeleting = ref(false);
const itemToDelete = ref(null);
const itemsToDelete = ref([]);
const disableRouteParams = ref(true);
const sortBy = ref(null);
const roles = ref([]);
const statusFilter = ref('all');
const roleFilter = ref([]);
const appliedFilters = ref([]);
const selectedUserIds = ref([]);

const userRepository = computed(() => {
    return repositoryFactory.create('user');
});
const roleRepository = computed(() => {
    return repositoryFactory.create('acl_role');
});
const currentUser = computed(() => {
    return Contena.Store.get('session').currentUser;
});
const users = computed(() => (user.value ? Array.from(user.value) : []));
const userCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);

    if (term.value) {
        criteria.setTerm(term.value);
    }

    // Keep the initial data order stable without marking a table column as actively sorted.
    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
    criteria.addSorting(Criteria.sort(sortBy.value || 'username', sortDirection.value || 'ASC'));

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
const userColumns = computed(() => {
    return [
        {
            property: 'username',
            label: t('ct-users.user-grid.labelUsername'),
            position: 100,
            renderer: 'text',
            width: 220,
        },
        {
            property: 'userCode',
            label: t('ct-users.user-grid.labelUserCode'),
            position: 200,
            renderer: 'text',
            width: 160,
        },
        {
            property: 'name',
            label: t('ct-users.user-grid.labelName'),
            position: 300,
            renderer: 'text',
            width: 220,
        },
        {
            property: 'phoneNumber',
            label: t('ct-users.user-grid.labelPhoneNumber'),
            position: 400,
            renderer: 'text',
            width: 180,
        },
        {
            property: 'aclRoles',
            sortable: false,
            label: t('ct-users.user-grid.labelRoles'),
            position: 500,
            renderer: 'text',
            width: 220,
        },
        {
            property: 'email',
            label: t('ct-users.user-grid.labelEmail'),
            position: 600,
            renderer: 'text',
            width: 260,
        },
        {
            property: 'active',
            label: t('ct-users.user-grid.status'),
            position: 700,
            renderer: 'text',
            width: 130,
        },
    ];
});
const additionalContextButtons = computed(() => {
    if (!acl.can('users_and_permissions.viewer')) {
        return [];
    }

    return [
        {
            key: 'edit',
            label: acl.can('users_and_permissions.editor') ? t('global.default.edit') : t('global.default.view'),
        },
    ];
});
const filters = computed(() => [
    {
        id: 'status',
        label: t('ct-users.filter.status'),
        type: {
            id: 'single-selection',
            options: [
                { id: 'active', label: t('ct-users.filter.active') },
                { id: 'inactive', label: t('ct-users.filter.inactive') },
            ],
        },
    },
    {
        id: 'role',
        label: t('ct-users.filter.roles'),
        type: {
            id: 'multi-selection',
            options: roleFilterOptions.value.map((option) => ({
                id: option.value,
                label: option.label,
            })),
        },
    },
]);
const statusFilterOptions = computed(() => {
    return [
        { value: 'all', label: t('ct-users.filter.allStatuses') },
        { value: 'active', label: t('ct-users.filter.active') },
        { value: 'inactive', label: t('ct-users.filter.inactive') },
    ];
});
const roleFilterOptions = computed(() => {
    return roles.value.map((role) => ({
        value: role.id,
        label: role.name,
    }));
});
const filterCount = computed(() => {
    return Number(statusFilter.value !== 'all') + Number(roleFilter.value.length > 0);
});

const getUserDisplayName = (user) => {
    return user.name || user.username;
};
const getItemToDelete = (item) => {
    if (!itemToDelete.value) {
        return false;
    }
    return itemToDelete.value.id === item.id;
};
const loadRoles = () => {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(Criteria.sort('name', 'ASC'));

    return roleRepository.value.search(criteria).then((result) => {
        roles.value = result;
    });
};
const onSearch = (value) => {
    term.value = value;
    page.value = 1;

    getList();
};
const onPageChange = (value) => {
    updatePage({ page: value, limit: limit.value });
};
const onLimitChange = (value) => {
    limit.value = value;
    page.value = 1;
    getList();
};
const onSort = (value) => {
    updateSort(value);
};
const onFilterChange = () => {
    page.value = 1;
    getList();
};
const setStatusFilter = async (value) => {
    statusFilter.value = value;
    appliedFilters.value =
        value === 'all'
            ? appliedFilters.value.filter((filter) => filter.id !== 'status')
            : [
                  ...appliedFilters.value.filter((filter) => filter.id !== 'status'),
                  {
                      ...filters.value.find((filter) => filter.id === 'status'),
                      type: {
                          ...filters.value.find((filter) => filter.id === 'status').type,
                          options: [
                              filters.value
                                  .find((filter) => filter.id === 'status')
                                  .type.options.find((option) => option.id === value),
                          ],
                      },
                  },
              ];
    await nextTick();
    return onFilterChange();
};
const setRoleFilter = (value) => {
    roleFilter.value = value;
    if (value.length === 0) {
        appliedFilters.value = appliedFilters.value.filter((filter) => filter.id !== 'role');
    } else {
        const roleFilterDefinition = filters.value.find((filter) => filter.id === 'role');
        appliedFilters.value = [
            ...appliedFilters.value.filter((filter) => filter.id !== 'role'),
            {
                ...roleFilterDefinition,
                type: {
                    ...roleFilterDefinition.type,
                    options: roleFilterDefinition.type.options.filter((option) => value.includes(option.id)),
                },
            },
        ];
    }
    onFilterChange();
};
const resetFilters = () => {
    statusFilter.value = 'all';
    roleFilter.value = [];
    appliedFilters.value = [];
    onFilterChange();
};
const onAppliedFiltersChange = (value) => {
    appliedFilters.value = value;
    const status = value.find((filter) => filter.id === 'status');
    const role = value.find((filter) => filter.id === 'role');
    statusFilter.value = status?.type.options[0]?.id || 'all';
    roleFilter.value = role?.type.options.map((option) => option.id) || [];
    onFilterChange();
};
const getList = () => {
    isLoading.value = true;
    user.value = null;

    emit('get-list');
    emit('loading-change', true);

    return userRepository.value
        .search(userCriteria.value)
        .then((users) => {
            total.value = users.total;
            user.value = users;
            emit('total-change', users.total);
        })
        .finally(() => {
            isLoading.value = false;
            emit('loading-change', false);
        });
};
const onDelete = (user) => {
    if (!acl.can('users_and_permissions.deleter')) {
        return;
    }

    itemsToDelete.value = [user];
    itemToDelete.value = user;
};
const onSelectionChange = ({ id, value }) => {
    if (value) {
        if (!selectedUserIds.value.includes(id)) {
            selectedUserIds.value = [
                ...selectedUserIds.value,
                id,
            ];
        }
        return;
    }

    selectedUserIds.value = selectedUserIds.value.filter((selectedId) => selectedId !== id);
};
const onMultipleSelectionChange = ({ selections, value }) => {
    selectedUserIds.value = value ? selections : [];
};
const onBulkDelete = () => {
    if (!acl.can('users_and_permissions.deleter')) {
        return;
    }

    const usersToDelete = users.value.filter((user) => selectedUserIds.value.includes(user.id));
    if (usersToDelete.length === 0) {
        return;
    }

    itemsToDelete.value = usersToDelete;
    itemToDelete.value = usersToDelete[0];
};
const onContextSelect = ({ key, data }) => {
    if (key === 'edit') {
        emit('edit', data);
    }
};
const onConfirmDelete = (user) => {
    if (!user || !acl.can('users_and_permissions.deleter')) {
        return;
    }

    const usersToDelete = itemsToDelete.value.length > 0 ? itemsToDelete.value : [user];
    const username = getUserDisplayName(user);
    const titleDeleteSuccess = t('global.default.success');
    const messageDeleteSuccess =
        usersToDelete.length > 1
            ? t('ct-users.user-grid.notification.deleteSuccessMultiple.message', { count: usersToDelete.length })
            : t('ct-users.user-grid.notification.deleteSuccess.message', { name: username }, 0);
    const titleDeleteError = t('global.default.error');
    const messageDeleteError =
        usersToDelete.length > 1
            ? t('ct-users.user-grid.notification.deleteErrorMultiple.message', { count: usersToDelete.length })
            : t('ct-users.user-grid.notification.deleteError.message', { name: username }, 0);

    if (usersToDelete.some((entry) => entry.id === currentUser.value?.id)) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('ct-users.user-grid.notification.deleteUserLoggedInError.message'),
        });

        onCloseDeleteModal();

        return;
    }

    isDeleting.value = true;
    Promise.all(usersToDelete.map((entry) => userRepository.value.delete(entry.id, Contena.Context.api)))
        .then(() => {
            createNotificationSuccess({
                title: titleDeleteSuccess,
                message: messageDeleteSuccess,
            });
            getList();
            selectedUserIds.value = [];
        })
        .catch(() => {
            createNotificationError({
                title: titleDeleteError,
                message: messageDeleteError,
            });
        })
        .finally(() => {
            isDeleting.value = false;
            onCloseDeleteModal();
        });
};
const onCloseDeleteModal = () => {
    itemToDelete.value = null;
    itemsToDelete.value = [];
};

loadRoles();
initializeListing({
    getList,
    sortBy,
    disableRouteParams,
});

swDefinePublic({
    userService,
    repositoryFactory,
    acl,
    user,
    users,
    isLoading,
    isDeleting,
    itemToDelete,
    itemsToDelete,
    disableRouteParams,
    sortBy,
    roles,
    statusFilter,
    roleFilter,
    userRepository,
    roleRepository,
    currentUser,
    userCriteria,
    userColumns,
    additionalContextButtons,
    filters,
    appliedFilters,
    selectedUserIds,
    statusFilterOptions,
    roleFilterOptions,
    filterCount,
    getUserDisplayName,
    getItemToDelete,
    loadRoles,
    onSearch,
    onPageChange,
    onLimitChange,
    onSort,
    onFilterChange,
    setStatusFilter,
    setRoleFilter,
    resetFilters,
    onAppliedFiltersChange,
    getList,
    onDelete,
    onBulkDelete,
    onSelectionChange,
    onMultipleSelectionChange,
    onContextSelect,
    onConfirmDelete,
    onCloseDeleteModal,
});

defineExpose({
    userService,
    repositoryFactory,
    acl,
    user,
    users,
    isLoading,
    isDeleting,
    itemToDelete,
    itemsToDelete,
    disableRouteParams,
    sortBy,
    roles,
    statusFilter,
    roleFilter,
    userRepository,
    roleRepository,
    currentUser,
    userCriteria,
    userColumns,
    additionalContextButtons,
    filters,
    appliedFilters,
    selectedUserIds,
    statusFilterOptions,
    roleFilterOptions,
    filterCount,
    getUserDisplayName,
    getItemToDelete,
    loadRoles,
    onSearch,
    onPageChange,
    onLimitChange,
    onSort,
    onFilterChange,
    setStatusFilter,
    setRoleFilter,
    resetFilters,
    onAppliedFiltersChange,
    getList,
    onDelete,
    onBulkDelete,
    onSelectionChange,
    onMultipleSelectionChange,
    onContextSelect,
    onConfirmDelete,
    onCloseDeleteModal,
});
</script>

<style lang="scss">
.ct-users-user-listing__table .mt-data-table__toolbar {
    justify-content: flex-end;
}

.mt-data-table.ct-users-user-listing__table {
    width: 100%;
    max-width: none;
    height: 100%;
    margin-bottom: 0;
}

.ct-users-user-listing__content {
    height: 100%;
    padding: var(--scale-size-16);
    box-sizing: border-box;
}

.ct-users-user-listing__confirm-delete-text {
    margin-bottom: var(--scale-size-24);
}

</style>
