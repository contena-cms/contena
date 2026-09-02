<template>
    <ct-block name="ct_permissions_role_listing">
        <mt-card class="ct-permissions-role-listing" position-identifier="ct-permissions-role-listing">
            <ct-block name="ct_permissions_role_listing_grid">
                <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
                <ct-data-grid
                    v-if="showListingResults || isLoading"
                    :data-source="roles"
                    :columns="rolesColumns"
                    identifier="roles-grid"
                    :show-settings="true"
                    :show-selection="false"
                    :is-loading="isLoading"
                    @column-sort="onSortColumn"
                >
                    <template #column-name="{ item }">
                        <ct-block name="ct_permissions_role_listing_grid_column_name">
                            <mt-link as="a" @click.prevent="openEditRole(item.id)">
                                {{ item.name }}
                            </mt-link>
                        </ct-block>
                    </template>

                    <template #column-createdAt="{ item }">
                        <ct-block name="ct_permissions_role_listing_grid_column_created_at">
                            <ct-time-ago v-if="item.createdAt" :date="item.createdAt" />
                            <span v-else>—</span>
                        </ct-block>
                    </template>

                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                    <template #column-createdBy="{ item }">
                        <ct-block name="ct_permissions_role_listing_grid_column_created_by">
                            <span class="ct-permissions-role-listing__created-by">
                                {{ item.createdBy ? formatUserName(item.createdBy) : '' }}
                            </span>
                        </ct-block>
                    </template>

                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                    <template #column-users="{ item }">
                        <ct-block name="ct_permissions_role_listing_grid_column_users">
                            <mt-badge class="ct-permissions-role-listing__user-count" variant="neutral">
                                {{ item.users?.length ?? 0 }}
                            </mt-badge>
                        </ct-block>
                    </template>

                    <template #actions="{ item }">
                        <ct-block name="ct_permissions_role_listing_grid_actions">
                            <ct-context-menu-item
                                class="ct-permissions-role-listing__context-menu-permissions"
                                :disabled="!acl.can('users_and_permissions.viewer') || undefined"
                                @click="openPermissions(item.id)"
                            >
                                {{ $t('ct-permissions.roles.role-grid.assignPermissions') }}
                            </ct-context-menu-item>

                            <ct-context-menu-item
                                class="ct-permissions-role-listing__context-menu-edit"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                @click="openEditRole(item.id)"
                            >
                                {{ $t('global.default.edit') }}
                            </ct-context-menu-item>

                            <ct-context-menu-item
                                variant="danger"
                                class="ct-permissions-role-listing__context-menu-delete"
                                :disabled="!acl.can('users_and_permissions.deleter') || undefined"
                                @click="onDelete(item)"
                            >
                                {{ $t('global.default.delete') }}
                            </ct-context-menu-item>
                        </ct-block>
                    </template>

                    <template #pagination>
                        <ct-block name="ct_permissions_role_listing_grid_pagination">
                            <ct-pagination
                                :page="page"
                                :limit="limit"
                                :total="total"
                                :auto-hide="true"
                                @page-change="onPageChange"
                            />
                        </ct-block>
                    </template>
                </ct-data-grid>
            </ct-block>

            <ct-block name="ct_permissions_role_listing_empty_state">
                <template v-if="showListingResults || isLoading"
                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                >
                <mt-empty-state
                    v-else
                    :icon="$route.meta.$module.icon"
                    :headline="$t('ct-permissions.roles.role-grid.messageEmptyTitle')"
                    :description="$t('ct-permissions.roles.role-grid.messageEmptySubline')"
                />
            </ct-block>
        </mt-card>

        <ct-block name="ct_permissions_role_listing_modals">
            <ct-permissions-role-form-modal
                v-if="isRoleFormModalOpen"
                :role-id="roleFormRoleId"
                @saved="onRoleSaved"
                @close="closeRoleForm"
            />

            <ct-permissions-role-permissions-modal
                v-if="permissionsRoleId"
                :role-id="permissionsRoleId"
                @saved="onRoleSaved"
                @close="closePermissions"
            />

            <ct-modal
                v-if="isConfirmDeleteModalOpen && itemToDelete"
                :title="$t('ct-permissions.roles.role-grid.titleModalDelete')"
                variant="small"
                @modal-close="onCloseDeleteModal"
            >
                <p class="ct-permissions-role-listing__confirm-delete-text">
                    {{ $t('ct-permissions.roles.role-grid.textModalDelete', { name: itemToDelete.name }, 0) }}
                </p>

                <template #modal-footer>
                    <mt-button size="small" variant="secondary" @click="onCloseDeleteModal">
                        {{ $t('global.default.cancel') }}
                    </mt-button>
                    <mt-button
                        class="ct-permissions-role-listing__confirm-delete-button"
                        variant="critical"
                        size="small"
                        @click="onConfirmDelete"
                    >
                        {{ $t('global.default.delete') }}
                    </mt-button>
                </template>
            </ct-modal>
        </ct-block>
    </ct-block>
</template>

<script setup>
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';
import './ct-permissions-role-listing.scss';

const { Criteria } = Contena.Data;
defineProps({});
const emit = defineEmits([
    'loading-change',
    'total-change',
]);
const { t } = useI18n();
const { page, limit, total, sortBy, sortDirection, term, onPageChange, onSortColumn, initializeListing } = useListing();
const { createNotificationSuccess, createNotificationError } = useNotification();

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

