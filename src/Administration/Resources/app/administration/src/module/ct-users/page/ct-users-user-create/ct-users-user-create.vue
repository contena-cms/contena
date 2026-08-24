<template>
    <ct-block name="sw_users_user_detail">
        <a-drawer
            root-class-name="ct-users-user-detail__drawer"
            :open="true"
            placement="right"
            width="min(920px, calc(100vw - 48px))"
            :title="fullName"
            @close="onCancel"
        >
            <template #extra>
                <ct-block name="sw_users_user_detail_actions">
                    <a-space>
                        <a-button @click="onCancel">{{ translate('global.default.cancel') }}</a-button>
                        <a-button
                            class="ct-users-user-detail__save-action"
                            type="primary"
                            :loading="isLoading"
                            :disabled="isLoading || !canEditUser"
                            @click="onSave"
                        >
                            <template #icon><ct-icon name="SaveOutlined" /></template>
                            {{ translate('global.default.save') }}
                        </a-button>
                    </a-space>
                </ct-block>
            </template>

            <ct-block name="sw_users_user_detail_content">
                            <div class="ct-users-user-detail__content">
                                <ct-block name="sw_users_user_detail_card_basic_information">
                                    <a-card :title="translate('ct-users.user-detail.labelCard')" :loading="isLoading">
                                        <ct-block name="sw_users_user_detail_content_grid">
                                            <a-form v-if="user" class="ct-users-user-detail__form" layout="vertical">
                                                <div class="ct-users-user-detail__grid">
                                                    <ct-block name="sw_users_user_detail_content_name">
                                                        <a-form-item
                                                            class="ct-users-user-detail__grid-name"
                                                            required
                                                            :label="translate('ct-users.user-detail.labelName')"
                                                            :validate-status="userNameError ? 'error' : undefined"
                                                            :help="getErrorMessage(userNameError)"
                                                        >
                                                            <a-input
                                                                v-model:value="user.name"
                                                                name="ct-field--user-name"
                                                                :disabled="!canEditUser"
                                                            />
                                                        </a-form-item>
                                                    </ct-block>

                                                    <ct-block name="sw_users_user_detail_content_user_code">
                                                        <a-form-item
                                                            v-if="user.userCode"
                                                            class="ct-users-user-detail__grid-user-code"
                                                            :label="translate('ct-users.user-detail.labelUserCode')"
                                                        >
                                                            <a-input :value="user.userCode" name="ct-field--user-userCode" disabled />
                                                        </a-form-item>
                                                    </ct-block>

                                                    <ct-block name="sw_users_user_detail_content_phone_number">
                                                        <a-form-item
                                                            class="ct-users-user-detail__grid-phoneNumber"
                                                            :label="translate('ct-users.user-detail.labelPhoneNumber')"
                                                            :validate-status="userPhoneNumberError ? 'error' : undefined"
                                                            :help="getErrorMessage(userPhoneNumberError)"
                                                        >
                                                            <a-input
                                                                v-model:value="user.phoneNumber"
                                                                name="ct-field--user-phoneNumber"
                                                                :disabled="!canEditUser"
                                                            />
                                                        </a-form-item>
                                                    </ct-block>

                                                    <ct-block name="sw_users_user_detail_content_gender">
                                                        <a-form-item
                                                            class="ct-users-user-detail__grid-gender"
                                                            :label="translate('ct-users.user-detail.labelGender')"
                                                        >
                                                            <a-select
                                                                v-model:value="user.gender"
                                                                name="ct-field--user-gender"
                                                                allow-clear
                                                                :options="genderOptions"
                                                                :loading="isGenderLoading"
                                                                :disabled="!canEditUser"
                                                                :placeholder="
                                                                    translate('ct-users.user-detail.labelGenderPlaceholder')
                                                                "
                                                            />
                                                        </a-form-item>
                                                    </ct-block>

                                                    <ct-block name="sw_users_user_detail_content_email">
                                                        <a-form-item
                                                            class="ct-users-user-detail__grid-eMail"
                                                            required
                                                            :label="translate('ct-users.user-detail.labelEmail')"
                                                            :validate-status="userEmailError ? 'error' : undefined"
                                                            :help="getErrorMessage(userEmailError)"
                                                        >
                                                            <a-input
                                                                v-model:value="user.email"
                                                                name="ct-field--user-email"
                                                                type="email"
                                                                :disabled="!canEditUser"
                                                            />
                                                        </a-form-item>
                                                    </ct-block>

                                                    <ct-block name="sw_users_user_detail_content_username">
                                                        <a-form-item
                                                            class="ct-users-user-detail__grid-username"
                                                            required
                                                            :label="translate('ct-users.user-detail.labelUsername')"
                                                            :validate-status="userUsernameError || isUsernameUsed ? 'error' : undefined"
                                                            :help="
                                                                isUsernameUsed
                                                                    ? translate('ct-users.user-detail.errorUsernameUsed')
                                                                    : getErrorMessage(userUsernameError)
                                                            "
                                                        >
                                                            <a-input
                                                                v-model:value="user.username"
                                                                name="ct-field--user-username"
                                                                :disabled="!canEditUser"
                                                                @blur="checkUsername"
                                                            />
                                                        </a-form-item>
                                                    </ct-block>

                                                    <ct-block name="sw_users_user_detail_content_password">
                                                        <a-form-item
                                                            class="ct-users-user-detail__grid-password"
                                                            :required="!$route.params.id"
                                                            :label="translate('ct-users.user-detail.labelPassword')"
                                                            :validate-status="userPasswordError ? 'error' : undefined"
                                                            :help="getErrorMessage(userPasswordError)"
                                                        >
                                                            <a-input-password
                                                                :value="user.password"
                                                                name="ct-field--user-password"
                                                                autocomplete="new-password"
                                                                :disabled="!canEditUser"
                                                                @update:value="setPassword"
                                                            />
                                                        </a-form-item>
                                                    </ct-block>

                                                    <ct-block name="sw_users_user_detail_grid_content_active">
                                                        <a-form-item
                                                            class="ct-users-user-detail__grid-active"
                                                            :label="translate('ct-users.user-detail.labelActive')"
                                                        >
                                                            <a-switch
                                                                v-model:checked="user.active"
                                                                name="ct-field--user-active"
                                                                :disabled="
                                                                    isCurrentUser || !canEditUser
                                                                "
                                                            />
                                                        </a-form-item>
                                                    </ct-block>
                                                </div>
                                            </a-form>
                                        </ct-block>

                                        <ct-block name="sw_users_user_detail_content_tags">
                                            <ct-entity-tag-select
                                                v-if="user"
                                                v-model:entity-collection="user.tags"
                                                name="ct-field--user-tags"
                                                class="ct-users-user-detail__tags"
                                                :label="translate('ct-users.user-detail.labelTags')"
                                                :placeholder="translate('ct-users.user-detail.placeholderTags')"
                                                :disabled="!canEditUser"
                                            />
                                        </ct-block>
                                    </a-card>
                                </ct-block>

                                <ct-block name="sw_users_user_detail_card_user_interface">
                                    <a-card :title="translate('ct-users.user-detail.labelUserInterface')" :loading="isLoading">
                                        <a-form v-if="user" layout="vertical">
                                            <div class="ct-users-user-detail__grid ct-users-user-detail__interface-grid">
                                                <ct-block name="sw_users_user_detail_grid_content_language">
                                                    <a-form-item
                                                        class="ct-users-user-detail__grid-language"
                                                        required
                                                        :label="translate('ct-users.user-detail.labelLanguage')"
                                                        :validate-status="userLocaleIdError ? 'error' : undefined"
                                                        :help="getErrorMessage(userLocaleIdError)"
                                                    >
                                                        <a-select
                                                            v-model:value="user.localeId"
                                                            name="ct-field--user-localeId"
                                                            show-search
                                                            option-filter-prop="label"
                                                            :options="localeOptions"
                                                            :placeholder="translate('ct-users.user-detail.labelLanguagePlaceholder')"
                                                            :disabled="!canEditUser"
                                                        />
                                                    </a-form-item>
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_grid_content_timezone">
                                                    <a-form-item
                                                        class="ct-users-user-detail__grid-timezone"
                                                        required
                                                        :label="translate('ct-users.user-detail.labelTimezone')"
                                                    >
                                                        <a-select
                                                            v-model:value="user.timeZone"
                                                            name="ct-field--user-timeZone"
                                                            show-search
                                                            option-filter-prop="label"
                                                            :options="antTimezoneOptions"
                                                            :disabled="!canEditUser"
                                                        />
                                                    </a-form-item>
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_content_media_upload">
                                                    <div class="ct-users-user-detail__media-field">
                                                        <ct-upload-listener
                                                            :upload-tag="user.id"
                                                            auto-upload
                                                            @media-upload-finish="setMediaItem"
                                                        />
                                                        <ct-media-upload-v2
                                                            class="ct-users-user-detail__grid-profile-picture"
                                                            :source="avatarMedia"
                                                            :label="translate('ct-users.user-detail.labelProfilePicture')"
                                                            :upload-tag="user.id"
                                                            :allow-multi-select="false"
                                                            :source-context="user"
                                                            :disabled="!canEditUser"
                                                            default-folder="user"
                                                            @media-drop="onDropMedia"
                                                            @media-upload-sidebar-open="onOpenMedia"
                                                            @media-upload-remove-image="onUnlinkLogo"
                                                        />
                                                    </div>
                                                </ct-block>
                                            </div>
                                        </a-form>
                                    </a-card>
                                </ct-block>

                                <ct-block name="sw_users_user_detail_card_roles_permissions">
                                    <a-card
                                        :title="translate('ct-users.user-detail.labelRolesPermissionsCard')"
                                        :loading="isLoading"
                                    >
                                        <a-form v-if="user" layout="vertical">
                                            <div class="ct-users-user-detail__grid">
                                                <ct-block name="sw_users_user_detail_grid_content_acl_roles">
                                                    <a-form-item
                                                        class="ct-users-user-detail__grid-aclRoles"
                                                        :label="translate('ct-users.user-detail.labelRoles')"
                                                    >
                                                        <a-select
                                                            :value="aclRoleIds"
                                                            mode="multiple"
                                                            show-search
                                                            option-filter-prop="label"
                                                            :options="roleOptions"
                                                            :disabled="
                                                                user.admin || !canEditUser
                                                            "
                                                            @change="onAclRolesUpdate"
                                                        />
                                                    </a-form-item>
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_grid_content_job_title">
                                                    <a-form-item
                                                        class="ct-users-user-detail__grid-jobTitle"
                                                        :label="translate('ct-users.user-detail.labelPositions')"
                                                    >
                                                        <a-select
                                                            :value="positionIds"
                                                            mode="multiple"
                                                            show-search
                                                            option-filter-prop="label"
                                                            :options="positionOptions"
                                                            :placeholder="translate('ct-users.user-detail.placeholderPositions')"
                                                            :disabled="!canEditUser"
                                                            @change="onPositionsUpdate"
                                                        />
                                                    </a-form-item>
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_grid_content_acl_is_admin">
                                                    <a-form-item
                                                        class="ct-users-user-detail__grid-is-admin"
                                                        :label="translate('ct-users.user-detail.labelAdministrator')"
                                                    >
                                                        <a-switch
                                                            v-model:checked="user.admin"
                                                            :disabled="
                                                                isCurrentUser || !canEditUser
                                                            "
                                                        />
                                                    </a-form-item>
                                                </ct-block>
                                            </div>
                                        </a-form>
                                    </a-card>
                                </ct-block>

                                <ct-block name="sw_users_user_detail_card_integrations">
                                    <a-card v-if="$route.params.id" :title="translate('ct-users.user-detail.labelIntegrationsCard')">
                                        <template #extra>
                                            <a-button
                                                :disabled="!canEditUser"
                                                @click="addAccessKey"
                                            >
                                                <template #icon><ct-icon name="PlusOutlined" /></template>
                                                {{ translate('ct-users.user-detail.addAccessKey') }}
                                            </a-button>
                                        </template>

                                        <a-table
                                            :columns="integrationColumns"
                                            :data-source="integrations"
                                            :loading="isIntegrationsLoading"
                                            :pagination="false"
                                            row-key="id"
                                            size="middle"
                                        >
                                            <template #bodyCell="{ column, record }">
                                                <template v-if="column.key === 'action'">
                                                    <a-space>
                                                        <a-button
                                                            type="text"
                                                            :disabled="!canEditUser"
                                                            @click="onShowDetailModal(record.id)"
                                                        >
                                                            <template #icon><ct-icon name="EditOutlined" /></template>
                                                        </a-button>
                                                        <a-button
                                                            type="text"
                                                            danger
                                                            :disabled="!canEditUser"
                                                            @click="showDeleteModal = record.id"
                                                        >
                                                            <template #icon><ct-icon name="DeleteOutlined" /></template>
                                                        </a-button>
                                                    </a-space>
                                                </template>
                                            </template>
                                            <template #emptyText>
                                                <a-empty :description="translate('ct-users.user-detail.noAccessKeysTitle')" />
                                            </template>
                                        </a-table>
                                    </a-card>
                                </ct-block>
                            </div>
            </ct-block>

                <ct-block name="sw_users_user_detail_grid_inner_slot_media_modal">
                    <ct-media-modal-v2
                        v-if="showMediaModal"
                        :allow-multi-select="false"
                        :initial-folder-id="mediaDefaultFolderId"
                        entity-context="user"
                        @modal-close="showMediaModal = false"
                        @media-modal-selection-change="onMediaSelectionChange"
                    />
                </ct-block>

                <ct-block name="sw_users_user_detail_grid_inner_slot_delete_modal">
                    <a-modal
                        :open="Boolean(showDeleteModal)"
                        :title="translate('global.default.warning')"
                        :ok-text="translate('global.default.delete')"
                        :cancel-text="translate('global.default.cancel')"
                        ok-type="danger"
                        @ok="onConfirmDelete(showDeleteModal)"
                        @cancel="onCloseDeleteModal"
                    >
                        <p>{{ translate('ct-users.user-detail.modal.confirmDelete') }}</p>
                    </a-modal>
                </ct-block>

                <ct-block name="sw_users_user_detail_detail_modal">
                    <a-modal
                        :open="Boolean(currentIntegration)"
                        :title="
                            showSecretAccessKey ? translate('global.default.warning') : translate('global.default.edit')
                        "
                        :confirm-loading="isModalLoading"
                        :ok-text="
                            showSecretAccessKey
                                ? translate('ct-users.user-detail.modal.buttonApply')
                                : translate('ct-users.user-detail.modal.buttonApplyEdit')
                        "
                        :cancel-text="translate('global.default.cancel')"
                        @ok="onSaveIntegration"
                        @cancel="onCloseDetailModal"
                    >
                        <a-form v-if="currentIntegration" layout="vertical">
                            <a-form-item :label="translate('ct-users.user-detail.modal.idFieldLabel')">
                                <a-typography-text :copyable="{ text: currentIntegration.accessKey }">
                                    {{ currentIntegration.accessKey }}
                                </a-typography-text>
                            </a-form-item>
                            <a-form-item :label="translate('ct-users.user-detail.modal.secretFieldLabel')">
                                <a-typography-text
                                    v-if="showSecretAccessKey"
                                    :copyable="{ text: currentIntegration.secretAccessKey }"
                                >
                                    {{ currentIntegration.secretAccessKey }}
                                </a-typography-text>
                                <a-input-password v-else :value="currentIntegration.secretAccessKey" disabled />
                            </a-form-item>
                            <a-button v-if="!showSecretAccessKey" danger block @click="addAccessKey">
                                {{ translate('ct-users.user-detail.modal.buttonCreateNewApiKeys') }}
                            </a-button>
                            <a-alert
                                class="ct-users-user-detail__secret-help-text-alert"
                                type="warning"
                                show-icon
                                :message="
                                    showSecretAccessKey
                                        ? translate('ct-users.user-detail.modal.secretHelpText')
                                        : translate('ct-users.user-detail.modal.hintCreateNewApiKeys')
                                "
                            />
                        </a-form>
                    </a-modal>
                </ct-block>
        </a-drawer>
    </ct-block>
