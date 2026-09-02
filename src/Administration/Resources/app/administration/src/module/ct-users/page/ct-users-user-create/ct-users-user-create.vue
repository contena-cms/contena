<template>
    <ct-block name="ct_users_user_create_page">
        <ct-page class="ct-users-user-detail">
            <template #smart-bar-header>
                <ct-block name="ct_users_user_create_header">
                    <h2 v-if="!isLoading">{{ fullName }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_users_user_create_actions">
                    <mt-button variant="secondary" @click="onCancel">
                        {{ $t('global.default.cancel') }}
                    </mt-button>
                    <ct-button-process
                        v-model:process-success="isSaveSuccessful"
                        variant="primary"
                        :is-loading="isLoading"
                        :disabled="isLoading || !acl.can('users_and_permissions.creator') || undefined"
                        @click.prevent="onSave"
                        @update:process-success="saveFinish"
                    >
                        {{ $t('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_users_user_create_content">
                    <ct-block name="ct_users_user_detail">
                        <ct-block name="ct_users_user_detail_content">
                            <ct-card-view>
                                <mt-tabs
                                    :default-item="$route.name"
                                    :items="detailTabs"
                                    position-identifier="ct-users-user-detail-tabs"
                                    small
                                />
                                <ct-block name="ct_users_user_detail_view">
                                    <router-view />
                                </ct-block>
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
                                <ct-modal
                                    v-if="showDeleteModal"
                                    :title="$t('global.default.warning')"
                                    @modal-close="onCloseDeleteModal"
                                >
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

                                            <mt-button
                                                size="small"
                                                variant="critical"
                                                @click="onConfirmDelete(showDeleteModal)"
                                            >
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
                                        <ct-block
                                            name="ct_users_user_detail_detail_modal_inner_field_secret_access_key_field"
                                        >
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

                                        <ct-block
                                            name="ct_users_user_detail_detail_modal_inner_field_secret_access_key_button"
                                        >
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
                                        <mt-banner
                                            v-else
                                            variant="attention"
                                            class="ct-users-user-detail__secret-help-text-alert"
                                        >
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
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import { computed, inject, provide, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
const {
    user,
    userRepository,
    currentUser,
    userNameError,
    userPhoneNumberError,
    userEmailError,
    userUsernameError,
    userLocaleIdError,
    isUsernameUsed,
    checkUsername,
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
    saveFinish,
    onCancel,
    fullName,
    isLoading,
    acl,
    localeOptions,
    timezoneOptions,
    avatarMedia,
    showMediaModal,
    mediaDefaultFolderId,
    setMediaItem,
    onDropMedia,
    onOpenMedia,
    onUnlinkLogo,
    onMediaSelectionChange,
    isCurrentUser,
    integrations,
    integrationColumns,
    integrationContextButtons,
    isIntegrationsLoading,
    currentIntegration,
    isModalLoading,
    showSecretAccessKey,
    showDeleteModal,
    addAccessKey,
    onIntegrationContextSelect,
    onIntegrationDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    onCloseDetailModal,
    onSaveIntegration,
} = Contena.Component.getExtensionParentSetup();
const numberRangeService = inject('numberRangeService');
const router = useRouter();
const { t } = useI18n();
const userCodePreview = ref('');
const userPasswordError = computed(() => {
    const entity = user.value;
    if (!entity || typeof entity.getEntityName !== 'function') {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'password');
});
const setPassword = (password) => {
    if (typeof password === 'string' && password.length <= 0) {
        delete user.value.password;
        return;
    }

    user.value.password = password;
};

provide('ctUsersUserDetailContext', {
    user,
    isLoading,
    acl,
    translate: t,
    userNameError,
    userPhoneNumberError,
    userEmailError,
    userUsernameError,
    userPasswordError,
    userLocaleIdError,
    isUsernameUsed,
    checkUsername,
    isCurrentUser,
    setPassword,
    localeOptions,
    timezoneOptions,
    avatarMedia,
    setMediaItem,
    onDropMedia,
    onOpenMedia,
    onUnlinkLogo,
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
    integrations,
    integrationColumns,
    integrationContextButtons,
    addAccessKey,
    onIntegrationContextSelect,
    onIntegrationDelete,
});
const detailTabs = computed(() => [
    {
        label: t('ct-users.user-detail.labelCard'),
        name: 'ct.users.create.base',
        onClick: () => void router.push({ name: 'ct.users.create.base' }),
    },
    {
        label: t('ct-users.user-detail.labelUserInterface'),
        name: 'ct.users.create.interface',
        onClick: () => void router.push({ name: 'ct.users.create.interface' }),
    },
    {
        label: t('ct-users.user-detail.labelRolesPermissionsCard'),
        name: 'ct.users.create.roles',
        onClick: () => void router.push({ name: 'ct.users.create.roles' }),
    },
    {
        label: t('ct-users.user-detail.labelIntegrationsCard'),
        name: 'ct.users.create.integrations',
        onClick: () => void router.push({ name: 'ct.users.create.integrations' }),
    },
]);
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
const onSave = async () => {
    if (!user.value.localeId) {
        user.value.localeId = currentUser.value.localeId;
    }

    await parentOnSave.value();
    if (isSaveSuccessful.value) {
        await router.push({ name: 'ct.users.detail', params: { id: user.value.id } });
    }
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
    user,
    isLoading,
    fullName,
    onCancel,
    userNameError,
    userPhoneNumberError,
    userEmailError,
    userUsernameError,
    userLocaleIdError,
    isUsernameUsed,
    localeOptions,
    timezoneOptions,
    avatarMedia,
    showMediaModal,
    mediaDefaultFolderId,
    setMediaItem,
    onDropMedia,
    onOpenMedia,
    onUnlinkLogo,
    onMediaSelectionChange,
    isCurrentUser,
    integrations,
    integrationColumns,
    integrationContextButtons,
    isIntegrationsLoading,
    currentIntegration,
    isModalLoading,
    showSecretAccessKey,
    showDeleteModal,
    onAclRoleAdd,
    onAclRoleRemove,
    onAclRolesUpdate,
    onPositionAdd,
    onPositionRemove,
    onPositionsUpdate,
    aclRoleIds,
    positionIds,
    positionCriteria,
    aclRoleRepositoryFactory,
    addAccessKey,
    onIntegrationContextSelect,
    onIntegrationDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    onCloseDetailModal,
    onSaveIntegration,
    detailTabs,
});

defineExpose({
    isSaveSuccessful,
    userPasswordError,
    loadUser,
    onSave,
    saveUser,
    user,
    isLoading,
    fullName,
    onCancel,
    userNameError,
    userPhoneNumberError,
    userEmailError,
    userUsernameError,
    userLocaleIdError,
    isUsernameUsed,
    localeOptions,
    timezoneOptions,
    avatarMedia,
    showMediaModal,
    mediaDefaultFolderId,
    setMediaItem,
    onDropMedia,
    onOpenMedia,
    onUnlinkLogo,
    onMediaSelectionChange,
    isCurrentUser,
    integrations,
    integrationColumns,
    integrationContextButtons,
    isIntegrationsLoading,
    currentIntegration,
    isModalLoading,
    showSecretAccessKey,
    showDeleteModal,
    onAclRoleAdd,
    onAclRoleRemove,
    onAclRolesUpdate,
    onPositionAdd,
    onPositionRemove,
    onPositionsUpdate,
    aclRoleIds,
    positionIds,
    positionCriteria,
    aclRoleRepositoryFactory,
    addAccessKey,
    onIntegrationContextSelect,
    onIntegrationDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    onCloseDetailModal,
    onSaveIntegration,
    detailTabs,
});
</script>
