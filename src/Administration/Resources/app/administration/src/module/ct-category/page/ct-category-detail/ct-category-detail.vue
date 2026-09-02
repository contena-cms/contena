<template>
    <ct-block name="ct_category">
        <ct-page class="ct-category" :class="pageClasses">
            <template #search-bar>
                <ct-block name="ct_category_search_bar">
                    <mt-search :model-value="term" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_category_smart_bar_header">
                    <h2 v-if="category">
                        {{ placeholder(category, 'name') }}
                    </h2>
                    <h2 v-else>
                        {{ $t('ct-category.general.headlineCategories') }}
                    </h2>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="ct_category_language_switch">
                    <ct-language-switch
                        :save-changes-function="saveOnLanguageChange"
                        :abort-change-function="abortOnLanguageChange"
                        :disabled="landingPageId === 'create'"
                        @on-change="onChangeLanguage"
                    />
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <template v-if="category || landingPage">
                    <ct-block name="ct_category_smart_bar_abort">
                        <mt-button
                            v-tooltip.bottom="tooltipCancel"
                            :disabled="isLoading"
                            variant="secondary"
                            size="default"
                            @click="cancelEdit"
                        >
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_category_smart_bar_save">
                        <ct-block name="ct_category_smart_bar_save_category">
                            <ct-button-process
                                v-if="category"
                                v-tooltip.bottom="tooltipSave"
                                class="ct-category-detail__save-action"
                                :is-loading="isLoading"
                                :process-success="isSaveSuccessful"
                                :disabled="isLoading || !acl.can('category.editor')"
                                variant="primary"
                                @update:process-success="saveFinish"
                                @click.prevent="onSave"
                            >
                                {{ $t('global.default.save') }}
                            </ct-button-process>
                        </ct-block>

                        <ct-block name="ct_category_smart_bar_save_landing_page">
                            <ct-button-process
                                v-if="landingPage"
                                v-tooltip.bottom="landingPageTooltipSave"
                                class="ct-category-detail__save-landing-page-action"
                                :is-loading="isLoading"
                                :process-success="isSaveSuccessful"
                                :disabled="isLoading || !acl.can('landing_page.editor')"
                                variant="primary"
                                @update:process-success="saveFinish"
                                @click.prevent="onSaveLandingPage"
                            >
                                {{ $t('global.default.save') }}
                            </ct-button-process>
                        </ct-block>
                    </ct-block>
                </template>
            </template>

            <template #side-content>
                <ct-block name="ct_category_side_content">
                    <ct-block name="ct_category_collapse">
                        <ct-sidebar-collapse
                            class="ct-category-detail__category-collapse"
                            :expand-on-loading="landingPageId === null"
                        >
                            <template #header>
                                <ct-block name="ct_category_collapse_header">
                                    <div v-if="categoryCheckedItem > 0" class="ct-category-detail__collapse-selected-count">
                                        {{ $t(`ct-category.general.treeHeadSelected`, { count: categoryCheckedItem }) }}:
                                    </div>
                                    <div v-else class="ct-category-detail__collapse-headline">
                                        {{ $t(`ct-category.general.treeHeadline`) }}
                                    </div>
                                </ct-block>
                            </template>

                            <template #actions>
                                <ct-block name="ct_category_collapse_actions">
                                    <div v-if="categoryCheckedItem > 0">
                                        <mt-button
                                            class="ct-tree-actions__delete_categories"
                                            variant="critical"
                                            size="small"
                                            @click="onCategoryDelete"
                                        >
                                            {{ $t('global.default.delete') }}
                                        </mt-button>
                                    </div>
                                </ct-block>
                            </template>

                            <template #content>
                                <ct-block name="ct_category_tree">
                                    <ct-category-tree
                                        ref="categoryTree"
                                        :category-id="categoryId"
                                        :current-language-id="currentLanguageId"
                                        :allow-edit="acl.can('category.editor')"
                                        :allow-create="acl.can('category.creator')"
                                        :allow-delete="acl.can('category.deleter')"
                                        @unsaved-changes="openChangeModal"
                                        @category-checked-elements-count="categoryCheckedElementsCount"
                                    />
                                </ct-block>
                            </template>
                        </ct-sidebar-collapse>
                    </ct-block>

                    <ct-block name="ct_landing_page_collapse">
                        <ct-sidebar-collapse
                            class="ct-category-detail__landing-page-collapse"
                            :expand-on-loading="landingPageId !== null"
                        >
                            <template #header>
                                <ct-block name="ct_landing_page_collapse_header">
                                    <div
                                        v-if="landingPageCheckedItem > 0"
                                        class="ct-category-detail__collapse-selected-count"
                                    >
                                        {{
                                            $t(`ct-landing-page.general.treeHeadSelected`, {
                                                count: landingPageCheckedItem,
                                            })
                                        }}:
                                    </div>
                                    <div v-else class="ct-category-detail__collapse-headline">
                                        {{ $t(`ct-landing-page.general.treeHeadline`) }}
                                    </div>
                                </ct-block>
                            </template>

                            <template #actions>
                                <ct-block name="ct_landing_page_collapse_actions">
                                    <div v-if="landingPageCheckedItem > 0">
                                        <mt-button
                                            class="ct-tree-actions__delete_categories"
                                            variant="critical"
                                            size="small"
                                            @click="onLandingPageDelete"
                                        >
                                            {{ $t('global.default.delete') }}
                                        </mt-button>
                                    </div>
                                </ct-block>
                            </template>

                            <template #content>
                                <ct-block name="ct_landing_page_tree">
                                    <ct-landing-page-tree
                                        ref="landingPageTree"
                                        :landing-page-id="landingPageId"
                                        :current-language-id="currentLanguageId"
                                        :allow-edit="acl.can('landing_page.editor')"
                                        :allow-create="acl.can('landing_page.creator')"
                                        :allow-delete="acl.can('landing_page.deleter')"
                                        @unsaved-changes="openChangeModal"
                                        @landing-page-checked-elements-count="landingPageCheckedElementsCount"
                                    />
                                </ct-block>
                            </template>
                        </ct-sidebar-collapse>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_category_content">
                    <template v-if="isLoading">
                        <ct-skeleton variant="detail-bold" />
                        <ct-skeleton />
                    </template>

                    <div v-else class="ct-category__content">
                        <ct-block name="ct_category_content_view">
                            <ct-category-view
                                v-if="category"
                                ref="categoryView"
                                :is-loading="isLoading"
                                :type="category.type"
                            />
                        </ct-block>

                        <ct-block name="ct_category_content_entry_point_overwrite_modal">
                            <ct-category-entry-point-overwrite-modal
                                v-if="showEntryPointOverwriteModal"
                                :channels="entryPointOverwriteChannels"
                                @cancel="cancelEntryPointOverwrite"
                                @confirm="confirmEntryPointOverwrite"
                            />
                        </ct-block>

                        <ct-block name="ct_landing_page_content_view">
                            <ct-landing-page-view v-if="landingPage" ref="landingPageView" :is-loading="isLoading" />
                        </ct-block>

                        <ct-block name="ct_category_content_discard_changes_modal">
                            <ct-discard-changes-modal
                                v-if="isDisplayingLeavePageWarning"
                                @keep-editing="onLeaveModalClose(nextRoute)"
                                @discard-changes="onLeaveModalConfirm(nextRoute)"
                            />
                        </ct-block>

                        <ct-block name="ct_category_content_empty">
                            <mt-empty-state
                                v-if="showEmptyState"
                                :centered="true"
                                :icon="$route.meta.$module.icon"
                                :headline="$t('ct-category.general.emptyStateHeadline')"
                                :description="$t($route.meta.$module.description)"
                            />
                        </ct-block>
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import { computed, getCurrentInstance, inject, nextTick, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';
import { usePlaceholder } from 'src/app/composables/use-placeholder';
import './store';
import './ct-category-detail.scss';

const { Context } = Contena;
const { Criteria, ChangesetGenerator, EntityCollection } = Contena.Data;

defineOptions({
    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can(this.landingPage ? 'landing_page.editor' : 'category.editor');
            },
            method: 'saveOnLanguageChange',
        },
        ESCAPE: 'cancelEdit',
    },
});

