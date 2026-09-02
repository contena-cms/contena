<template>
    <div class="ct-users-user-detail">
        <ct-block name="ct_users_user_detail">
            <ct-block name="ct_users_user_detail_content">
                <ct-card-view>
                    <div class="ct-users-user-detail__content">
                        <ct-block name="ct_users_user_detail_card_basic_information">
                            <mt-card
                                position-identifier="ct-users-user-detail"
                                :title="$t('ct-users.user-detail.labelCard')"
                                :is-loading="isLoading"
                            >
                                <ct-block name="ct_users_user_detail_content_grid">
                                    <div
                                        v-if="user"
                                        class="ct-users-user-detail__grid ct-users-user-detail__information-grid"
                                    >
                                        <ct-block name="ct_users_user_detail_content_name">
                                            <mt-text-field
                                                v-model="user.name"
                                                name="ct-field--user-name"
                                                class="ct-users-user-detail__grid-name"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                :error="userNameError"
                                                required
                                                :label="$t('ct-users.user-detail.labelName')"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_content_user_code">
                                            <template v-if="user && user.userCode">
                                                <mt-text-field
                                                    :model-value="user.userCode"
                                                    name="ct-field--user-userCode"
                                                    class="ct-users-user-detail__grid-user-code"
                                                    disabled
                                                    :label="$t('ct-users.user-detail.labelUserCode')"
                                                />
                                            </template>
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_content_phone_number">
                                            <mt-text-field
                                                v-model="user.phoneNumber"
                                                name="ct-field--user-phoneNumber"
                                                class="ct-users-user-detail__grid-phoneNumber"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                :error="userPhoneNumberError"
                                                :label="$t('ct-users.user-detail.labelPhoneNumber')"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_content_gender">
                                            <ct-data-dictionary-select
                                                v-model="user.gender"
                                                technical-name="core.gender"
                                                name="ct-field--user-gender"
                                                class="ct-users-user-detail__grid-gender"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                :label="$t('ct-users.user-detail.labelGender')"
                                                :placeholder="$t('ct-users.user-detail.labelGenderPlaceholder')"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_content_email">
                                            <mt-text-field
                                                v-model="user.email"
                                                name="ct-field--user-email"
                                                class="ct-users-user-detail__grid-eMail"
                                                :error="userEmailError"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                required
                                                :label="$t('ct-users.user-detail.labelEmail')"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_content_username">
                                            <mt-text-field
                                                v-model="user.username"
                                                name="ct-field--user-username"
                                                class="ct-users-user-detail__grid-username"
                                                :error-message="
                                                    isUsernameUsed ? $t('ct-users.user-detail.errorUsernameUsed') : ''
                                                "
                                                :error="userUsernameError"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                required
                                                :label="$t('ct-users.user-detail.labelUsername')"
                                                @update:model-value="checkUsername"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_content_password">
                                            <mt-password-field
                                                v-model="user.password"
                                                name="ct-field--user-password"
                                                :label="$t('ct-users.user-detail.labelPassword')"
                                                class="ct-users-user-detail__grid-password"
                                                :error="userPasswordError"
                                                required
                                                :password-toggle-able="true"
                                                autocomplete="new-password"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_grid_content_active">
                                            <mt-switch
                                                v-model="user.active"
                                                name="ct-field--user-active"
                                                class="ct-users-user-detail__grid-active"
                                                :label="$t('ct-users.filter.active')"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                            />
                                        </ct-block>
                                    </div>
                                </ct-block>

                                <ct-block name="ct_users_user_detail_content_tags">
                                    <ct-entity-tag-select
                                        v-if="user"
                                        v-model:entity-collection="user.tags"
                                        name="ct-field--user-tags"
                                        class="ct-users-user-detail__tags"
                                        :label="$t('ct-users.user-detail.labelTags')"
                                        :placeholder="$t('ct-users.user-detail.placeholderTags')"
                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                    />
                                </ct-block>
                            </mt-card>
                        </ct-block>

                        <ct-block name="ct_users_user_detail_card_user_interface">
                            <mt-card
                                position-identifier="ct-users-user-detail-user-interface"
                                :title="$t('ct-users.user-detail.labelUserInterface')"
                                :is-loading="isLoading"
                            >
                                <ct-block name="ct_users_user_detail_user_interface_grid">
                                    <div
                                        v-if="user"
                                        class="ct-users-user-detail__grid ct-users-user-detail__user-interface-grid"
                                    >
                                        <ct-block name="ct_users_user_detail_grid_content_language">
                                            <mt-select
                                                v-model="user.localeId"
                                                name="ct-field--user-localeId"
                                                class="ct-users-user-detail__grid-language"
                                                :label="$t('ct-users.user-detail.labelLanguage')"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                :error="userLocaleIdError"
                                                :options="localeOptions"
                                                required
                                                :placeholder="$t('ct-users.user-detail.labelLanguagePlaceholder')"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_grid_content_timezone">
                                            <mt-select
                                                v-model="user.timeZone"
                                                name="ct-field--user-timeZone"
                                                class="ct-users-user-detail__grid-timezone"
                                                :options="timezoneOptions"
                                                required
                                                :label="$t('ct-users.user-detail.labelTimezone')"
                                                :disabled="!acl.can('user.update_profile') || undefined"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_users_user_detail_content_media_upload">
                                            <ct-upload-listener
                                                :upload-tag="user.id"
                                                auto-upload
                                                @media-upload-finish="setMediaItem"
                                            />
                                            <ct-media-upload-v2
                                                class="ct-users-user-detail__grid-profile-picture"
                                                :source="avatarMedia"
                                                :label="$t('ct-users.user-detail.labelProfilePicture')"
                                                :upload-tag="user.id"
                                                :allow-multi-select="false"
                                                :source-context="user"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                default-folder="user"
                                                @media-drop="onDropMedia"
                                                @media-upload-sidebar-open="onOpenMedia"
                                                @media-upload-remove-image="onUnlinkLogo"
                                            />
                                        </ct-block>
                                    </div>
                                </ct-block>
                            </mt-card>
                        </ct-block>

                        <ct-block name="ct_users_user_detail_card_roles_permissions">
                            <mt-card
                                position-identifier="ct-users-user-detail-roles-permissions"
                                :title="$t('ct-users.user-detail.labelRolesPermissionsCard')"
                                :is-loading="isLoading"
                            >
                                <ct-block name="ct_users_user_detail_roles_permissions_grid">
                                    <div
                                        v-if="user"
                                        class="ct-users-user-detail__grid ct-users-user-detail__roles-permissions-grid"
                                    >
                                        <ct-block name="ct_users_user_detail_grid_content_acl_roles">
                                            <mt-entity-select
                                                v-tooltip="{
                                                    showDelay: 300,
                                                    message: $t('ct-users.user-detail.disabledRoleSelectWarning'),
                                                    disabled: !user.admin || !acl.can('users_and_permissions.editor'),
                                                }"
                                                :model-value="aclRoleIds"
                                                name="ct-field--user-aclRoles"
                                                class="ct-users-user-detail__grid-aclRoles"
                                                :label="$t('ct-users.user-detail.labelRoles')"
                                                :disabled="
                                                    user.admin || !acl.can('users_and_permissions.editor') || undefined
                                                "
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
                                                :label="$t('ct-users.user-detail.labelPositions')"
                                                :placeholder="$t('ct-users.user-detail.placeholderPositions')"
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
                                                :label="$t('ct-users.user-detail.labelAdministrator')"
                                                :disabled="
                                                    isCurrentUser || !acl.can('users_and_permissions.editor') || undefined
                                                "
                                            />
                                        </ct-block>
                                    </div>
                                </ct-block>
                            </mt-card>
                        </ct-block>

                        <ct-block name="ct_users_user_detail_card_integrations">
                            <mt-card
                                :title="$t('ct-users.user-detail.labelIntegrationsCard')"
                                position-identifier="ct-users-user-detail-integrations"
                            >
                                <template #grid>
                                    <ct-block name="ct_users_user_detail_key_grid">
                                        <mt-data-table
                                            class="ct-users-user-detail__integration-table"
                                            :caption="$t('ct-users.user-detail.labelIntegrationsCard')"
                                            :data-source="integrations"
                                            :columns="integrationColumns"
                                            :is-loading="isIntegrationsLoading || isLoading"
                                            :pagination-total-items="integrations.length"
                                            :current-page="1"
                                            :pagination-limit="25"
                                            :disable-edit="true"
                                            :disable-delete="!acl.can('users_and_permissions.editor') || undefined"
                                            :additional-context-buttons="integrationContextButtons"
                                            @context-select="onIntegrationContextSelect"
                                            @item-delete="onIntegrationDelete"
                                        >
                                            <template #toolbar>
                                                <ct-block name="ct_users_user_detail_grid_toolbar">
                                                    <mt-button
                                                        variant="secondary"
                                                        size="default"
                                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                        @click.stop.prevent="addAccessKey"
                                                    >
                                                        {{ $t('ct-users.user-detail.addAccessKey') }}
                                                    </mt-button>
                                                </ct-block>
                                            </template>

                                            <template #empty-state>
                                                <mt-empty-state
                                                    icon="regular-key"
                                                    :headline="$t('ct-users.user-detail.noAccessKeysTitle')"
                                                    :description="$t('ct-users.user-detail.noAccessKeysSubline')"
                                                />
                                            </template>
                                        </mt-data-table>
                                    </ct-block>
                                </template>
                            </mt-card>
                        </ct-block>
                    </div>
                </ct-card-view>

                <ct-block name="ct_users_user_detail_grid_inner_slot_media_modal">
                    <ct-media-modal-v2
                        v-if="showMediaModal"
                        :allow-multi-select="false"
                        :initial-folder-id="mediaDefaultFolderId"
                        entity-context="user"
                        @modal-close="showMediaModal = false"
                        @media-modal-selection-change="onMediaSelectionChange"
                    />
                </ct-block>

                <ct-block name="ct_users_user_detail_grid_inner_slot_delete_modal">
                    <ct-modal v-if="showDeleteModal" :title="$t('global.default.warning')" @modal-close="onCloseDeleteModal">
                        <ct-block name="ct_users_user_detail_grid_inner_slot_delete_modal_confirm_text">
                            <p>
                                {{ $t('ct-users.user-detail.modal.confirmDelete') }}
                            </p>
                        </ct-block>

                        <template #modal-footer>
                            <ct-block name="ct_users_user_detail_grid_inner_slot_delete_modal_footer">
                                <mt-button size="small" variant="secondary" @click="onCloseDeleteModal">
                                    {{ $t('global.default.cancel') }}
                                </mt-button>

                                <mt-button size="small" variant="critical" @click="onConfirmDelete(showDeleteModal)">
                                    {{ $t('global.default.delete') }}
                                </mt-button>
                            </ct-block>
                        </template>
                    </ct-modal>
                </ct-block>

                <ct-block name="ct_users_user_detail_detail_modal">
                    <ct-modal
                        v-if="currentIntegration"
                        size="550px"
                        class="ct-users-user-detail__detail"
                        :is-loading="isModalLoading"
                        :title="showSecretAccessKey ? $t('global.default.warning') : $t('global.default.edit')"
                        @modal-close="onCloseDetailModal"
                    >
                        <ct-block name="ct_users_user_detail_detail_modal_inner_field_access_key">
                            <mt-text-field
                                v-model="currentIntegration.accessKey"
                                :label="$t('ct-users.user-detail.modal.idFieldLabel')"
                                :disabled="true"
                                :copyable="true"
                                :copyable-tooltip="true"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_detail_modal_inner_field_secret_access_key">
                            <ct-block name="ct_users_user_detail_detail_modal_inner_field_secret_access_key_field">
                                <mt-text-field
                                    v-if="showSecretAccessKey"
                                    v-model="currentIntegration.secretAccessKey"
                                    :label="$t('ct-users.user-detail.modal.secretFieldLabel')"
                                    :disabled="true"
                                    :password-toggle-able="false"
                                    :copyable="showSecretAccessKey"
                                    :copyable-tooltip="true"
                                />

                                <mt-password-field
                                    v-else
                                    v-model="currentIntegration.secretAccessKey"
                                    :label="$t('ct-users.user-detail.modal.secretFieldLabel')"
                                    :disabled="true"
                                    :password-toggle-able="false"
                                    :copyable="showSecretAccessKey"
                                    :copyable-tooltip="true"
                                    autocomplete="off"
                                />
                            </ct-block>

                            <ct-block name="ct_users_user_detail_detail_modal_inner_field_secret_access_key_button">
                                <mt-button
                                    v-if="!showSecretAccessKey"
                                    class="ct-users-user-detail__secret-help-text-button ct-field"
                                    variant="critical"
                                    :block="true"
                                    @click.stop.prevent="addAccessKey"
                                >
                                    {{ $t('ct-users.user-detail.modal.buttonCreateNewApiKeys') }}
                                </mt-button>
                            </ct-block>

                            <ct-block name="ct_users_user_detail_detail_modal_inner_field_help_text">
                                <mt-banner
                                    v-if="!showSecretAccessKey"
                                    variant="attention"
                                    class="ct-users-user-detail__secret-help-text-alert"
                                >
                                    {{ $t('ct-users.user-detail.modal.hintCreateNewApiKeys') }}
                                </mt-banner>
                            </ct-block>
                        </ct-block>

                        <ct-block name="ct_users_user_detail_detail_modal_inner_help_text">
                            <template v-if="!showSecretAccessKey"
                                ><!-- Keeps the conditional chain connected across ct-block. --></template
                            >
                            <mt-banner v-else variant="attention" class="ct-users-user-detail__secret-help-text-alert">
                                {{ $t('ct-users.user-detail.modal.secretHelpText') }}
                            </mt-banner>
                        </ct-block>

                        <template #modal-footer>
                            <ct-block name="ct_users_user_detail_detail_modal_inner_footer">
                                <ct-block name="ct_users_user_detail_detail_modal_inner_footer_cancel">
                                    <mt-button
                                        size="small"
                                        :disabled="isModalLoading || undefined"
                                        variant="secondary"
                                        @click="onCloseDetailModal"
                                    >
                                        {{ $t('global.default.cancel') }}
                                    </mt-button>
                                </ct-block>

                                <ct-block name="ct_users_user_detail_detail_modal_inner_footer_apply">
                                    <mt-button
                                        size="small"
                                        class="ct-users-user-detail__save-action"
                                        :disabled="(isModalLoading && !!currentIntegration.label) || undefined"
                                        variant="primary"
                                        @click="onSaveIntegration"
                                    >
                                        {{
                                            showSecretAccessKey
                                                ? $t('ct-users.user-detail.modal.buttonApply')
                                                : $t('ct-users.user-detail.modal.buttonApplyEdit')
                                        }}
                                    </mt-button>
                                </ct-block>
                            </ct-block>
                        </template>
                    </ct-modal>
                </ct-block>
            </ct-block>
        </ct-block>
    </div>
