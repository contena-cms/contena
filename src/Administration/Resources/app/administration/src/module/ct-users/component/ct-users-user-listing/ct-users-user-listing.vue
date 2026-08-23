<template>
    <ct-block name="sw_users_user_list">
        <mt-card class="ct-users-user-listing ct-users-user-listing" position-identifier="ct-users-user-listing">
            <template #grid>
                <ct-block name="sw_users_user_list_content">
                    <ct-block name="sw_users_user_list_content_grid">
                        <ct-entity-listing
                            v-if="isLoading || user"
                            :data-source="user"
                            :columns="userColumns"
                            :repository="userRepository"
                            identifier="user-grid"
                            :show-settings="true"
                            :show-selection="false"
                            :is-loading="isLoading"
                            :disable-data-fetching="true"
                            :full-page="false"
                            :compact-mode="true"
                            :sort-by="sortBy"
                            :sort-direction="sortDirection"
                            :allow-view="acl.can('users_and_permissions.viewer')"
                            :allow-edit="acl.can('users_and_permissions.editor')"
                            :allow-delete="acl.can('users_and_permissions.deleter')"
                            @column-sort="onSortColumn"
                            @page-change="onPageChange"
                        >
                            <template #actions="{ item }">
                                <ct-block name="sw_users_user_list_content_grid_actions">
                                    <ct-block name="sw_users_user_list_actions_edit">
                                        <ct-context-menu-item
                                            class="ct-users-user-listing__user-view-action"
                                            :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                            @click="onEdit(item)"
                                        >
                                            {{ $t('global.default.edit') }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="sw_users_user_list_actions_delete">
                                        <ct-context-menu-item
                                            class="ct-users-user-listing__user-delete-action"
                                            variant="danger"
                                            :disabled="!acl.can('users_and_permissions.deleter') || undefined"
                                            @click="onDelete(item)"
                                        >
                                            {{ $t('global.default.delete') }}
                                        </ct-context-menu-item>
                                    </ct-block>
                                </ct-block>
                            </template>

                            <template #preview-username="{ item }">
                                <ct-block name="sw_users_user_list_column_username_preview">
                                    <mt-avatar
                                        size="xs"
                                        :name="item.name"
                                        variant="square"
                                        :image-url="item.avatarMedia?.url"
                                    />
                                </ct-block>
                            </template>

                            <template #column-username="{ item }">
                                <ct-block name="sw_users_user_list_column_username">
                                    <ct-block name="sw_users_user_list_column_username_content">
                                        <mt-link
                                            :as="RouterLink"
                                            class="ct-users-user-listing__columns"
                                            :to="{ name: userDetailRouterLink, params: { id: item.id } }"
                                        >
                                            {{ item.username }}
                                        </mt-link>
                                    </ct-block>
                                </ct-block>
                            </template>

                            <!-- ct-block preserves this slot variable at runtime. -->
                            <!-- eslint-disable-next-line vue/no-unused-vars -->
                            <template #column-email="emailColumn">
                                <ct-block name="sw_users_user_list_column_email">
                                    <ct-block name="sw_users_user_list_column_email_content">
                                        <span class="ct-data-grid__cell-value">
                                            {{ emailColumn.item.email }}
                                        </span>
                                    </ct-block>
                                </ct-block>
                            </template>

                            <template #column-aclRoles="{ item }">
                                <ct-block name="sw_users_user_list_column_username_acl_roles">
                                    <template v-if="item.aclRoles && item.aclRoles.length > 0">
                                        <span class="ct-data-grid__cell-value">
                                            <span v-for="(role, index) in item.aclRoles" :key="index">
                                                {{ role.name
                                                }}<template v-if="index + 1 < item.aclRoles.length">,&nbsp;</template>
                                            </span>
                                        </span>
                                    </template>

                                    <span v-else></span>
                                </ct-block>
                            </template>

                            <template #column-active="{ item }">
                                <ct-block name="sw_users_user_list_column_active">
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

                            <template #action-modals="{ item }">
                                <ct-block name="sw_users_user_list_delete_modal">
                                    <ct-modal
                                        v-if="getItemToDelete(item)"
                                        :title="$t('global.default.warning')"
                                        variant="small"
                                        class="ct-users-user-listing__delete-modal"
                                        @modal-close="onCloseDeleteModal"
                                    >
                                        <ct-block name="sw_users_user_list_delete_modal_confirm_delete_text">
                                            <p class="ct-users-user-listing__confirm-delete-text">
                                                {{
                                                    $t(
                                                        'ct-users.user-grid.textModalDelete',
                                                        { name: getUserDisplayName(item) },
                                                        0,
                                                    )
                                                }}
                                            </p>
                                        </ct-block>

                                        <template #modal-footer>
                                            <ct-block name="sw_users_user_list_delete_modal_footer">
                                                <ct-block name="sw_users_user_list_delete_modal_cancel">
                                                    <mt-button size="small" variant="secondary" @click="onCloseDeleteModal">
                                                        {{ $t('global.default.cancel') }}
                                                    </mt-button>
                                                </ct-block>

                                                <ct-block name="sw_users_user_list_delete_modal_confirm">
                                                    <mt-button
                                                        :is-loading="isLoading"
                                                        variant="critical"
                                                        size="small"
                                                        @click="onConfirmDelete(item)"
                                                    >
                                                        {{ $t('global.default.delete') }}
                                                    </mt-button>
                                                </ct-block>
                                            </ct-block>
                                        </template>
                                    </ct-modal>
                                </ct-block>
                            </template>
                        </ct-entity-listing>
                    </ct-block>
                </ct-block>
            </template>
        </mt-card>
    </ct-block>
</template>

<script setup>
import './ct-users-user-listing.scss';
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
]);