const props = defineProps({
    categoryId: {
        type: String,
        required: false,
        default: null,
    },
    landingPageId: {
        type: String,
        required: false,
        default: null,
    },
});

const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();
const { placeholder } = usePlaceholder();
const instance = getCurrentInstance();
const device = instance?.proxy?.$device;

const acl = inject('acl');
const repositoryFactory = inject('repositoryFactory');
const seoUrlService = inject('seoUrlService');

const term = ref('');
const isLoading = ref(false);
const isCustomFieldLoading = ref(false);
const isSaveSuccessful = ref(false);
const isMobileViewport = ref(false);
const isDisplayingLeavePageWarning = ref(false);
const nextRoute = ref(null);
const currentLanguageId = ref(Contena.Context.api.languageId);
const forceDiscardChanges = ref(false);
const categoryCheckedItem = ref(0);
const landingPageCheckedItem = ref(0);
const entryPointOverwriteConfirmed = ref(false);
const entryPointOverwriteChannels = ref(null);
const splitBreakpoint = 1024;

const changesetGenerator = computed(() => new ChangesetGenerator());
const landingPage = computed(() => Contena.Store.get('ctCategoryDetail').landingPage);
const category = computed(() => Contena.Store.get('ctCategoryDetail').category);
const showEmptyState = computed(() => !category.value && !landingPage.value);
const identifier = computed(() => {
    if (category.value) {
        return placeholder(category.value, 'name');
    }

    return landingPage.value ? placeholder(landingPage.value, 'name') : '';
});
const landingPageRepository = computed(() => repositoryFactory.create('landing_page'));
const categoryRepository = computed(() => repositoryFactory.create('category'));
const customFieldSetRepository = computed(() => repositoryFactory.create('custom_field_set'));
const mediaRepository = computed(() => repositoryFactory.create('media'));
const showEntryPointOverwriteModal = computed(() => {
    return entryPointOverwriteChannels.value !== null && entryPointOverwriteChannels.value.length > 0;
});
const customFieldSetCriteria = computed(() => {
    const criteria = new Criteria(1, null);
    criteria.addFilter(Criteria.equals('relations.entityName', 'category'));

    return criteria;
});
const customFieldSetLandingPageCriteria = computed(() => {
    const criteria = new Criteria(1, null);
    criteria.addFilter(Criteria.equals('relations.entityName', 'landing_page'));

    return criteria;
});
const pageClasses = computed(() => ({
    'has--category': Boolean(category.value),
    'is--mobile': isMobileViewport.value,
}));
const tooltipSave = computed(() => {
    if (!acl.can('category.editor')) {
        return {
            message: t('ct-privileges.tooltip.warning'),
            disabled: false,
            showOnDisabledElements: true,
        };
    }

    return {
        message: `${device?.getSystemKey() ?? 'Ctrl'} + S`,
        appearance: 'light',
    };
});
const landingPageTooltipSave = computed(() => {
    if (!acl.can('landing_page.editor')) {
        return {
            message: t('ct-privileges.tooltip.warning'),
            disabled: false,
            showOnDisabledElements: true,
        };
    }

    return {
        message: `${device?.getSystemKey() ?? 'Ctrl'} + S`,
        appearance: 'light',
    };
});
const tooltipCancel = computed(() => ({
    message: 'ESC',
    appearance: 'light',
}));
const categoryCriteria = computed(() => {
    const criteria = new Criteria(1, 1);
    criteria.getAssociation('seoUrls').addFilter(Criteria.equals('isCanonical', true));
    criteria
        .addAssociation('tags')
        .addAssociation('media')
        .addAssociation('navigationChannels')
        .addAssociation('serviceChannels')
        .addAssociation('footerChannels')
        .addAssociation('translations');

    return criteria;
});
const landingPageCriteria = computed(() => {
    const criteria = new Criteria(1, 1);
    criteria.addAssociation('tags');
    criteria.addAssociation('channels');
    criteria.addAssociation('translations');

    return criteria;
});
const assetFilter = computed(() => Contena.Filter.getByName('asset'));

