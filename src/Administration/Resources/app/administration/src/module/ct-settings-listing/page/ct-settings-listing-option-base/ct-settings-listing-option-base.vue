<template>
    <ct-block name="sw_settings_listing_option_base">
        <ct-page class="ct-settings-listing-base">
            <template #smart-bar-header>
                <ct-block name="sw_settings_listing_option_base_smart_bar_heading">
                    <h2>{{ smartBarHeading }}</h2>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="sw_settings_listing_option_base_language_switch">
                    <ct-language-switch @on-change="onChangeLanguage" />
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_listing_option_base_smart_bar_actions">
                    <mt-button
                        variant="primary"
                        :is-loading="isLoading"
                        :disabled="isSaveButtonDisabled || undefined"
                        @click="onSave"
                    >
                        {{ t('global.default.save') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_listing_option_base_content">
                    <ct-card-view>
                        <mt-loader v-if="isLoading && !blogSortingEntity" />

                        <template v-if="blogSortingEntity">
                            <ct-block name="sw_settings_listing_option_base_content_locked_info">
                                <mt-banner
                                    v-if="blogSortingEntity.locked"
                                    class="ct-settings-listing-base__locked-info"
                                    variant="info"
                                    :title="t('global.default.info')"
                                >
                                    {{ t('ct-settings-listing.base.lockedInfo') }}
                                </mt-banner>
                            </ct-block>

                            <ct-block name="sw_settings_listing_option_base_content_general_info">
                                <ct-settings-listing-option-general-info
                                    :sorting-option="blogSortingEntity"
                                    :is-default-sorting="isDefaultSorting"
                                    :label-error="sortingOptionLabelError"
                                    :technical-name-error="sortingOptionTechnicalNameError"
                                />
                            </ct-block>

                            <ct-block name="sw_settings_listing_option_base_content_criteria_grid">
                                <ct-settings-listing-option-criteria-grid
                                    :blog-sorting-entity="blogSortingEntity"
                                    @changed="clearValidationErrors"
                                    @delete-requested="onDeleteCriteria"
                                />
                            </ct-block>
                        </template>
                    </ct-card-view>

                    <ct-block name="sw_settings_listing_option_base_content_delete_modal">
                        <ct-settings-listing-delete-modal
                            v-if="toBeDeletedCriteriaIndex !== null"
                            :title="t('ct-settings-listing.base.delete.modalTitle')"
                            :description="t('ct-settings-listing.base.delete.modalDescription')"
                            @cancel="toBeDeletedCriteriaIndex = null"
                            @delete="onConfirmDeleteCriteria"
                        />
                    </ct-block>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';

import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';
import './ct-settings-listing-option-base.scss';

interface BlogSortingField {
    field: string;
    order: 'asc' | 'desc';
    priority: number;
    naturalSorting: boolean | number | null;
}

interface BlogSortingEntity {
    id: string;
    label: string;
    key: string;
    priority: number;
    active: boolean;
    locked: boolean;
    fields: BlogSortingField[];
}

interface SystemConfigApiService {
    getValues(domain: string): Promise<Record<string, unknown>>;
}

const { Criteria } = Contena.Data;
const { ContenaError } = Contena.Classes;
const props = withDefaults(defineProps<{ createMode?: boolean }>(), { createMode: false });
const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const { createNotificationError, createNotificationSuccess } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const systemConfigApiService = inject<SystemConfigApiService>('systemConfigApiService');
if (!repositoryFactory || !systemConfigApiService) {
    throw new Error('The repository factory or system config service is unavailable.');
}

const blogSortingRepository = repositoryFactory.create('blog_sorting' as keyof EntitySchema.Entities);
const blogSortingEntity = ref<BlogSortingEntity | null>(null);
const defaultSortingId = ref<string | null>(null);
const toBeDeletedCriteriaIndex = ref<number | null>(null);
const sortingOptionTechnicalNameError = ref<ContenaError | null>(null);
const sortingOptionLabelError = ref<ContenaError | null>(null);
const isLoading = ref(false);

const smartBarHeading = computed(
    () =>
        blogSortingEntity.value?.label ||
        t(props.createMode ? 'ct-settings-listing.create.smartBarTitle' : 'ct-settings-listing.base.smartBarTitle'),
);
const isSaveButtonDisabled = computed(() => {
    const sorting = blogSortingEntity.value;
    return (
        !sorting ||
        sorting.fields.length === 0 ||
        sorting.fields.some((field) => !field.field || field.field === 'customField')
    );
});
const isDefaultSorting = computed(() => defaultSortingId.value === blogSortingEntity.value?.id);

const loadBlogSorting = async (): Promise<void> => {
    isLoading.value = true;
    try {
        if (props.createMode) {
            const entity = blogSortingRepository.create(Contena.Context.api) as unknown as BlogSortingEntity;
            entity.label = '';
            entity.key = '';
            entity.priority = 1;
            entity.active = false;
            entity.locked = false;
            entity.fields = [];
            blogSortingEntity.value = entity;
            return;
        }

        const id = typeof route.params.id === 'string' ? route.params.id.toLowerCase() : '';
        const entity = await blogSortingRepository.get(id, Contena.Context.api);
        if (!entity) {
            await router.replace({ name: 'ct.settings.listing.index' });
            return;
        }

        const sorting = entity as unknown as BlogSortingEntity;
        if (!Array.isArray(sorting.fields)) sorting.fields = [];
        blogSortingEntity.value = sorting;
    } finally {
        isLoading.value = false;
    }
};
const fetchDefaultSorting = async (): Promise<void> => {
    const values = await systemConfigApiService.getValues('core.listing');
    defaultSortingId.value =
        typeof values['core.listing.defaultSorting'] === 'string' ? values['core.listing.defaultSorting'] : null;
};
const searchForAlreadyExistingKey = async (key: string): Promise<boolean> => {
    if (!key || !blogSortingEntity.value) return false;

    const criteria = new Criteria(1, 1);
    criteria.addFilter(Criteria.equals('key', key));
    const result = await blogSortingRepository.search(criteria, Contena.Context.api);
    const existing = result.first();
    return Boolean(existing && existing.id !== blogSortingEntity.value.id);
};
const isValidSortingOption = async (): Promise<boolean> => {
    const sorting = blogSortingEntity.value;
    if (!sorting) return false;

    clearValidationErrors();
    if (!sorting.key) {
        sortingOptionTechnicalNameError.value = new ContenaError({
            code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
        });
    } else if (await searchForAlreadyExistingKey(sorting.key)) {
        sortingOptionTechnicalNameError.value = new ContenaError({ code: 'DUPLICATED_NAME' });
    }

    if (!sorting.label) {
        sortingOptionLabelError.value = new ContenaError({
            code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
        });
    }

    return !(sortingOptionTechnicalNameError.value || sortingOptionLabelError.value);
};
const onSave = async (): Promise<void> => {
    const sorting = blogSortingEntity.value;
    if (!sorting || !(await isValidSortingOption())) return;

    isLoading.value = true;
    try {
        await blogSortingRepository.save(sorting as unknown as Entity<keyof EntitySchema.Entities>, Contena.Context.api);
        createNotificationSuccess({
            message: t('ct-settings-listing.base.notification.saveSuccess', {
                sortingOptionName: sorting.label,
            }),
        });

        if (props.createMode) {
            await router.replace({ name: 'ct.settings.listing.edit', params: { id: sorting.id } });
        }
    } catch {
        createNotificationError({
            message: t('ct-settings-listing.base.notification.saveError', {
                sortingOptionName: sorting.label,
            }),
        });
    } finally {
        isLoading.value = false;
    }
};
const onDeleteCriteria = (index: number): void => {
    toBeDeletedCriteriaIndex.value = index;
};
const onConfirmDeleteCriteria = async (): Promise<void> => {
    if (!blogSortingEntity.value || toBeDeletedCriteriaIndex.value === null) return;

    blogSortingEntity.value.fields.splice(toBeDeletedCriteriaIndex.value, 1);
    toBeDeletedCriteriaIndex.value = null;
    if (!props.createMode && blogSortingEntity.value.fields.length > 0) await onSave();
};
const onChangeLanguage = (): void => {
    if (!props.createMode) void loadBlogSorting();
};
const clearValidationErrors = (): void => {
    sortingOptionTechnicalNameError.value = null;
    sortingOptionLabelError.value = null;
};

void Promise.all([
    loadBlogSorting(),
    fetchDefaultSorting(),
]);

swDefinePublic({
    blogSortingEntity,
    defaultSortingId,
    toBeDeletedCriteriaIndex,
    sortingOptionTechnicalNameError,
    sortingOptionLabelError,
    isLoading,
    smartBarHeading,
    isSaveButtonDisabled,
    isDefaultSorting,
    loadBlogSorting,
    fetchDefaultSorting,
    isValidSortingOption,
    searchForAlreadyExistingKey,
    onSave,
    onDeleteCriteria,
    onConfirmDeleteCriteria,
    onChangeLanguage,
    clearValidationErrors,
});

usePageTitle();

defineExpose({
    blogSortingEntity,
    defaultSortingId,
    toBeDeletedCriteriaIndex,
    sortingOptionTechnicalNameError,
    sortingOptionLabelError,
    isLoading,
    smartBarHeading,
    isSaveButtonDisabled,
    isDefaultSorting,
    loadBlogSorting,
    fetchDefaultSorting,
    isValidSortingOption,
    searchForAlreadyExistingKey,
    onSave,
    onDeleteCriteria,
    onConfirmDeleteCriteria,
    onChangeLanguage,
    clearValidationErrors,
});
</script>