</template>

<script setup>
defineProps({});

import { computed, inject, ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
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
const saveFinish = () => {
    isSaveSuccessful.value = false;
    void router.push({
        name: 'ct.users.user.detail',
        params: { id: user.value.id },
    });
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

swDefinePublic({
    userPasswordError,
    loadUser,
    saveFinish,
    onSave,
    saveUser,
});

defineExpose({
    userPasswordError,
    loadUser,
    saveFinish,
    onSave,
    saveUser,
});
</script>

<style lang="scss">
.ct-users-user-detail {
    .ct-page__main-content,
    .ct-page__main-content-inner {
        overflow: visible;
        background: transparent;
    }

    &__shell {
        display: block;
    }

    &__topbar,
    &__page-header {
        display: none;
    }

    &__workspace {
        padding: 0;
    }

    &__content {
        display: grid;
        gap: var(--ct-spacing);
    }

    &__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 var(--ct-spacing-lg);
    }

    &__interface-grid {
        align-items: start;
    }

    &__media-field {
        grid-row: span 2;
    }

    &__tags {
        margin-top: var(--ct-spacing-sm);
    }

    &__secret-help-text-alert {
        margin-top: var(--ct-spacing);
    }

    @media screen and (max-width: 680px) {
        &__grid {
            grid-template-columns: 1fr;
        }
    }
}

.ct-users-user-detail__drawer {
    .ant-drawer-body {
        background: var(--ct-color-bg-layout);
    }

    @media screen and (max-width: 680px) {
        .ant-drawer-content-wrapper {
            width: 100% !important;
        }
    }
}
</style>
