<template>
    <ct-block name="sw_users_user_detail">
        <ct-page class="ct-users-user-detail">
            <template #smart-bar-header>
                <ct-block name="sw_users_user_detail_header">
                    <h2 v-if="!isLoading">
                        {{ fullName }}
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_users_user_detail_actions">
                    <ct-block name="sw_users_user_detail_actions_cancel">
                        <mt-button variant="secondary" size="default" @click="onCancel">
                            {{ translate('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_users_user_detail_actions_save">
                        <ct-button-process
                            size="default"
                            class="ct-users-user-detail__save-action"
                            :is-loading="isLoading"
                            :process-success="isSaveSuccessful"
                            :disabled="isLoading || !acl.can('users_and_permissions.editor') || undefined"
                            variant="primary"
                            @update:process-success="saveFinish"
                            @click.prevent="onSave"
                        >
                            {{ translate('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_users_user_detail_content">
                    <ct-card-view>
                        <ct-block name="sw_users_user_detail_content_inner">
                            <div class="ct-users-user-detail__content">
                                <ct-block name="sw_users_user_detail_card_basic_information">
                                    <mt-card
                                        position-identifier="ct-users-user-detail"
                                        :title="translate('ct-users.user-detail.labelCard')"
                                        :is-loading="isLoading"
                                    >
                                        <ct-block name="sw_users_user_detail_content_grid">
                                            <div
                                                v-if="user"
                                                class="ct-users-user-detail__grid ct-users-user-detail__information-grid"
                                            >
                                                <ct-block name="sw_users_user_detail_content_name">
                                                    <mt-text-field
                                                        v-model="user.name"
                                                        name="ct-field--user-name"
                                                        class="ct-users-user-detail__grid-name"
                                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                        :error="userNameError"
                                                        required
                                                        :label="translate('ct-users.user-detail.labelName')"
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_content_user_code">
                                                    <template v-if="user && user.userCode">
                                                        <mt-text-field
                                                            :model-value="user.userCode"
                                                            name="ct-field--user-userCode"
                                                            class="ct-users-user-detail__grid-user-code"
                                                            disabled
                                                            :label="translate('ct-users.user-detail.labelUserCode')"
                                                        />
                                                    </template>
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_content_phone_number">
                                                    <mt-text-field
                                                        v-model="user.phoneNumber"
                                                        name="ct-field--user-phoneNumber"
                                                        class="ct-users-user-detail__grid-phoneNumber"
                                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                        :error="userPhoneNumberError"
                                                        :label="translate('ct-users.user-detail.labelPhoneNumber')"
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_content_gender">
                                                    <ct-data-dictionary-select
                                                        v-model="user.gender"
                                                        technical-name="core.gender"
                                                        name="ct-field--user-gender"
                                                        class="ct-users-user-detail__grid-gender"
                                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                        :label="translate('ct-users.user-detail.labelGender')"
                                                        :placeholder="
                                                            translate('ct-users.user-detail.labelGenderPlaceholder')
                                                        "
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_content_email">
                                                    <mt-text-field
                                                        v-model="user.email"
                                                        name="ct-field--user-email"
                                                        class="ct-users-user-detail__grid-eMail"
                                                        :error="userEmailError"
                                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                        required
                                                        :label="translate('ct-users.user-detail.labelEmail')"
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_content_username">
                                                    <mt-text-field
                                                        v-model="user.username"
                                                        name="ct-field--user-username"
                                                        class="ct-users-user-detail__grid-username"
                                                        :error-message="
                                                            isUsernameUsed
                                                                ? translate('ct-users.user-detail.errorUsernameUsed')
                                                                : ''
                                                        "
                                                        :error="userUsernameError"
                                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                        required
                                                        :label="translate('ct-users.user-detail.labelUsername')"
                                                        @update:model-value="checkUsername"
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_content_password">
                                                    <mt-password-field
                                                        class="ct-users-user-detail__grid-password"
                                                        :model-value="user.password"
                                                        name="ct-field--user-password"
                                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                        :label="translate('ct-users.user-detail.labelPassword')"
                                                        :error="userPasswordError"
                                                        autocomplete="new-password"
                                                        @update:model-value="setPassword"
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_grid_content_active">
                                                    <mt-switch
                                                        v-model="user.active"
                                                        name="ct-field--user-active"
                                                        class="ct-users-user-detail__grid-active"
                                                        :label="translate('ct-users.user-detail.labelActive')"
                                                        :disabled="
                                                            isCurrentUser ||
                                                            !acl.can('users_and_permissions.editor') ||
                                                            undefined
                                                        "
                                                    />
                                                </ct-block>
                                            </div>
                                        </ct-block>

                                        <ct-block name="sw_users_user_detail_content_tags">
                                            <ct-entity-tag-select
                                                v-if="user"
                                                v-model:entity-collection="user.tags"
                                                name="ct-field--user-tags"
                                                class="ct-users-user-detail__tags"
                                                :label="translate('ct-users.user-detail.labelTags')"
                                                :placeholder="translate('ct-users.user-detail.placeholderTags')"
                                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                            />
                                        </ct-block>
                                    </mt-card>
                                </ct-block>

                                <ct-block name="sw_users_user_detail_card_user_interface">
                                    <mt-card
                                        position-identifier="ct-users-user-detail-user-interface"
                                        :title="translate('ct-users.user-detail.labelUserInterface')"
                                        :is-loading="isLoading"
                                    >
                                        <ct-block name="sw_users_user_detail_user_interface_grid">
                                            <div
                                                v-if="user"
                                                class="ct-users-user-detail__grid ct-users-user-detail__user-interface-grid"
                                            >
                                                <ct-block name="sw_users_user_detail_grid_content_language">
                                                    <mt-select
                                                        v-model="user.localeId"
                                                        name="ct-field--user-localeId"
                                                        class="ct-users-user-detail__grid-language"
                                                        :label="translate('ct-users.user-detail.labelLanguage')"
                                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                                        :error="userLocaleIdError"
                                                        :options="localeOptions"
                                                        required
                                                        :placeholder="
                                                            translate('ct-users.user-detail.labelLanguagePlaceholder')
                                                        "
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_grid_content_timezone">
                                                    <mt-select
                                                        v-model="user.timeZone"
                                                        name="ct-field--user-timeZone"
                                                        class="ct-users-user-detail__grid-timezone"
                                                        :options="timezoneOptions"
                                                        required
                                                        :label="translate('ct-users.user-detail.labelTimezone')"
                                                        :disabled="!acl.can('user.update_profile') || undefined"
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_users_user_detail_content_media_upload">
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

                                <ct-block name="sw_users_user_detail_card_roles_permissions">
                                    <mt-card
                                        position-identifier="ct-users-user-detail-roles-permissions"
                                        :title="translate('ct-users.user-detail.labelRolesPermissionsCard')"
                                        :is-loading="isLoading"
                                    >
                                        <ct-block name="sw_users_user_detail_roles_permissions_grid">
                                            <div
                                                v-if="user"
                                                class="ct-users-user-detail__grid ct-users-user-detail__roles-permissions-grid"
                                            >
                                                <ct-block name="sw_users_user_detail_grid_content_acl_roles">
                                                    <mt-entity-select
                                                        v-tooltip="{
                                                            showDelay: 300,
                                                            message: translate(
                                                                'ct-users.user-detail.disabledRoleSelectWarning',
                                                            ),
                                                            disabled:
                                                                !user.admin || !acl.can('users_and_permissions.editor'),
                                                        }"
                                                        :model-value="aclRoleIds"
                                                        name="ct-field--user-aclRoles"
                                                        class="ct-users-user-detail__grid-aclRoles"
                                                        :label="translate('ct-users.user-detail.labelRoles')"
                                                        :disabled="
                                                            user.admin ||
                                                            !acl.can('users_and_permissions.editor') ||
                                                            undefined
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

                                                <ct-block name="sw_users_user_detail_grid_content_job_title">
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

                                                <ct-block name="sw_users_user_detail_grid_content_acl_is_admin">
                                                    <mt-switch
                                                        v-model="user.admin"
                                                        name="ct-field--user-admin"
                                                        class="ct-users-user-detail__grid-is-admin"
                                                        :label="translate('ct-users.user-detail.labelAdministrator')"
                                                        :disabled="
                                                            isCurrentUser ||
                                                            !acl.can('users_and_permissions.editor') ||
                                                            undefined
                                                        "
                                                    />
                                                </ct-block>
                                            </div>
                                        </ct-block>
                                    </mt-card>
                                </ct-block>

                                <ct-block name="sw_users_user_detail_card_integrations">
                                    <mt-card
                                        :title="translate('ct-users.user-detail.labelIntegrationsCard')"
                                        position-identifier="ct-users-user-detail-integrations"
                                    >
                                        <template #headerRight>
                                            <ct-block name="sw_users_user_detail_grid_toolbar">
                                                <ct-block name="sw_users_user_detail_grid_add_key">
                                                    <mt-button
                                                        variant="secondary"
                                                        size="small"
                                                        :disabled="
                                                            !$route.params.id ||
                                                            !acl.can('users_and_permissions.editor') ||
                                                            undefined
                                                        "
                                                        @click="addAccessKey"
                                                    >
                                                        {{ translate('ct-users.user-detail.addAccessKey') }}
                                                    </mt-button>
                                                </ct-block>
                                            </ct-block>
                                        </template>

                                        <template #grid>
                                            <ct-block name="sw_users_user_detail_key_grid">
                                                <ct-block name="sw_users_user_detail_key_grid_content">
                                                    <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
                                                    <ct-data-grid
                                                        :is-loading="isLoading"
                                                        :data-source="integrations"
                                                        :columns="integrationColumns"
                                                        identifier="user-grid"
                                                        :show-settings="true"
                                                        :skeleton-item-amount="skeletonItemAmount"
                                                    >
                                                        <template #actions="{ item }">
                                                            <ct-block name="sw_users_user_detail_grid_columns_actions">
                                                                <ct-block
                                                                    name="sw_users_user_detail_grid_columns_actions_edit"
                                                                >
                                                                    <ct-context-menu-item
                                                                        class="ct-users-user-detail__grid-context-menu-edit"
                                                                        :disabled="
                                                                            !acl.can('users_and_permissions.editor') ||
                                                                            undefined
                                                                        "
                                                                        @click="onShowDetailModal(item.id)"
                                                                    >
                                                                        {{
                                                                            translate('ct-users.user-detail.contextMenuEdit')
                                                                        }}
                                                                    </ct-context-menu-item>
                                                                </ct-block>

                                                                <ct-block
                                                                    name="sw_users_user_detail_grid_columns_actions_delete"
                                                                >
                                                                    <ct-context-menu-item
                                                                        class="ct-users-user-detail__grid-context-menu-delete"
                                                                        :disabled="
                                                                            !acl.can('users_and_permissions.editor') ||
                                                                            undefined
                                                                        "
                                                                        variant="danger"
                                                                        @click="showDeleteModal = item.id"
                                                                    >
                                                                        {{ translate('global.default.delete') }}
                                                                    </ct-context-menu-item>
                                                                </ct-block>
                                                            </ct-block>
                                                        </template>
                                                    </ct-data-grid>

                                                    <mt-empty-state
                                                        v-if="integrations.length === 0 && !isLoading"
                                                        :icon="$route.meta.$module.icon"
                                                        :headline="translate('ct-users.user-detail.noAccessKeysTitle')"
                                                        :description="translate('ct-users.user-detail.noAccessKeysSubline')"
                                                    />
                                                </ct-block>
                                            </ct-block>
                                        </template>
                                    </mt-card>
                                </ct-block>
                            </div>
                        </ct-block>
                    </ct-card-view>

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
                        <ct-modal
                            v-if="showDeleteModal"
                            :title="translate('global.default.warning')"
                            @modal-close="onCloseDeleteModal"
                        >
                            <ct-block name="sw_users_user_detail_grid_inner_slot_delete_modal_confirm_text">
                                <p>
                                    {{ translate('ct-users.user-detail.modal.confirmDelete') }}
                                </p>
                            </ct-block>

                            <template #modal-footer>
                                <ct-block name="sw_users_user_detail_grid_inner_slot_delete_modal_footer">
                                    <mt-button size="small" variant="secondary" @click="onCloseDeleteModal">
                                        {{ translate('global.default.cancel') }}
                                    </mt-button>

                                    <mt-button size="small" variant="critical" @click="onConfirmDelete(showDeleteModal)">
                                        {{ translate('global.default.delete') }}
                                    </mt-button>
                                </ct-block>
                            </template>
                        </ct-modal>
                    </ct-block>

                    <ct-block name="sw_users_user_detail_detail_modal">
                        <ct-modal
                            v-if="currentIntegration"
                            size="550px"
                            class="ct-users-user-detail__detail"
                            :is-loading="isModalLoading"
                            :title="
                                showSecretAccessKey ? translate('global.default.warning') : translate('global.default.edit')
                            "
                            @modal-close="onCloseDetailModal"
                        >
                            <ct-block name="sw_users_user_detail_detail_modal_inner_field_access_key">
                                <mt-text-field
                                    v-model="currentIntegration.accessKey"
                                    :label="translate('ct-users.user-detail.modal.idFieldLabel')"
                                    :disabled="true"
                                    :copyable="true"
                                    :copyable-tooltip="true"
                                />
                            </ct-block>

                            <ct-block name="sw_users_user_detail_detail_modal_inner_field_secret_access_key">
                                <ct-block name="sw_users_user_detail_detail_modal_inner_field_secret_access_key_field">
                                    <mt-text-field
                                        v-if="showSecretAccessKey"
                                        v-model="currentIntegration.secretAccessKey"
                                        :label="translate('ct-users.user-detail.modal.secretFieldLabel')"
                                        :disabled="true"
                                        :password-toggle-able="false"
                                        :copyable="showSecretAccessKey"
                                        :copyable-tooltip="true"
                                    />

                                    <mt-password-field
                                        v-else
                                        v-model="currentIntegration.secretAccessKey"
                                        :label="translate('ct-users.user-detail.modal.secretFieldLabel')"
                                        :disabled="true"
                                        :password-toggle-able="false"
                                        :copyable="showSecretAccessKey"
                                        :copyable-tooltip="true"
                                        autocomplete="off"
                                    />
                                </ct-block>

                                <ct-block name="sw_users_user_detail_detail_modal_inner_field_secret_access_key_button">
                                    <mt-button
                                        v-if="!showSecretAccessKey"
                                        class="ct-users-user-detail__secret-help-text-button ct-field"
                                        variant="critical"
                                        :block="true"
                                        @click="addAccessKey"
                                    >
                                        {{ translate('ct-users.user-detail.modal.buttonCreateNewApiKeys') }}
                                    </mt-button>
                                </ct-block>

                                <ct-block name="sw_users_user_detail_detail_modal_inner_field_help_text">
                                    <mt-banner
                                        v-if="!showSecretAccessKey"
                                        variant="attention"
                                        class="ct-users-user-detail__secret-help-text-alert"
                                    >
                                        {{ translate('ct-users.user-detail.modal.hintCreateNewApiKeys') }}
                                    </mt-banner>
                                </ct-block>
                            </ct-block>

                            <ct-block name="sw_users_user_detail_detail_modal_inner_help_text">
                                <template v-if="!showSecretAccessKey"
                                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                                >
                                <mt-banner v-else variant="attention" class="ct-users-user-detail__secret-help-text-alert">
                                    {{ translate('ct-users.user-detail.modal.secretHelpText') }}
                                </mt-banner>
                            </ct-block>

                            <template #modal-footer>
                                <ct-block name="sw_users_user_detail_detail_modal_inner_footer">
                                    <ct-block name="sw_users_user_detail_detail_modal_inner_footer_cancel">
                                        <mt-button
                                            size="small"
                                            :disabled="isModalLoading || undefined"
                                            variant="secondary"
                                            @click="onCloseDetailModal"
                                        >
                                            {{ translate('global.default.cancel') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="sw_users_user_detail_detail_modal_inner_footer_apply">
                                        <mt-button
                                            size="small"
                                            class="ct-users-user-detail__save-action"
                                            :disabled="(isModalLoading && !!currentIntegration.label) || undefined"
                                            variant="primary"
                                            @click="onSaveIntegration"
                                        >
                                            {{
                                                showSecretAccessKey
                                                    ? translate('ct-users.user-detail.modal.buttonApply')
                                                    : translate('ct-users.user-detail.modal.buttonApplyEdit')
                                            }}
                                        </mt-button>
                                    </ct-block>
                                </ct-block>
                            </template>
                        </ct-modal>
                    </ct-block>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-users-user-detail.scss';
const { Criteria } = Contena.Data;
const { warn } = Contena.Utils.debug;
const { ContenaError } = Contena.Classes;

defineProps({});

import { ref, computed, inject, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const translate = t;
const userService = inject('userService');
const loginService = inject('loginService');
const mediaDefaultFolderService = inject('mediaDefaultFolderService');
const userValidationService = inject('userValidationService');
const integrationService = inject('integrationService');
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const isLoading = ref(false);
const userId = ref('');
const user = ref(null);
const currentUser = ref(null);
const languages = ref([]);
const integrations = ref([]);
const currentIntegration = ref(null);
const mediaItem = ref(null);
const newPassword = ref('');
const newPasswordConfirm = ref('');
const isEmailAlreadyInUse = ref(false);
const isUsernameUsed = ref(false);
const isIntegrationsLoading = ref(false);
const isSaveSuccessful = ref(false);
const isModalLoading = ref(false);
const showSecretAccessKey = ref(false);
const showDeleteModal = ref(null);
const skeletonItemAmount = ref(3);
const timezoneOptions = ref([]);
const mediaDefaultFolderId = ref(null);
const showMediaModal = ref(false);
const keyRepository = ref(null);

const userNameError = computed(() => {
    const entity = user.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});
const userPhoneNumberError = computed(() => {
    const entity = user.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'phoneNumber');
});
const userEmailError = computed(() => {
    const entity = user.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'email');
});
const userUsernameError = computed(() => {
    const entity = user.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'username');
});
const userLocaleIdError = computed(() => {
    const entity = user.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'localeId');
});
const userPasswordError = computed(() => {
    const entity = user.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'password');
});
const identifier = computed(() => {
    return fullName.value;
});
const fullName = computed(() => {
    if (!user.value) {
        return t('ct-users.user-detail.labelNewUser');
    }

    return user.value.name || user.value.username || t('ct-users.user-detail.labelNewUser');
});
const userRepository = computed(() => {
    return repositoryFactory.create('user');
});
const userCriteria = computed(() => {
    const criteria = new Criteria(1, 25);

    criteria.addAssociation('accessKeys');
    criteria.addAssociation('locale');
    criteria.addAssociation('aclRoles');
    criteria.addAssociation('positions');
    criteria.addAssociation('tags');

    return criteria;
});
const aclRoleIds = computed(() => {
    return user.value?.aclRoles ? Array.from(user.value.aclRoles, (role) => role.id) : [];
});
const userPositions = computed(() => user.value?.positions);
const positionIds = computed(() => Array.from(userPositions.value?.getIds() ?? []));
const positionCriteria = new Criteria(1, 100);
positionCriteria.addFilter(Criteria.equals('active', true));
positionCriteria.addSorting(Criteria.sort('position', 'ASC'));
positionCriteria.addSorting(Criteria.sort('name', 'ASC'));
const aclRoleRepositoryFactory = () => {
    const repository = repositoryFactory.create('acl_role');

    return {
        search(criteria, context) {
            criteria.addFilter(Criteria.equals('deletedAt', null));

            return repository.search(criteria, context);
        },
    };
};
const languageRepository = computed(() => {
    return repositoryFactory.create('language');
});
const languageCriteria = new Criteria(1, 500);
languageCriteria.addAssociation('locale');
languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));
const localeRepository = computed(() => {
    return repositoryFactory.create('locale');
});
const avatarMedia = computed(() => {
    return mediaItem.value;
});
const isError = computed(() => {
    return isEmailAlreadyInUse.value || isUsernameUsed.value || !hasLanguage.value;
});
const hasLanguage = computed(() => {
    return user.value && user.value.localeId;
});
const disableConfirm = computed(() => {
    return newPassword.value !== newPasswordConfirm.value || newPassword.value === '' || newPassword.value === null;
});
const isCurrentUser = computed(() => {
    if (!user.value || !currentUser.value) {
        return false;
    }

    return userId.value === currentUser.value.id;
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const integrationColumns = computed(() => {
    return [
        {
            property: 'accessKey',
            label: t('ct-users.user-detail.labelAccessKey'),
        },
    ];
});
const languageId = computed(() => {
    return Contena.Store.get('session').languageId;
});
const localeOptions = computed(() => {
    return languages.value.map((language) => {
        return {
            id: language.locale.id,
            value: language.locale.id,
            label: language.customLabel,
        };
    });
});
const loadUser = () => {
    userId.value = route.params.id?.toLowerCase();

    return userRepository.value.get(userId.value, Contena.Context.api, userCriteria.value).then((loadedUser) => {
        user.value = loadedUser;

        if (user.value.avatarId) {
            loadMediaItem(user.value.avatarId);
        }

        keyRepository.value = repositoryFactory.create(user.value.accessKeys.entity, user.value.accessKeys.source);
        loadKeys();
    });
};
const onAclRoleAdd = (role) => {
    if (!user.value?.aclRoles || !role?.id || user.value.aclRoles.has(role.id)) {
        return;
    }

    user.value.aclRoles.add(role);
};
const onAclRoleRemove = (role) => {
    if (role?.id) {
        user.value?.aclRoles?.remove(role.id);
    }
};
const onAclRolesUpdate = (roleIds) => {
    if (!user.value?.aclRoles || !Array.isArray(roleIds)) {
        return;
    }

    user.value.aclRoles.filter((role) => !roleIds.includes(role.id)).forEach((role) => user.value.aclRoles.remove(role.id));
};
const onPositionAdd = (position) => {
    const positions = userPositions.value;

    if (!positions || !position?.id || positions.has(position.id)) {
        return;
    }

    positions.add(position);
};
const onPositionRemove = (position) => {
    if (position?.id) {
        userPositions.value?.remove(position.id);
    }
};
const onPositionsUpdate = (positionIds) => {
    const positions = userPositions.value;

    if (!positions || !Array.isArray(positionIds)) {
        return;
    }

    positions.filter((position) => !positionIds.includes(position.id)).forEach((position) => positions.remove(position.id));
};
const addAccessKey = () => {
    const newKey = keyRepository.value.create();

    isModalLoading.value = true;
    newKey.quantityStart = 1;
    integrationService.generateKey({}, {}, true).then((response) => {
        newKey.accessKey = response.accessKey;
        newKey.secretAccessKey = response.secretAccessKey;
        currentIntegration.value = newKey;
        isModalLoading.value = false;
        showSecretAccessKey.value = true;
    });
};
const createdComponent = () => {
    isLoading.value = true;

    if (!languageId.value) {
        isLoading.value = false;
        return Promise.resolve();
    }

    getMediaDefaultFolderId()
        .then((id) => {
            mediaDefaultFolderId.value = id;
        })
        .catch(() => {
            mediaDefaultFolderId.value = null;
        });

    timezoneOptions.value = Contena.Service('timezoneService').getTimezoneOptions();
    Contena.Store.get('context').api.languageId = languageId.value;

    const requests = [
        loadLanguages(),
        loadCurrentUser(),
    ];
    if (route.params.id) {
        requests.push(loadUser());
    }

    return Promise.all(requests).finally(() => {
        isLoading.value = false;
    });
};
const loadLanguages = () => {
    return languageRepository.value.search(languageCriteria).then((result) => {
        languages.value = [];
        result.forEach((lang) => {
            lang.customLabel = `${lang.locale.translated.name} (${lang.locale.translated.territory})`;
            languages.value.push(lang);
        });

        return languages.value;
    });
};
const loadCurrentUser = () => {
    return userService.getUser().then((response) => {
        currentUser.value = response.data;
    });
};
const loadKeys = () => {
    integrations.value = user.value.accessKeys;
};
const checkEmail = async () => {
    if (!user.value.email) {
        return true;
    }

    const { emailIsUnique } = await userValidationService.checkUserEmail({
        email: user.value.email,
        id: user.value.id,
    });

    isEmailAlreadyInUse.value = !emailIsUnique;

    if (isEmailAlreadyInUse.value) {
        const expression = `user.${user.value.id}.email`;
        const error = new ContenaError({
            code: 'USER_EMAIL_ALREADY_EXISTS',
            detail: t('ct-users.user-detail.errorEmailUsed'),
        });

        Contena.Store.get('error').addApiError({
            expression,
            error,
        });
        return false;
    }

    return true;
};
const checkUsername = () => {
    return userValidationService
        .checkUserUsername({
            username: user.value.username,
            id: user.value.id,
        })
        .then(({ usernameIsUnique }) => {
            isUsernameUsed.value = !usernameIsUnique;
        });
};
const loadMediaItem = (targetId) => {
    mediaRepository.value.get(targetId).then((media) => {
        mediaItem.value = media;
        user.value.avatarMedia = media;
    });
};
const setMediaItem = ({ targetId }) => {
    user.value.avatarId = targetId;
    loadMediaItem(targetId);
};
const onUnlinkLogo = () => {
    mediaItem.value = null;
    user.value.avatarMedia = null;
    user.value.avatarId = null;
};
const onDropMedia = (mediaItem) => {
    setMediaItem({ targetId: mediaItem.id });
};
const onOpenMedia = () => {
    showMediaModal.value = true;
};
const onMediaSelectionChange = ([mediaEntity]) => {
    mediaItem.value = mediaEntity;
    user.value.avatarMedia = mediaEntity;
    user.value.avatarId = mediaEntity.id;
};
const getMediaDefaultFolderId = () => {
    return mediaDefaultFolderService.getDefaultFolderId('user');
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
};
const onSave = () => {
    return saveUser(Contena.Context.api);
};
const saveUser = async (context) => {
    isSaveSuccessful.value = false;
    isLoading.value = true;

    try {
        if (currentUser.value.id === user.value.id) {
            await Contena.Service('localeHelper').setLocaleWithId(user.value.localeId);
        }

        if (!(await checkEmail())) {
            return;
        }

        await userRepository.value.save(user.value, context);

        if (currentUser.value.id === user.value.id) {
            if (user.value.password) {
                await updateAuthToken();
            }
            await updateCurrentUser();
        }

        await createdComponent();
        isSaveSuccessful.value = true;
    } catch (exception) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('ct-users.user-detail.notification.saveError.message', { name: fullName.value }, 0),
        });
        warn('ct-users-user-detail', exception.message, exception.response);
        throw exception;
    } finally {
        isLoading.value = false;
    }
};
const updateCurrentUser = async () => {
    await userService.getUser().then((response) => {
        const data = response.data;
        delete data.password;
        Contena.Store.get('session').setCurrentUser(data);
    });
};
const onCancel = () => {
    void router.push({ name: 'ct.users.index' });
};
const setPassword = (password) => {
    if (typeof password === 'string' && password.length <= 0) {
        delete user.value.password;
        return;
    }

    user.value.password = password;
};
const onShowDetailModal = (id) => {
    if (!id) {
        addAccessKey();
        return;
    }

    currentIntegration.value = user.value.accessKeys.get(id);
};
const onCloseDetailModal = () => {
    currentIntegration.value = null;
    showSecretAccessKey.value = false;
    isModalLoading.value = false;
};
const onSaveIntegration = () => {
    if (!currentIntegration.value) {
        return;
    }

    if (!user.value.accessKeys.has(currentIntegration.value.id)) {
        user.value.accessKeys.add(currentIntegration.value);
    }

    onCloseDetailModal();
};
const onCloseDeleteModal = () => {
    showDeleteModal.value = null;
};
const onConfirmDelete = (id) => {
    if (!id) {
        return;
    }

    onCloseDeleteModal();
    user.value.accessKeys.remove(id);
};
const updateAuthToken = async () => {
    await loginService.loginByUsername(user.value.username, user.value.password);
};

