<template>
    <ct-block name="ct_users_user_detail_page">
        <ct-page class="ct-users-user-detail">
            <template #smart-bar-header>
                <ct-block name="ct_users_user_detail_header">
                    <h2 v-if="!isLoading">{{ fullName }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_users_user_detail_actions">
                    <mt-button variant="secondary" @click="onCancel">
                        {{ translate('global.default.cancel') }}
                    </mt-button>
                    <ct-button-process
                        v-model:process-success="isSaveSuccessful"
                        variant="primary"
                        :is-loading="isLoading"
                        :disabled="isLoading || !acl.can('users_and_permissions.editor') || undefined"
                        @click.prevent="onSave"
                        @update:process-success="saveFinish"
                    >
                        {{ translate('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>

            <template #content>
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
                                :title="translate('global.default.warning')"
                                @modal-close="onCloseDeleteModal"
                            >
                                <ct-block name="ct_users_user_detail_grid_inner_slot_delete_modal_confirm_text">
                                    <p>
                                        {{ translate('ct-users.user-detail.modal.confirmDelete') }}
                                    </p>
                                </ct-block>

                                <template #modal-footer>
                                    <ct-block name="ct_users_user_detail_grid_inner_slot_delete_modal_footer">
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

                        <ct-block name="ct_users_user_detail_detail_modal">
                            <ct-modal
                                v-if="currentIntegration"
                                size="550px"
                                class="ct-users-user-detail__detail"
                                :is-loading="isModalLoading"
                                :title="
                                    showSecretAccessKey
                                        ? translate('global.default.warning')
                                        : translate('global.default.edit')
                                "
                                @modal-close="onCloseDetailModal"
                            >
                                <ct-block name="ct_users_user_detail_detail_modal_inner_field_access_key">
                                    <mt-text-field
                                        v-model="currentIntegration.accessKey"
                                        :label="translate('ct-users.user-detail.modal.idFieldLabel')"
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

                                    <ct-block name="ct_users_user_detail_detail_modal_inner_field_secret_access_key_button">
                                        <mt-button
                                            v-if="!showSecretAccessKey"
                                            class="ct-users-user-detail__secret-help-text-button ct-field"
                                            variant="critical"
                                            :block="true"
                                            @click.stop.prevent="addAccessKey"
                                        >
                                            {{ translate('ct-users.user-detail.modal.buttonCreateNewApiKeys') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="ct_users_user_detail_detail_modal_inner_field_help_text">
                                        <mt-banner
                                            v-if="!showSecretAccessKey"
                                            variant="attention"
                                            class="ct-users-user-detail__secret-help-text-alert"
                                        >
                                            {{ translate('ct-users.user-detail.modal.hintCreateNewApiKeys') }}
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
                                        {{ translate('ct-users.user-detail.modal.secretHelpText') }}
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
                                                {{ translate('global.default.cancel') }}
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

const props = defineProps({
    initialUserId: {
        type: String,
        default: '',
    },
});

import { ref, computed, inject, provide, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationError } = useNotification();

const translate = t;
const router = useRouter();
const detailTabs = computed(() => [
    {
        label: t('ct-users.user-detail.labelCard'),
        name: 'ct.users.detail.base',
        onClick: () => void router.push({ name: 'ct.users.detail.base', params: { id: props.initialUserId } }),
    },
    {
        label: t('ct-users.user-detail.labelUserInterface'),
        name: 'ct.users.detail.interface',
        onClick: () => void router.push({ name: 'ct.users.detail.interface', params: { id: props.initialUserId } }),
    },
    {
        label: t('ct-users.user-detail.labelRolesPermissionsCard'),
        name: 'ct.users.detail.roles',
        onClick: () => void router.push({ name: 'ct.users.detail.roles', params: { id: props.initialUserId } }),
    },
    {
        label: t('ct-users.user-detail.labelIntegrationsCard'),
        name: 'ct.users.detail.integrations',
        onClick: () => void router.push({ name: 'ct.users.detail.integrations', params: { id: props.initialUserId } }),
    },
]);
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
const fullName = computed(() => {
    if (!user.value) {
        return t('ct-users.user-detail.labelNewUser');
    }

    return user.value.name || user.value.username || t('ct-users.user-detail.labelNewUser');
});
const identifier = computed(() => fullName.value);
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
            position: 100,
            renderer: 'text',
            width: 420,
        },
    ];
});
const integrationContextButtons = computed(() => [
    {
        key: 'edit',
        label: t('ct-users.user-detail.contextMenuEdit'),
    },
]);
const onIntegrationContextSelect = ({ key, data }) => {
    if (key === 'edit') {
        onShowDetailModal(data.id);
    }
};
const onIntegrationDelete = (item) => {
    showDeleteModal.value = item.id;
};
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
    userId.value = props.initialUserId.toLowerCase();

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
    if (!keyRepository.value) {
        return;
    }

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
    if (props.initialUserId) {
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
const onCancel = () => {
    void router.push({ name: 'ct.users.index' });
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

provide('ctUsersUserDetailContext', {
    user,
    isLoading,
    acl,
    translate,
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

watch(
    () => languageId.value,
    () => {
        void createdComponent();
    },
);

void createdComponent();

ctDefinePublic({
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
    integrationContextButtons,
    languageId,
    localeOptions,
    createdComponent,
    loadUser,
    addAccessKey,
    onIntegrationContextSelect,
    onIntegrationDelete,
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
    onCancel,
    saveUser,
    updateCurrentUser,
    setPassword,
    onShowDetailModal,
    onCloseDetailModal,
    onSaveIntegration,
    onCloseDeleteModal,
    onConfirmDelete,
    updateAuthToken,
    detailTabs,
});
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
    integrationContextButtons,
    languageId,
    localeOptions,
    createdComponent,
    loadUser,
    addAccessKey,
    onIntegrationContextSelect,
    onIntegrationDelete,
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
    onCancel,
    saveUser,
    updateCurrentUser,
    setPassword,
    onShowDetailModal,
    onCloseDetailModal,
    onSaveIntegration,
    onCloseDeleteModal,
    onConfirmDelete,
    updateAuthToken,
    detailTabs,
});
</script>