</template>

<script setup>
import { computed, inject, ref } from 'vue';
const {
    user,
    userRepository,
    currentUser,
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
    isSaveSuccessful,
    onSave: parentOnSave,
    saveUser: parentSaveUser,
    integrations,
    integrationColumns,
    integrationContextButtons,
    isIntegrationsLoading,
    addAccessKey,
    onIntegrationContextSelect,
    onIntegrationDelete,
} = Contena.Component.getExtensionParentSetup();
const numberRangeService = inject('numberRangeService');
const userCodePreview = ref('');
const userPasswordError = computed(() => {
    const entity = user.value;
    if (!entity || typeof entity.getEntityName !== 'function') {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'password');
});
const loadUser = async () => {
    if (user.value) {
        return;
    }

    user.value = userRepository.value.create(Contena.Context.api);
    user.value.active = true;
    user.value.admin = false;

    const { number } = await numberRangeService.reserve('user', true);
    user.value.userCode = number;
    userCodePreview.value = number;
};
const onSave = () => {
    if (!user.value.localeId) {
        user.value.localeId = currentUser.value.localeId;
    }

    return parentOnSave.value();
};
const saveUser = async (context) => {
    if (user.value.userCode === userCodePreview.value) {
        const { number } = await numberRangeService.reserve('user');
        user.value.userCode = number;
        userCodePreview.value = 'reserved';
    }

    return parentSaveUser.value(context);
};

void loadUser();

ctDefinePublic({
    isSaveSuccessful,
    userPasswordError,
    loadUser,
    onSave,
    saveUser,
});

defineExpose({
    isSaveSuccessful,
    userPasswordError,
    loadUser,
    onSave,
    saveUser,
});
</script>