if (!repositoryFactory || !acl) {
    throw new Error('The role listing requires the repository factory and ACL service.');
}

const roleRepository = repositoryFactory.create('acl_role');
const roles = ref([]);
const isLoading = ref(false);
const itemToDelete = ref(null);
const isConfirmDeleteModalOpen = ref(false);
const isRoleFormModalOpen = ref(false);
const roleFormRoleId = ref(null);
const permissionsRoleId = ref(null);

const rolesColumns = computed(() => [
    { property: 'name', label: t('ct-permissions.roles.role-grid.labelName'), primary: true },
    { property: 'code', label: t('ct-permissions.roles.role-grid.labelCode'), width: '180px' },
    { property: 'description', label: t('ct-permissions.roles.role-grid.labelDescription') },
    { property: 'createdAt', label: t('ct-permissions.roles.role-grid.labelCreatedAt'), width: '150px' },
    {
        property: 'createdBy',
        label: t('ct-permissions.roles.role-grid.labelCreatedBy'),
        sortable: false,
        width: '150px',
    },
    {
        property: 'users',
        label: t('ct-permissions.roles.role-grid.labelAssignedUsers'),
        sortable: false,
        width: '140px',
    },
]);
const roleCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);
    criteria.addFilter(Criteria.equals('deletedAt', null));
    criteria.addAssociation('createdBy');
    criteria.addAssociation('users');

    if (term.value) {
        criteria.setTerm(term.value);
    }

    if (sortBy.value) {
        // Criteria is a local mutable query object, not component state.
        // eslint-disable-next-line vue/no-side-effects-in-computed-properties
        criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value || 'ASC'));
    }

    return criteria;
});
const showListingResults = computed(() => !isLoading.value && roles.value.length > 0);
const getList = async () => {
    isLoading.value = true;
    emit('loading-change', true);

    try {
        const result = await roleRepository.search(roleCriteria.value);
        roles.value = result;
        total.value = result.total;
        emit('total-change', result.total);
    } finally {
        isLoading.value = false;
        emit('loading-change', false);
    }
};
const onSearch = (searchTerm) => {
    term.value = searchTerm;
    page.value = 1;
    void getList();
};
const openCreateRole = () => {
    roleFormRoleId.value = null;
    isRoleFormModalOpen.value = true;
};
const openEditRole = (roleId) => {
    if (!acl.can('users_and_permissions.editor')) {
        return;
    }

    roleFormRoleId.value = roleId;
    isRoleFormModalOpen.value = true;
};
const closeRoleForm = () => {
    isRoleFormModalOpen.value = false;
    roleFormRoleId.value = null;
};
const openPermissions = (roleId) => {
    if (!acl.can('users_and_permissions.viewer')) {
        return;
    }

    permissionsRoleId.value = roleId;
};
const closePermissions = () => {
    permissionsRoleId.value = null;
};
const onRoleSaved = () => {
    void getList();
};
const formatUserName = (user) => {
    const fullName = [
        user.firstName,
        user.lastName,
    ]
        .filter(Boolean)
        .join(' ')
        .trim();

    return fullName || user.username || user.email || t('ct-permissions.roles.role-grid.unknownUser');
};
const onDelete = (role) => {
    itemToDelete.value = role;
    isConfirmDeleteModalOpen.value = true;
};
const onCloseDeleteModal = () => {
    isConfirmDeleteModalOpen.value = false;
    itemToDelete.value = null;
};
const onConfirmDelete = async () => {
    isConfirmDeleteModalOpen.value = false;
    await deleteRole(Contena.Context.api);
};
const deleteRole = async (context) => {
    const role = itemToDelete.value;

    if (!role) {
        return;
    }

    isLoading.value = true;
    emit('loading-change', true);

    try {
        await roleRepository.delete(role.id, context);
        createNotificationSuccess({
            message: t('ct-permissions.roles.role-grid.notification.deleteSuccess.message', { name: role.name }, 0),
        });
        itemToDelete.value = null;
        await getList();
    } catch {
        createNotificationError({
            message: t('ct-permissions.roles.role-grid.notification.deleteError.message', { name: role.name }, 0),
        });
    } finally {
        isLoading.value = false;
        emit('loading-change', false);
    }
};
initializeListing({ getList, disableRouteParams: ref(true) });
void getList();

ctDefinePublic({
    acl,
    roles,
    isLoading,
    itemToDelete,
    isConfirmDeleteModalOpen,
    isRoleFormModalOpen,
    roleFormRoleId,
    permissionsRoleId,
    rolesColumns,
    roleCriteria,
    showListingResults,
    getList,
    onSearch,
    openCreateRole,
    openEditRole,
    closeRoleForm,
    openPermissions,
    closePermissions,
    onRoleSaved,
    formatUserName,
    onDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    deleteRole,
});

defineExpose({
    acl,
    roles,
    isLoading,
    itemToDelete,
    isConfirmDeleteModalOpen,
    isRoleFormModalOpen,
    roleFormRoleId,
    permissionsRoleId,
    rolesColumns,
    roleCriteria,
    showListingResults,
    getList,
    onSearch,
    openCreateRole,
    openEditRole,
    closeRoleForm,
    openPermissions,
    closePermissions,
    onRoleSaved,
    formatUserName,
    onDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    deleteRole,
});
</script>
