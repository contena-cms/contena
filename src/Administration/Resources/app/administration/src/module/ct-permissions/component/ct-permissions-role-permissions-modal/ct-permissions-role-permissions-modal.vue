<template>
    <ct-block name="ct_permissions_role_permissions_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal
                class="ct-permissions-role-permissions-modal"
                width="full"
                :title="t('ct-permissions.roles.modal.permissionsTitle', { name: role?.name ?? '' })"
            >
                <ct-block name="ct_permissions_role_permissions_modal_tabs">
                    <mt-tabs
                        class="ct-permissions-role-permissions-modal__tabs"
                        :items="tabItems"
                        :default-item="activeTab"
                        @new-item-active="activeTab = $event"
                    />
                </ct-block>

                <ct-block name="ct_permissions_role_permissions_modal_content">
                    <div v-if="role" class="ct-permissions-role-permissions-modal__content">
                        <template v-if="activeTab === 'permissions'">
                            <ct-permissions-role-access
                                :role="role"
                                :is-loading="isLoading"
                                :disabled="!canEdit || undefined"
                            />
                            <ct-permissions-additional-permissions
                                :role="role"
                                :is-loading="isLoading"
                                :disabled="!canEdit || undefined"
                            />
                        </template>

                        <template v-else>
                            <mt-banner variant="info">
                                {{ t('ct-permissions.roles.view.detailed.alertText') }}
                            </mt-banner>
                            <ct-permissions-detailed-additional-permissions
                                :role="role"
                                :detailed-privileges="detailedPrivileges"
                                :is-loading="isLoading"
                                :disabled="!canEdit || undefined"
                            />
                            <ct-permissions-detailed-permissions-grid
                                :role="role"
                                :detailed-privileges="detailedPrivileges"
                                :is-loading="isLoading"
                                :disabled="!canEdit || undefined"
                            />
                        </template>
                    </div>
                </ct-block>

                <template #footer>
                    <ct-block name="ct_permissions_role_permissions_modal_footer">
                        <div class="ct-permissions-role-permissions-modal__footer-actions">
                            <mt-button variant="secondary" size="small" @click="closeModal">
                                {{ t('global.default.cancel') }}
                            </mt-button>
                            <mt-button
                                variant="primary"
                                size="small"
                                :is-loading="isLoading"
                                :disabled="!canEdit || isLoading || undefined"
                                @click="requestSave"
                            >
                                {{ t('global.default.save') }}
                            </mt-button>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { useNotification } from 'src/app/composables/use-notification';
import type AclService from 'src/app/service/acl.service';
import type PrivilegesService from 'src/app/service/privileges.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import './ct-permissions-role-permissions-modal.scss';

type RoleEntity = Entity<'acl_role'> & { name: string; privileges: string[] };
type TabName = 'permissions' | 'advanced';
interface UserService {
    getUser(): Promise<{ data: Record<string, unknown> }>;
}

const props = defineProps<{ roleId: string }>();
const emit = defineEmits<{ close: []; saved: [] }>();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const privileges = inject<PrivilegesService>('privileges');
const acl = inject<AclService>('acl');
const userService = inject<UserService>('userService');

if (!repositoryFactory || !privileges || !acl || !userService) {
    throw new Error('The role permissions modal requires repository, privilege, ACL, and user services.');
}

const roleRepository = repositoryFactory.create('acl_role');
const role = ref<RoleEntity | null>(null);
const detailedPrivileges = ref<string[]>([]);
const isLoading = ref(false);
const activeTab = ref<TabName>('permissions');
const tabItems = computed(() => [
    { name: 'permissions', label: t('ct-permissions.roles.tabs.permissions') },
    { name: 'advanced', label: t('ct-permissions.roles.tabs.detailed') },
]);
const canEdit = computed(() => acl.can('users_and_permissions.editor'));
const loadRole = async () => {
    isLoading.value = true;

    try {
        role.value = (await roleRepository.get(props.roleId)) as RoleEntity;
        const filteredPrivileges = privileges.filterPrivilegesRoles(role.value.privileges);
        const generalPrivileges = privileges.getPrivilegesForAdminPrivilegeKeys(filteredPrivileges);
        const defaultUserPrivileges = privileges.getDefaultUserPrivileges();

        detailedPrivileges.value = role.value.privileges.filter(
            (privilege) =>
                ![
                    ...generalPrivileges,
                    ...defaultUserPrivileges,
                ].includes(privilege),
        );
        role.value.privileges = filteredPrivileges;
    } finally {
        isLoading.value = false;
    }
};
const requestSave = async () => {
    if (!canEdit.value) {
        return;
    }

    await savePermissions(Contena.Context.api);
};
const savePermissions = async (context: unknown) => {
    if (!role.value || !canEdit.value) {
        return;
    }

    const privilegesToSave = [
        ...privileges.getPrivilegesForAdminPrivilegeKeys(role.value.privileges),
        ...detailedPrivileges.value,
    ]
        .filter((privilege, index, allPrivileges) => allPrivileges.indexOf(privilege) === index)
        .sort();
    isLoading.value = true;
    role.value.privileges = privilegesToSave;

    try {
        await roleRepository.save(role.value, context);
        await updateCurrentUser();
        emit('saved');
        emit('close');
    } catch {
        role.value.privileges = privileges.filterPrivilegesRoles(role.value.privileges);
        createNotificationError({
            message: t('ct-permissions.roles.modal.permissionsSaveError', { name: role.value.name }, 0),
        });
    } finally {
        isLoading.value = false;
    }
};
const updateCurrentUser = async () => {
    const { data } = await userService.getUser();

    delete data.password;
    Contena.Store.get('session').setCurrentUser(data);
};
const closeModal = () => {
    if (!isLoading.value) {
        emit('close');
    }
};
const onModalChange = (isOpen: boolean) => {
    if (!isOpen) {
        closeModal();
    }
};

void loadRole();

ctDefinePublic({
    role,
    detailedPrivileges,
    isLoading,
    activeTab,
    tabItems,
    canEdit,
    loadRole,
    requestSave,
    savePermissions,
    updateCurrentUser,
    closeModal,
    onModalChange,
});

defineExpose({
    role,
    detailedPrivileges,
    isLoading,
    activeTab,
    tabItems,
    canEdit,
    loadRole,
    requestSave,
    savePermissions,
    updateCurrentUser,
    closeModal,
    onModalChange,
});
</script>