const categoryCheckedElementsCount = (count) => {
    categoryCheckedItem.value = count;
};
const landingPageCheckedElementsCount = (count) => {
    landingPageCheckedItem.value = count;
};
const checkViewport = () => {
    isMobileViewport.value = (device?.getViewportWidth() ?? window.innerWidth) < splitBreakpoint;
};
const onSearch = (value) => {
    term.value = value.length > 0 ? value : undefined;
};
const loadCustomFieldSet = () => {
    isCustomFieldLoading.value = true;

    return customFieldSetRepository.value
        .search(customFieldSetCriteria.value)
        .then((customFieldSet) => {
            Contena.Store.get('ctCategoryDetail').customFieldSets = customFieldSet;
        })
        .finally(() => {
            isCustomFieldLoading.value = false;
        });
};
const loadLandingPageCustomFieldSet = () => {
    isCustomFieldLoading.value = true;

    return customFieldSetRepository.value
        .search(customFieldSetLandingPageCriteria.value)
        .then((customFieldSet) => {
            Contena.Store.get('ctCategoryDetail').customFieldSets = customFieldSet;
        })
        .finally(() => {
            isCustomFieldLoading.value = false;
        });
};
const setLandingPage = async () => {
    isLoading.value = true;

    try {
        if (props.landingPageId === null) {
            Contena.Store.get('ctCategoryDetail').landingPage = null;
            return;
        }

        Contena.Store.get('ctCategoryDetail').category = null;
        await Contena.Store.get('ctCategoryDetail').loadActiveLandingPage({
            repository: landingPageRepository.value,
            apiContext: Contena.Context.api,
            id: props.landingPageId,
            criteria: landingPageCriteria.value,
        });
        await loadLandingPageCustomFieldSet();
    } catch {
        createNotificationError({
            title: t('global.default.error'),
            message: t('global.notification.unspecifiedSaveErrorMessage'),
        });
    } finally {
        isLoading.value = false;
    }
};
const setCategory = () => {
    isLoading.value = true;

    if (props.categoryId === null) {
        Contena.Store.get('ctCategoryDetail').category = null;
        isLoading.value = false;
        return Promise.resolve();
    }

    Contena.Store.get('ctCategoryDetail').landingPage = null;
    return Contena.Store.get('ctCategoryDetail')
        .loadActiveCategory({
            repository: categoryRepository.value,
            apiContext: Contena.Context.api,
            id: props.categoryId,
            criteria: categoryCriteria.value,
        })
        .then(loadCustomFieldSet)
        .finally(() => {
            isLoading.value = false;
        });
};
const onSaveCategories = () => categoryRepository.value.save(category.value);
const openChangeModal = (destination) => {
    nextRoute.value = destination;
    isDisplayingLeavePageWarning.value = true;
};
const onLeaveModalClose = () => {
    nextRoute.value = null;
    isDisplayingLeavePageWarning.value = false;
};
const onLeaveModalConfirm = (destination) => {
    Contena.Store.get('error').removeApiError('category');
    forceDiscardChanges.value = true;
    isDisplayingLeavePageWarning.value = false;

    void nextTick(() => {
        void router.push({
            name: destination.name,
            params: destination.params,
        });
    });
};
const resetCategory = () => router.push({ name: 'ct.category.index' });
const cancelEdit = () => resetCategory();
const onChangeLanguage = (newLanguageId) => {
    currentLanguageId.value = newLanguageId;

    if (props.landingPageId !== null) {
        void setLandingPage();
    }

    void setCategory();
};
const abortOnLanguageChange = () => {
    if (landingPage.value) {
        return categoryRepository.value.hasChanges(landingPage.value);
    }

    return category.value ? categoryRepository.value.hasChanges(category.value) : false;
};
const saveOnLanguageChange = () => {
    if (landingPage.value) {
        return onSaveLandingPage();
    }

    return onSave();
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
};
const updateSeoUrls = () => {
    if (!Contena.Store.list().includes('ctSeoUrl')) {
        return Promise.resolve();
    }

    const seoUrls = Contena.Store.get('ctSeoUrl').newOrModifiedUrls;

    return Promise.all(
        seoUrls.map((seoUrl) => {
            if (!seoUrl.seoPathInfo) {
                return Promise.resolve();
            }

            seoUrl.isModified = true;
            return seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId).catch((error) => {
                if (error.response?.data?.errors) {
                    error.response.data.errors.forEach((apiError) => {
                        const messageKey = `global.error-codes.${apiError.detail}`;
                        const params = apiError.meta?.parameters || {};
                        const translatedMessage = t(messageKey, params);
                        const errorMessage =
                            translatedMessage !== messageKey
                                ? translatedMessage
                                : apiError.detail || apiError.title || t('global.notification.unspecifiedSaveErrorMessage');

                        createNotificationError({ message: errorMessage });
                    });
                } else {
                    createNotificationError({
                        message: error.message || t('global.notification.unspecifiedSaveErrorMessage'),
                    });
                }

                throw error;
            });
        }),
    );
};
const checkForEntryPointOverwrite = () => {
    entryPointOverwriteChannels.value = new EntityCollection('/channel', 'channel', Context.api);

    category.value.navigationChannels.forEach((channel) => {
        if (channel.navigationCategoryId !== null && channel.navigationCategoryId !== props.categoryId) {
            entryPointOverwriteChannels.value.add(channel);
        }
    });
    category.value.footerChannels.forEach((channel) => {
        if (channel.footerCategoryId !== null && channel.footerCategoryId !== props.categoryId) {
            entryPointOverwriteChannels.value.add(channel);
        }
    });
    category.value.serviceChannels.forEach((channel) => {
        if (channel.serviceCategoryId !== null && channel.serviceCategoryId !== props.categoryId) {
            entryPointOverwriteChannels.value.add(channel);
        }
    });
};
const cancelEntryPointOverwrite = () => {
    entryPointOverwriteChannels.value = null;
};
const onSave = async () => {
    isSaveSuccessful.value = false;

    if (!entryPointOverwriteConfirmed.value) {
        checkForEntryPointOverwrite();
        if (showEntryPointOverwriteModal.value) {
            return Promise.resolve();
        }
    }

    isLoading.value = true;

    try {
        await updateSeoUrls();
        await categoryRepository.value.save(category.value, { ...Contena.Context.api });
        isSaveSuccessful.value = true;
        entryPointOverwriteConfirmed.value = false;

        return setCategory();
    } catch (error) {
        isLoading.value = false;
        entryPointOverwriteConfirmed.value = false;

        if (!error.response?.data?.errors) {
            createNotificationError({
                message: t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
            });
        }

        throw error;
    }
};
const confirmEntryPointOverwrite = () => {
    entryPointOverwriteChannels.value = null;
    entryPointOverwriteConfirmed.value = true;
    void nextTick(() => void onSave());
};
const addLandingPageChannelError = () => {
    const channelError = new Contena.Classes.ContenaError({
        code: 'landing_page_channel_blank',
        detail: 'This value should not be blank.',
        status: '400',
    });

    Contena.Store.get('error').addApiError({
        expression: `landing_page.${landingPage.value.id}.channels`,
        error: channelError,
    });
    createNotificationError({
        message: t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
    });
};
const onSaveLandingPage = () => {
    isSaveSuccessful.value = false;

    if (props.landingPageId !== 'create' && landingPage.value.channels.length === 0) {
        addLandingPageChannelError();
        return Promise.resolve();
    }

    isLoading.value = true;

    return landingPageRepository.value
        .save(landingPage.value, Contena.Context.api)
        .then(() => {
            isSaveSuccessful.value = true;

            if (props.landingPageId === 'create') {
                return router.push({
                    name: 'ct.category.landingPageDetail',
                    params: { id: landingPage.value.id },
                });
            }

            return setLandingPage();
        })
        .catch(() => {
            isLoading.value = false;

            if (landingPage.value.channels.length === 0) {
                addLandingPageChannelError();
                return;
            }

            createNotificationError({
                message: t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
            });
        });
};
const onLandingPageDelete = () => {
    Contena.Store.get('ctCategoryDetail').landingPagesToDelete = null;
};
const onCategoryDelete = () => {
    Contena.Store.get('ctCategoryDetail').categoriesToDelete = null;
};

