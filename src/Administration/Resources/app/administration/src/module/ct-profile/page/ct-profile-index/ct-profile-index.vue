<template>
    <ct-block name="ct_profile_index">
        <ct-page class="ct-profile-index">
            <template #smart-bar-back>
                <ct-block name="ct_profile_index_smart_bar_back">
                    <span></span>
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_profile_index_headline">
                    <h2>{{ $t('ct-profile.general.headlineProfile') }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_profile_index_actions">
                    <ct-button-process
                        size="default"
                        class="ct-profile__save-action"
                        variant="primary"
                        :is-loading="isLoading || isUserLoading"
                        :process-success="isSaveSuccessful"
                        :disabled="isLoading || isUserLoading || !acl.can('user.update_profile') || undefined"
                        @update:process-success="saveFinish"
                        @click.prevent="onSave"
                    >
                        {{ $t('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>

            <template #content>
                <ct-card-view>
                    <ct-block name="ct_profile_index_tabs">
                        <div position-identifier="ct-profile-index">
                            <mt-tabs :items="profileTabItems" :default-item="$route.name" @new-item-active="onTabChange" />
                        </div>
                    </ct-block>

                    <ct-block name="ct_profile_index_router_view">
                        <template v-if="isUserLoading">
                            <ct-skeleton />
                            <ct-skeleton />
                        </template>

                        <template v-else>
                            <router-view v-slot="{ Component }">
                                <component
                                    :is="Component"
                                    v-bind="{
                                        user,
                                        timezoneOptions,
                                        languages,
                                        newPassword,
                                        newPasswordConfirm,
                                        avatarMediaItem,
                                        isUserLoading,
                                        languageId,
                                        isDisabled,
                                        userRepository,
                                    }"
                                    @new-password-change="onChangeNewPassword"
                                    @new-password-confirm-change="onChangeNewPasswordConfirm"
                                    @media-upload="setMediaItem"
                                    @media-remove="onUnlinkAvatar"
                                    @media-open="openMediaModal"
                                />
                            </router-view>
                        </template>
                    </ct-block>
                </ct-card-view>

                <ct-block name="ct_profile_index_media_upload_actions_media_modal">
                    <ct-media-modal-v2
                        v-if="showMediaModal"
                        :allow-multi-select="false"
                        :initial-folder-id="mediaDefaultFolderId"
                        :entity-context="user.getEntityName()"
                        @modal-close="showMediaModal = false"
                        @media-modal-selection-change="onMediaSelectionChange"
                    />
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import { KEY_USER_SEARCH_PREFERENCE } from 'src/app/service/search-ranking.service';
import '../../store/ct-profile.store';
const { Store } = Contena;
const { Criteria } = Contena.Data;

defineProps({});

import { ref, computed, inject, watch, onBeforeMount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const userService = inject('userService');
const loginService = inject('loginService');
const mediaDefaultFolderService = inject('mediaDefaultFolderService');
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');
const searchPreferencesService = inject('searchPreferencesService');
const searchRankingService = inject('searchRankingService');
const userConfigService = inject('userConfigService');
const validationApiService = inject('validationApiService');

const user = ref({ username: '', email: '' });
const languages = ref([]);
const imageSize = ref(140);
const newPassword = ref(null);
const newPasswordConfirm = ref(null);
const avatarMediaItem = ref(null);
const uploadTag = ref('ct-profile-upload-tag');
const isLoading = ref(false);
const isUserLoading = ref(true);
const isSaveSuccessful = ref(false);
const mediaDefaultFolderId = ref(null);
const showMediaModal = ref(false);
const timezoneOptions = ref([]);
const userPromise = ref(Promise.resolve(null));

const minSearchTermLength = computed(() => {
    return Store.get('ctProfile').minSearchTermLength;
});
const searchPreferences = computed(() => {
    return Store.get('ctProfile').searchPreferences;
});
const userEmailError = computed(() => {
    const entity = user.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'email');
});
const userTimeZoneError = computed(() => {
    const entity = user.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'timeZone');
});
const userSearchPreferences = computed({
    get: () => {
        return Store.get('ctProfile').userSearchPreferences;
    },
    set: (userSearchPreferences) => {
        Store.get('ctProfile').userSearchPreferences = userSearchPreferences;
    },
});
const isDisabled = computed(() => {
    return true;
});
const userRepository = computed(() => {
    return repositoryFactory.create('user');
});
const languageRepository = computed(() => {
    return repositoryFactory.create('language');
});
const localeRepository = computed(() => {
    return repositoryFactory.create('locale');
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const profileTabItems = computed(() => {
    return [
        {
            label: t('ct-profile.tabGeneral.title'),
            name: 'ct.profile.index.general',
        },
        {
            label: t('ct-profile.tabSearchPreferences.title'),
            name: 'ct.profile.index.searchPreferences',
        },
    ];
});
const languageId = computed(() => {
    return Contena.Store.get('session').languageId;
});

const createdComponent = () => {
    isUserLoading.value = true;
    userPromise.value = getUserData();
    timezoneOptions.value = Contena.Service('timezoneService').getTimezoneOptions();

    if (acl.can('media.creator')) {
        getMediaDefaultFolderId()
            .then((id) => {
                mediaDefaultFolderId.value = id;
            })
            .catch(() => {
                mediaDefaultFolderId.value = null;
            });
    }

    return Promise.all([
        Promise.resolve(languageId.value),
        userPromise.value,
    ])
        .then(() => loadLanguages())
        .finally(() => {
            isUserLoading.value = false;
        });
};
const beforeMountComponent = () => {
    return userPromise.value.then((loadedUser) => {
        if (!loadedUser) {
            return;
        }

        user.value = loadedUser;

        if (user.value.avatarId) {
            loadMediaItem(user.value.avatarId);
        }
    });
};
const resetGeneralData = () => {
    newPassword.value = null;
    newPasswordConfirm.value = null;

    void createdComponent();
    void beforeMountComponent();
};
const onTabChange = (routeName) => {
    void router.push({ name: routeName });
};
const loadLanguages = () => {
    const factoryContainer = Contena.Application.getContainer('factory');
    const localeFactory = factoryContainer.locale;
    const registeredLocales = Array.from(localeFactory.getLocaleRegistry().keys());

    const languageCriteria = new Criteria(1, 500);
    languageCriteria.addAssociation('locale');
    languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
    languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));
    languageCriteria.addFilter(Criteria.equalsAny('locale.code', registeredLocales));

    return languageRepository.value.search(languageCriteria).then((result) => {
        languages.value = [];
        const localeIds = [];
        let fallbackId = '';

        result.forEach((lang) => {
            lang.customLabel = `${lang.locale.translated.name} (${lang.locale.translated.territory})`;
            languages.value.push(lang);

            localeIds.push(lang.localeId);
            if (lang.locale.code === Contena.Context.app.fallbackLocale) {
                fallbackId = lang.localeId;
            }
        });

        if (!localeIds.includes(user.value.localeId)) {
            user.value.localeId = fallbackId;
        }
        isUserLoading.value = false;

        return languages.value;
    });
};
const getUserData = async () => {
    const routeUser = route.params.user;
    if (routeUser) {
        return userRepository.value.get(routeUser.id);
    }

    const user = await userService.getUser();
    return userRepository.value.get(user.data.id);
};
const saveFinish = async () => {
    isSaveSuccessful.value = false;
    user.value = await getUserData();
};
const onSave = async () => {
    if (route.name === 'ct.profile.index.searchPreferences') {
        void Promise.all([
            saveMinSearchTermLength(),
            saveUserSearchPreferences(),
        ]);

        return;
    }

    const isValid = await validationApiService.validateEmailAddress(user.value.email);

    if (isValid) {
        const passwordCheck = checkPassword();
        if (passwordCheck === null || passwordCheck === true) {
            isSaveSuccessful.value = false;
            isLoading.value = true;
            saveUser(Contena.Context.api);
        }

        return;
    }

    createErrorMessage(t('ct-profile.index.notificationInvalidEmailErrorMessage'));
};
const checkPassword = () => {
    if (newPassword.value && newPassword.value.length > 0) {
        if (newPassword.value !== newPasswordConfirm.value) {
            createErrorMessage(t('ct-profile.index.notificationPasswordErrorMessage'));
            return false;
        }

        user.value.password = newPassword.value;

        return true;
    }

    return null;
};
const createErrorMessage = (errorMessage) => {
    createNotificationError({
        message: errorMessage,
    });
};
const saveUser = (context) => {
    if (!acl.can('user:editor')) {
        const changes = userRepository.value.getSyncChangeset([
            user.value,
        ]);
        delete changes.changeset[0].changes.id;

        userService
            .updateUser(changes.changeset[0].changes)
            .then(async () => {
                if (newPassword.value) {
                    try {
                        await loginService.loginByUsername(user.value.username, newPassword.value);
                    } catch {
                        loginService.logout();
                        return;
                    }
                }

                await updateCurrentUser();

                isLoading.value = false;
                isSaveSuccessful.value = true;

                Contena.Service('localeHelper').setLocaleWithId(user.value.localeId);
            })
            .catch((error) => {
                if (error?.response?.data?.errors?.[0]) {
                    Contena.Store.get('error').addApiError({
                        expression: `user.${user.value?.id}.password`,
                        error: new Contena.Classes.ContenaError(error.response.data.errors[0]),
                    });
                }
                createNotificationError({
                    message: t('ct-profile.index.notificationSaveErrorMessage'),
                });
                isLoading.value = false;
                isSaveSuccessful.value = false;
            });

        return;
    }

    userRepository.value
        .save(user.value, context)
        .then(async () => {
            if (newPassword.value) {
                try {
                    await loginService.loginByUsername(user.value.username, newPassword.value);
                } catch {
                    loginService.logout();
                    return;
                }
            }

            await updateCurrentUser();
            Contena.Service('localeHelper').setLocaleWithId(user.value.localeId);

            isLoading.value = false;
            isSaveSuccessful.value = true;

            newPassword.value = '';
            newPasswordConfirm.value = '';
        })
        .catch(() => {
            handleUserSaveError();
            isLoading.value = false;
            isSaveSuccessful.value = false;
        });
};
const updateCurrentUser = () => {
    return userService.getUser().then((response) => {
        const data = response.data;
        delete data.password;

        return Contena.Store.get('session').setCurrentUser(data);
    });
};
const loadMediaItem = (targetId) => {
    mediaRepository.value.get(targetId).then((media) => {
        avatarMediaItem.value = media;
    });
};
const setMediaItem = ({ targetId }) => {
    user.value.avatarId = targetId;
    loadMediaItem(targetId);
};
const onDropMedia = (mediaItem) => {
    setMediaItem({ targetId: mediaItem.id });
};
const onUnlinkAvatar = () => {
    avatarMediaItem.value = null;
    user.value.avatarId = null;
};
const openMediaModal = () => {
    showMediaModal.value = true;
};
const handleUserSaveError = () => {
    if (route.name.includes('ct.profile.index')) {
        createNotificationError({
            message: t('ct-profile.index.notificationSaveErrorMessage'),
        });
    }
    isLoading.value = false;
};
const onChangeNewPassword = (newPasswordValue) => {
    newPassword.value = newPasswordValue;
};
const onChangeNewPasswordConfirm = (newPasswordConfirmValue) => {
    newPasswordConfirm.value = newPasswordConfirmValue;
};
const onMediaSelectionChange = ([mediaEntity]) => {
    avatarMediaItem.value = mediaEntity;
    user.value.avatarId = mediaEntity.id;
};
const getMediaDefaultFolderId = () => {
    return mediaDefaultFolderService.getDefaultFolderId('user');
};
const saveMinSearchTermLength = () => {
    return searchRankingService.saveMinSearchTermLength(minSearchTermLength.value);
};
const saveUserSearchPreferences = () => {
    userSearchPreferences.value = userSearchPreferences.value ?? searchPreferencesService.createUserSearchPreferences();
    userSearchPreferences.value.value = searchPreferences.value.map(({ entityName, _searchable, fields }) => {
        return {
            [entityName]: {
                _searchable,
                ...searchPreferencesService.processSearchPreferencesFields(fields),
            },
        };
    });

    searchRankingService.clearCacheUserSearchConfiguration();

    isLoading.value = true;
    isSaveSuccessful.value = false;
    return userConfigService
        .upsert({
            [KEY_USER_SEARCH_PREFERENCE]: userSearchPreferences.value.value,
        })
        .then(() => {
            isLoading.value = false;
            isSaveSuccessful.value = true;
        })
        .catch((error) => {
            isLoading.value = false;
            isSaveSuccessful.value = false;
            createNotificationError({ message: error.message });
        });
};
watch(
    () => user.value.avatarMedia?.id,
    () => {
        if (!user.value.avatarMedia?.id) {
            return;
        }

        if (!acl.can('media.creator')) {
            return;
        }

        setMediaItem({ targetId: user.value.avatarMedia.id });
    },
);
watch(
    () => route.fullPath,
    () => {
        if (route.name !== 'ct.profile.index.searchPreferences') {
            resetGeneralData();
        }
    },
);
watch(
    () => languageId.value,
    () => {
        void createdComponent();
    },
);

void createdComponent();
onBeforeMount(() => beforeMountComponent());

ctDefinePublic({
    userService,
    loginService,
    mediaDefaultFolderService,
    repositoryFactory,
    acl,
    searchPreferencesService,
    searchRankingService,
    userConfigService,
    validationApiService,
    user,
    languages,
    imageSize,
    newPassword,
    newPasswordConfirm,
    avatarMediaItem,
    uploadTag,
    isLoading,
    isUserLoading,
    isSaveSuccessful,
    mediaDefaultFolderId,
    showMediaModal,
    timezoneOptions,
    userPromise,
    minSearchTermLength,
    searchPreferences,
    userEmailError,
    userTimeZoneError,
    userSearchPreferences,
    isDisabled,
    userRepository,
    languageRepository,
    localeRepository,
    mediaRepository,
    profileTabItems,
    languageId,
    createdComponent,
    beforeMountComponent,
    resetGeneralData,
    onTabChange,
    loadLanguages,
    getUserData,
    saveFinish,
    onSave,
    checkPassword,
    createErrorMessage,
    saveUser,
    updateCurrentUser,
    loadMediaItem,
    setMediaItem,
    onDropMedia,
    onUnlinkAvatar,
    openMediaModal,
    handleUserSaveError,
    onChangeNewPassword,
    onChangeNewPasswordConfirm,
    onMediaSelectionChange,
    getMediaDefaultFolderId,
    saveMinSearchTermLength,
    saveUserSearchPreferences,
});
usePageTitle();

defineExpose({
    userService,
    loginService,
    mediaDefaultFolderService,
    repositoryFactory,
    acl,
    searchPreferencesService,
    searchRankingService,
    userConfigService,
    validationApiService,
    user,
    languages,
    imageSize,
    newPassword,
    newPasswordConfirm,
    avatarMediaItem,
    uploadTag,
    isLoading,
    isUserLoading,
    isSaveSuccessful,
    mediaDefaultFolderId,
    showMediaModal,
    timezoneOptions,
    userPromise,
    minSearchTermLength,
    searchPreferences,
    userEmailError,
    userTimeZoneError,
    userSearchPreferences,
    isDisabled,
    userRepository,
    languageRepository,
    localeRepository,
    mediaRepository,
    profileTabItems,
    languageId,
    createdComponent,
    beforeMountComponent,
    resetGeneralData,
    onTabChange,
    loadLanguages,
    getUserData,
    saveFinish,
    onSave,
    checkPassword,
    createErrorMessage,
    saveUser,
    updateCurrentUser,
    loadMediaItem,
    setMediaItem,
    onDropMedia,
    onUnlinkAvatar,
    openMediaModal,
    handleUserSaveError,
    onChangeNewPassword,
    onChangeNewPasswordConfirm,
    onMediaSelectionChange,
    getMediaDefaultFolderId,
    saveMinSearchTermLength,
    saveUserSearchPreferences,
});
</script>