import { ref, computed, inject, nextTick } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const router = useRouter();
const { page, limit, total, sortDirection, term, onPageChange, onSortColumn, initializeListing } = useListing();
const { createNotificationSuccess, createNotificationError } = useNotification();

const userService = inject('userService');
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const user = ref(null);
const isLoading = ref(false);
const itemToDelete = ref(null);
const disableRouteParams = ref(true);
const sortBy = ref('username');
const roles = ref([]);
const statusFilter = ref('all');
const roleFilter = ref([]);

const userRepository = computed(() => {
    return repositoryFactory.create('user');
});
const roleRepository = computed(() => {
    return repositoryFactory.create('acl_role');
});
const currentUser = computed(() => {
    return Contena.Store.get('session').currentUser;
});
const userDetailRouterLink = computed(() => {
    return 'ct.users.user.detail';
});
const userCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);

    if (term.value) {
        criteria.setTerm(term.value);
    }

    if (sortBy.value) {
        // Criteria is a local mutable query object, not component state.
        // eslint-disable-next-line vue/no-side-effects-in-computed-properties
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
const userColumns = computed(() => {
    return [
        {
            property: 'username',
            label: t('ct-users.user-grid.labelUsername'),
        },
        {
            property: 'userCode',
            label: t('ct-users.user-grid.labelUserCode'),
        },
        {
            property: 'name',
            label: t('ct-users.user-grid.labelName'),
        },
        {
            property: 'phoneNumber',
            label: t('ct-users.user-grid.labelPhoneNumber'),
        },
        {
            property: 'aclRoles',
            sortable: false,
            label: t('ct-users.user-grid.labelRoles'),
        },
        {
            property: 'email',
            label: t('ct-users.user-grid.labelEmail'),
        },
        {
            property: 'active',
            label: t('ct-users.user-grid.status'),
            align: 'center',
        },
    ];
});
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
const onFilterChange = () => {
    page.value = 1;
    getList();
};
const setStatusFilter = async (value) => {
    statusFilter.value = value;
    await nextTick();
    return onFilterChange();
};
const setRoleFilter = (value) => {
    roleFilter.value = value;
    onFilterChange();
};
const resetFilters = () => {
    statusFilter.value = 'all';
    roleFilter.value = [];
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
const onEdit = (user) => {
    if (!acl.can('users_and_permissions.editor')) {
        return;
    }

    void router.push({
        name: userDetailRouterLink.value,
        params: { id: user.id },
    });
};
const onDelete = (user) => {
    itemToDelete.value = user;
};
const onConfirmDelete = (user) => {
    const username = getUserDisplayName(user);
    const titleDeleteSuccess = t('global.default.success');
    const messageDeleteSuccess = t('ct-users.user-grid.notification.deleteSuccess.message', { name: username }, 0);
    const titleDeleteError = t('global.default.error');
    const messageDeleteError = t(
        'ct-users.user-grid.notification.deleteError.message',
        {
            name: username,
        },
        0,
    );

    if (user.id === currentUser.value?.id) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('ct-users.user-grid.notification.deleteUserLoggedInError.message'),
        });

        onCloseDeleteModal();

        return;
    }

    userRepository.value
        .delete(user.id, Contena.Context.api)
        .then(() => {
            createNotificationSuccess({
                title: titleDeleteSuccess,
                message: messageDeleteSuccess,
            });
            getList();
        })
        .catch(() => {
            createNotificationError({
                title: titleDeleteError,
                message: messageDeleteError,
            });
        });

    onCloseDeleteModal();
};
const onCloseDeleteModal = () => {
    itemToDelete.value = null;
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
    isLoading,
    itemToDelete,
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
    statusFilterOptions,
    roleFilterOptions,
    filterCount,
    getUserDisplayName,
    getItemToDelete,
    loadRoles,
    onSearch,
    onFilterChange,
    setStatusFilter,
    setRoleFilter,
    resetFilters,
    getList,
    onEdit,
    onDelete,
    onConfirmDelete,
    onCloseDeleteModal,
});

defineExpose({
    userService,
    repositoryFactory,
    acl,
    user,
    isLoading,
    itemToDelete,
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
    statusFilterOptions,
    roleFilterOptions,
    filterCount,
    getUserDisplayName,
    getItemToDelete,
    loadRoles,
    onSearch,
    onFilterChange,
    setStatusFilter,
    setRoleFilter,
    resetFilters,
    getList,
    onEdit,
    onDelete,
    onConfirmDelete,
    onCloseDeleteModal,
});
</script>