watch(
    () => props.landingPageId,
    () => void setLandingPage(),
);
watch(
    () => props.categoryId,
    () => void setCategory(),
);

onMounted(() => {
    checkViewport();
    device?.onResize({
        listener: checkViewport,
        component: instance?.proxy,
    });

    if (props.categoryId !== null) {
        void setCategory();
        return;
    }

    void setLandingPage();
});

ctDefinePublic({
    acl,
    term,
    isLoading,
    isCustomFieldLoading,
    isSaveSuccessful,
    isMobileViewport,
    isDisplayingLeavePageWarning,
    nextRoute,
    currentLanguageId,
    forceDiscardChanges,
    categoryCheckedItem,
    landingPageCheckedItem,
    entryPointOverwriteConfirmed,
    entryPointOverwriteChannels,
    changesetGenerator,
    showEmptyState,
    identifier,
    landingPageRepository,
    categoryRepository,
    landingPage,
    category,
    showEntryPointOverwriteModal,
    customFieldSetRepository,
    customFieldSetCriteria,
    customFieldSetLandingPageCriteria,
    mediaRepository,
    pageClasses,
    tooltipSave,
    landingPageTooltipSave,
    tooltipCancel,
    categoryCriteria,
    landingPageCriteria,
    assetFilter,
    categoryCheckedElementsCount,
    landingPageCheckedElementsCount,
    onSearch,
    setLandingPage,
    setCategory,
    loadCustomFieldSet,
    loadLandingPageCustomFieldSet,
    onSaveCategories,
    openChangeModal,
    onLeaveModalClose,
    onLeaveModalConfirm,
    cancelEdit,
    resetCategory,
    onChangeLanguage,
    abortOnLanguageChange,
    saveOnLanguageChange,
    saveFinish,
    onSave,
    checkForEntryPointOverwrite,
    cancelEntryPointOverwrite,
    confirmEntryPointOverwrite,
    onSaveLandingPage,
    addLandingPageChannelError,
    updateSeoUrls,
    onLandingPageDelete,
    onCategoryDelete,
    checkViewport,
});

