<template>
    <ct-block name="sw_settings_custom_field_set_detail">
        <ct-page class="ct-settings-custom-field-set-create ct-settings-set-detail">
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
                                <mt-card
                                    class="ct-settings-set-create__option-list-empty-state__wrapper"
                                    position-identifier="ct-settings-custom-field-set-create"
                                >
                                    <ct-block
                                        name="sw_settings_custom_field_set_detail_content_detail_custom_field_list_empty_state"
                                    >
                                        <mt-empty-state
                                            class="ct-settings-set-create__option-list-empty-state__empty-state"
                                            :icon="$route.meta.$module.icon"
                                            :headhline="$t('ct-settings-custom-field.set.detail.messageCustomFieldsEmpty')"
                                            :description="$t('ct-settings-custom-field.set.detail.emptyStateDescription')"
                                        />
                                    </ct-block>
                                </mt-card>
                            </ct-block>
                        </div>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-settings-custom-field-set-create.scss';

const { Criteria } = Contena.Data;
const utils = Contena.Utils;

defineProps({});

import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();
const {
    set,
    setId,
    isLoading,
    isSaveSuccessful,
    technicalNameError,
    customFieldSetRepository,
    onSave: parentOnSave,
} = Contena.Component.getExtensionParentSetup();
const createdComponent = async () => {
    isLoading.value = true;
    set.value = await customFieldSetRepository.value.create(Contena.Context.api, route.params.id || utils.createId());
    set.value.name = 'custom_';
    set.value.config = {};
    setId.value = set.value.id;
    isLoading.value = false;
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
    void router.push({
        name: 'ct.settings.custom.field.detail',
        params: { id: setId.value },
    });
};
const createNameNotUniqueNotification = () => {
    const message = t('ct-settings-custom-field.set.detail.messageNameNotUnique');
    createNotificationError({ title: t('global.default.error'), message });
    technicalNameError.value = { detail: message };
};
const onSave = () => {
    isLoading.value = true;

    if (!set.value?.name) {
        const message = t('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3');
        createNotificationError({ title: t('global.default.error'), message });
        technicalNameError.value = { detail: message };
        isLoading.value = false;

        return;
    }

    const criteria = new Criteria(1, 25);
    criteria.addFilter(Criteria.equals('name', set.value.name));
    customFieldSetRepository.value.search(criteria).then((result) => {
        if (result.length === 0) {
            parentOnSave.value();

            return;
        }

        createNameNotUniqueNotification();
        isLoading.value = false;
    });
};

void createdComponent();

swDefinePublic({
    createdComponent,
    saveFinish,
    onSave,
    createNameNotUniqueNotification,
});

defineExpose({ createdComponent, saveFinish, onSave, createNameNotUniqueNotification });
</script>
