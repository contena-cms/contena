<template>
    <ct-block name="sw_settings_custom_field_set_detail">
        <ct-page class="ct-settings-custom-field-set-detail ct-settings-set-detail">
            <template #smart-bar-header>
                <ct-block name="sw_settings_customField_set_detail_header">
                    <h2 v-if="set && set.config && getInlineSnippet(set.config.label)">
                        {{ getInlineSnippet(set.config.label) }}
                    </h2>
                    <h2 v-else>
                        {{ $t('ct-settings-custom-field.set.detail.textHeadline') }}
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_custom_field_set_detail_actions">
                    <ct-block name="sw_settings_custom_field_set_detail_actions_abort">
                        <mt-button :disabled="set.isLoading" variant="secondary" size="default" @click="onCancel">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_settings_custom_field_set_detail_actions_save">
                        <ct-button-process
                            size="default"
                            class="ct-settings-set-detail__save-action"
                            :is-loading="isLoading"
                            :process-success="isSaveSuccessful"
                            :disabled="set.isLoading || !acl.can('custom_field.editor')"
                            variant="primary"
                            @update:process-success="saveFinish"
                            @click.prevent="onSave"
                        >
                            {{ $t('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_custom_field_set_detail_content">
                    <ct-card-view>
                        <template v-if="isLoading">
                            <ct-skeleton />
                            <ct-skeleton />
                        </template>

                        <!-- v-show is used here as underlying components influence the loading state and v-if would destroy this behaviour -->
                        <div v-show="!isLoading">
                            <ct-block name="sw_settings_custom_field_set_detail_content_detail_base">
                                <ct-custom-field-set-detail-base
                                    :set="set"
                                    :technical-name-error="technicalNameError"
                                    @reset-errors="onResetErrors"
                                />
                            </ct-block>

                            <ct-block name="sw_settings_custom_field_set_detail_content_detail_custom_field_list">
                                <ct-custom-field-list
                                    v-if="set.id"
                                    ref="customFieldList"
                                    :set="set"
                                    @loading-changed="onLoadingChanged"
                                />
                            </ct-block>
                        </div>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-settings-custom-field-set-detail.scss';

const { Criteria } = Contena.Data;

defineProps({});

import { ref, computed, inject } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';
import { usePageTitle } from 'src/app/composables/use-page-title';
import { useNotification } from 'src/app/composables/use-notification';
import { useDiscardDetailPageChanges } from 'src/app/composables/use-discard-detail-page-changes';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const { getInlineSnippet } = useInlineSnippet();
const { createNotificationSuccess, createNotificationError } = useNotification();

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const set = ref({});
const setId = ref('');
const isLoading = ref(true);
const isSaveSuccessful = ref(false);
const technicalNameError = ref(null);

const identifier = computed(() => {
    return set.value.config && getInlineSnippet(set.value.config.label)
        ? getInlineSnippet(set.value.config.label)
        : set.value.name;
});
const customFieldSetRepository = computed(() => {
    return repositoryFactory.create('custom_field_set');
});
const customFieldRepository = computed(() => {
    return repositoryFactory.create('custom_field');
});
const customFieldCriteria = computed(() => {
    const criteria = new Criteria(1, 25);
    criteria.addFilter(Criteria.equals('customFieldSetId', setId.value));

    return criteria;
});
const customFieldSetCriteria = computed(() => {
    const criteria = new Criteria(1, 25);

    criteria.addAssociation('relations');

    return criteria;
});

const createdComponent = () => {
    if (route.params.id) {
        setId.value = route.params.id.toLowerCase();
        void loadEntityData();
    }
};
const loadEntityData = async () => {
    set.value = await customFieldSetRepository.value.get(setId.value, Contena.Context.api, customFieldSetCriteria.value);
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
};
const onCancel = () => {
    void router.push({ name: 'ct.settings.custom.field.index' });
};
const abortOnLanguageChange = () => {
    return customFieldSetRepository.value.hasChanges(set.value);
};
const onChangeLanguage = () => {
    void loadEntityData();
};
const onLoadingChanged = (loading) => {
    isLoading.value = loading;
};
const onResetErrors = () => {
    technicalNameError.value = null;
};
const onSave = async () => {
    const setLabel = identifier.value;
    isSaveSuccessful.value = false;
    isLoading.value = true;

    if (!set.value.config.translated) {
        const fallbackLocale = Contena.Context.app.fallbackLocale;
        set.value.config.label = {
            [fallbackLocale]: set.value.config.label[fallbackLocale],
        };
    }
    set.value.relations ??= [];

    try {
        await customFieldSetRepository.value.save(set.value);
        isSaveSuccessful.value = true;
        createNotificationSuccess({
            title: t('global.default.success'),
            message: t('ct-settings-custom-field.set.detail.messageSaveSuccess', { name: setLabel }),
        });
        await loadEntityData();
    } catch (error) {
        createNotificationError({
            message: error?.response?.data?.errors?.[0]?.detail ?? 'Error',
        });
    } finally {
        isLoading.value = false;
    }
};
const saveOnLanguageChange = () => onSave();

createdComponent();

swDefinePublic({
    repositoryFactory,
    acl,
    set,
    setId,
    isLoading,
    isSaveSuccessful,
    technicalNameError,
    identifier,
    customFieldSetRepository,
    customFieldRepository,
    customFieldCriteria,
    customFieldSetCriteria,
    createdComponent,
    loadEntityData,
    saveFinish,
    onCancel,
    abortOnLanguageChange,
    onChangeLanguage,
    onLoadingChanged,
    onResetErrors,
    onSave,
    saveOnLanguageChange,
});
usePageTitle(() => identifier.value);
const { discardChanges } = useDiscardDetailPageChanges(route, { set: () => set.value });

defineExpose({
    discardChanges,
    getInlineSnippet,
    repositoryFactory,
    acl,
    set,
    setId,
    isLoading,
    isSaveSuccessful,
    technicalNameError,
    identifier,
    customFieldSetRepository,
    customFieldRepository,
    customFieldCriteria,
    customFieldSetCriteria,
    createdComponent,
    loadEntityData,
    saveFinish,
    onCancel,
    abortOnLanguageChange,
    onChangeLanguage,
    onLoadingChanged,
    onResetErrors,
    onSave,
    saveOnLanguageChange,
});
</script>
