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
const dataDictionaryService = inject('dataDictionaryService', null);
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
const roleOptions = ref([]);
const positionOptions = ref([]);
const genderOptions = ref([]);
const isGenderLoading = ref(false);

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
const canEditUser = computed(() =>
    acl.can(route.params.id ? 'users_and_permissions.editor' : 'users_and_permissions.creator'),
);
const detailBreadcrumbs = computed(() => [
    { title: t('global.ct-admin-menu.navigation.mainMenuItemSystem') },
    { title: t('ct-users.general.cardLabel') },
    { title: fullName.value },
]);
const antTimezoneOptions = computed(() => timezoneOptions.value);
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const integrationColumns = computed(() => {
    return [
        {
            title: t('ct-users.user-detail.labelAccessKey'),
            key: 'accessKey',
            dataIndex: 'accessKey',
        },
        { title: t('global.default.actions'), key: 'action', align: 'right', width: 112 },
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
    roleIds.forEach((roleId) => {
        onAclRoleAdd(roleOptions.value.find((option) => option.value === roleId)?.entity);
    });
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
    positionIds.forEach((positionId) => {
        onPositionAdd(positionOptions.value.find((option) => option.value === positionId)?.entity);
    });
};
const loadRoleOptions = async () => {
    const criteria = new Criteria(1, 500);
    criteria.addFilter(Criteria.equals('deletedAt', null));
    criteria.addSorting(Criteria.sort('name', 'ASC'));
    const repository = repositoryFactory.create('acl_role');
    if (typeof repository.search !== 'function') {
        return;
    }
    const roles = await repository.search(criteria, Contena.Context.api);
    roleOptions.value = Array.from(roles, (role) => ({ value: role.id, label: role.name, entity: role }));
};
const loadPositionOptions = async () => {
    const repository = repositoryFactory.create('position');
    if (typeof repository.search !== 'function') {
        return;
    }
    const positions = await repository.search(positionCriteria, Contena.Context.api);
    positionOptions.value = Array.from(positions, (position) => ({
        value: position.id,
        label: position.name,
        entity: position,
    }));
};
const loadGenderOptions = async () => {
    if (!dataDictionaryService) {
        return;
    }

    isGenderLoading.value = true;
    try {
        genderOptions.value = await dataDictionaryService.getOptions('core.gender', true);
    } finally {
        isGenderLoading.value = false;
    }
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
        loadRoleOptions(),
        loadPositionOptions(),
        loadGenderOptions(),
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
const getErrorMessage = (error) => error?.detail ?? error?.message ?? undefined;
const toggleMobileMenu = () => {
    Contena.Utils.EventBus.emit('ct-admin-menu/toggle-offcanvas', true);
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
    translate,
    userService,
    loginService,
    mediaDefaultFolderService,
    userValidationService,
    integrationService,
    dataDictionaryService,
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
    roleOptions,
    positionOptions,
    genderOptions,
    isGenderLoading,
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
    loadRoleOptions,
    loadPositionOptions,
    loadGenderOptions,
    languageRepository,
    languageCriteria,
    localeRepository,
    avatarMedia,
    isError,
    hasLanguage,
    disableConfirm,
    isCurrentUser,
    canEditUser,
    detailBreadcrumbs,
    antTimezoneOptions,
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
    getErrorMessage,
    toggleMobileMenu,
    onShowDetailModal,
    onCloseDetailModal,
    onSaveIntegration,
    onCloseDeleteModal,
    onConfirmDelete,
    updateAuthToken,
});
usePageTitle(() => identifier.value);

defineExpose({
    translate,
    userService,
    loginService,
    mediaDefaultFolderService,
    userValidationService,
    integrationService,
    dataDictionaryService,
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
    roleOptions,
    positionOptions,
    genderOptions,
    isGenderLoading,
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
    loadRoleOptions,
    loadPositionOptions,
    loadGenderOptions,
    languageRepository,
    languageCriteria,
    localeRepository,
    avatarMedia,
    isError,
    hasLanguage,
    disableConfirm,
    isCurrentUser,
    canEditUser,
    detailBreadcrumbs,
    antTimezoneOptions,
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
    getErrorMessage,
    toggleMobileMenu,
    onShowDetailModal,
    onCloseDetailModal,
    onSaveIntegration,
    onCloseDeleteModal,
    onConfirmDelete,
    updateAuthToken,
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