watch(
    () => languageId.value,
    () => {
        void createdComponent();
    },
);

void createdComponent();

swDefinePublic({
    userService,
    loginService,
    mediaDefaultFolderService,
    userValidationService,
    integrationService,
    repositoryFactory,
    acl,
    isLoading,
    userId,
    user,
    currentUser,
    languages,
    integrations,
    currentIntegration,
    mediaItem,
    newPassword,
    newPasswordConfirm,
    isEmailAlreadyInUse,
    isUsernameUsed,
    isIntegrationsLoading,
    isSaveSuccessful,
    isModalLoading,
    showSecretAccessKey,
    showDeleteModal,
    skeletonItemAmount,
    timezoneOptions,
    mediaDefaultFolderId,
    showMediaModal,
    keyRepository,
    userNameError,
    userPhoneNumberError,
    userEmailError,
    userUsernameError,
    userLocaleIdError,
    userPasswordError,
    identifier,
    fullName,
    userRepository,
    userCriteria,
    aclRoleIds,
    positionIds,
    positionCriteria,
    aclRoleRepositoryFactory,
    onAclRoleAdd,
    onAclRoleRemove,
    onAclRolesUpdate,
    onPositionAdd,
    onPositionRemove,
    onPositionsUpdate,
    languageRepository,
    languageCriteria,
    localeRepository,
    avatarMedia,
    isError,
    hasLanguage,
    disableConfirm,
    isCurrentUser,
    mediaRepository,
    integrationColumns,
    languageId,
    localeOptions,
    createdComponent,
    loadUser,
    addAccessKey,
    loadLanguages,
    loadCurrentUser,
    loadKeys,
    checkEmail,
    checkUsername,
    loadMediaItem,
    setMediaItem,
    onUnlinkLogo,
    onDropMedia,
    onOpenMedia,
    onMediaSelectionChange,
    getMediaDefaultFolderId,
    saveFinish,
    onSave,
    saveUser,
    updateCurrentUser,
    onCancel,
    setPassword,
    onShowDetailModal,
    onCloseDetailModal,
    onSaveIntegration,
    onCloseDeleteModal,
    onConfirmDelete,
    updateAuthToken,
});
usePageTitle(() => identifier.value);