usePageTitle(() => identifier.value);

onBeforeRouteLeave((to) => {
    if (forceDiscardChanges.value) {
        forceDiscardChanges.value = false;
        return true;
    }

    if (!category.value) {
        return true;
    }

    const { changes, deletionQueue } = changesetGenerator.value.generate(category.value);
    if (changes === null) {
        return true;
    }

    const changedKeys = Object.keys(changes).filter(
        (key) =>
            ![
                'id',
                'versionId',
            ].includes(key),
    );
    if (changedKeys.length === 0 && deletionQueue.length === 0) {
        return true;
    }

    isDisplayingLeavePageWarning.value = true;
    nextRoute.value = to;

    return false;
});

defineExpose({
    acl,
    term,
    isLoading,
    isCustomFieldLoading,
    isSaveSuccessful,
    isMobileViewport,
    isDisplayingLeavePageWarning,
    nextRoute,
    currentLanguageId,
    forceDiscardChanges,
    categoryCheckedItem,
    landingPageCheckedItem,
    entryPointOverwriteConfirmed,
    entryPointOverwriteChannels,
    changesetGenerator,
    showEmptyState,
    identifier,
    landingPageRepository,
    categoryRepository,
    landingPage,
    category,
    showEntryPointOverwriteModal,
    customFieldSetRepository,
    customFieldSetCriteria,
    customFieldSetLandingPageCriteria,
    mediaRepository,
    pageClasses,
    tooltipSave,
    landingPageTooltipSave,
    tooltipCancel,
    categoryCriteria,
    landingPageCriteria,
    assetFilter,
    categoryCheckedElementsCount,
    landingPageCheckedElementsCount,
    onSearch,
    setLandingPage,
    setCategory,
    loadCustomFieldSet,
    loadLandingPageCustomFieldSet,
    onSaveCategories,
    openChangeModal,
    onLeaveModalClose,
    onLeaveModalConfirm,
    cancelEdit,
    resetCategory,
    onChangeLanguage,
    abortOnLanguageChange,
    saveOnLanguageChange,
    saveFinish,
    onSave,
    checkForEntryPointOverwrite,
    cancelEntryPointOverwrite,
    confirmEntryPointOverwrite,
    onSaveLandingPage,
    addLandingPageChannelError,
    updateSeoUrls,
    onLandingPageDelete,
    onCategoryDelete,
    checkViewport,
});
</script>
