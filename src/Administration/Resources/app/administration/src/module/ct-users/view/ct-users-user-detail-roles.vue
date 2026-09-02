<template>
    <ct-block name="ct_users_user_detail_roles">
        <ct-block name="ct_users_user_detail_card_roles_permissions">
            <mt-card
                position-identifier="ct-users-user-detail-roles-permissions"
                :title="translate('ct-users.user-detail.labelRolesPermissionsCard')"
                :is-loading="isLoading"
            >
                <ct-block name="ct_users_user_detail_roles_permissions_grid">
                    <div v-if="user" class="ct-users-user-detail__grid ct-users-user-detail__roles-permissions-grid">
                        <ct-block name="ct_users_user_detail_grid_content_acl_roles">
                            <mt-entity-select
                                v-tooltip="{
                                    showDelay: 300,
                                    message: translate('ct-users.user-detail.disabledRoleSelectWarning'),
                                    disabled: !user.admin || !acl.can('users_and_permissions.editor'),
                                }"
                                :model-value="aclRoleIds"
                                name="ct-field--user-aclRoles"
                                class="ct-users-user-detail__grid-aclRoles"
                                :label="translate('ct-users.user-detail.labelRoles')"
                                :disabled="user.admin || !acl.can('users_and_permissions.editor') || undefined"
                                label-property="name"
                                entity="acl_role"
                                :repository="aclRoleRepositoryFactory"
                                enable-multi-selection
                                @item-add="onAclRoleAdd"
                                @item-remove="onAclRoleRemove"
                                @update:model-value="onAclRolesUpdate"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_grid_content_job_title">
                            <mt-entity-select
                                :model-value="positionIds"
                                entity="position"
                                label-property="name"
                                name="ct-field--user-positions"
                                class="ct-users-user-detail__grid-jobTitle"
                                enable-multi-selection
                                :criteria="positionCriteria"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                :label="translate('ct-users.user-detail.labelPositions')"
                                :placeholder="translate('ct-users.user-detail.placeholderPositions')"
                                @item-add="onPositionAdd"
                                @item-remove="onPositionRemove"
                                @update:model-value="onPositionsUpdate"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_grid_content_acl_is_admin">
                            <mt-switch
                                v-model="user.admin"
                                name="ct-field--user-admin"
                                class="ct-users-user-detail__grid-is-admin"
                                :label="translate('ct-users.user-detail.labelAdministrator')"
                                :disabled="isCurrentUser || !acl.can('users_and_permissions.editor') || undefined"
                            />
                        </ct-block>
                    </div>
                </ct-block>
            </mt-card>
        </ct-block>
    </ct-block>
</template>

<script setup>
import { inject } from 'vue';

const {
    user,
    isLoading,
    acl,
    translate,
    aclRoleIds,
    aclRoleRepositoryFactory,
    onAclRoleAdd,
    onAclRoleRemove,
    onAclRolesUpdate,
    positionIds,
    positionCriteria,
    onPositionAdd,
    onPositionRemove,
    onPositionsUpdate,
    isCurrentUser,
} = inject('ctUsersUserDetailContext');

ctDefinePublic({});
</script>