defineExpose({
    userService,
    loginService,
    mediaDefaultFolderService,
    userValidationService,
    integrationService,
    repositoryFactory,
    acl,
    isLoading,
    userId,
    user,
    currentUser,
    languages,
    integrations,
    currentIntegration,
    mediaItem,
    newPassword,
    newPasswordConfirm,
    isEmailAlreadyInUse,
    isUsernameUsed,
    isIntegrationsLoading,
    isSaveSuccessful,
    isModalLoading,
    showSecretAccessKey,
    showDeleteModal,
    skeletonItemAmount,
    timezoneOptions,
    mediaDefaultFolderId,
    showMediaModal,
    keyRepository,
    userNameError,
    userPhoneNumberError,
    userEmailError,
    userUsernameError,
    userLocaleIdError,
    userPasswordError,
    identifier,
    fullName,
    userRepository,
    userCriteria,
    aclRoleIds,
    positionIds,
    positionCriteria,
    aclRoleRepositoryFactory,
    onAclRoleAdd,
    onAclRoleRemove,
    onAclRolesUpdate,
    onPositionAdd,
    onPositionRemove,
    onPositionsUpdate,
    languageRepository,
    languageCriteria,
    localeRepository,
    avatarMedia,
    isError,
    hasLanguage,
    disableConfirm,
    isCurrentUser,
    mediaRepository,
    integrationColumns,
    languageId,
    localeOptions,
    createdComponent,
    loadUser,
    addAccessKey,
    loadLanguages,
    loadCurrentUser,
    loadKeys,
    checkEmail,
    checkUsername,
    loadMediaItem,
    setMediaItem,
    onUnlinkLogo,
    onDropMedia,
    onOpenMedia,
    onMediaSelectionChange,
    getMediaDefaultFolderId,
    saveFinish,
    onSave,
    saveUser,
    updateCurrentUser,
    onCancel,
    setPassword,
    onShowDetailModal,
    onCloseDetailModal,
    onSaveIntegration,
    onCloseDeleteModal,
    onConfirmDelete,
    updateAuthToken,
});
</script>
