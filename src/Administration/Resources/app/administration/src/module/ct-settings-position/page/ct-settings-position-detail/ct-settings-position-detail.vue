<template>
    <ct-block name="ct_settings_position_detail">
        <ct-page class="ct-settings-position-detail">
            <template #smart-bar-back>
                <ct-block name="ct_settings_position_detail_back">
                    <mt-button variant="tertiary" size="small" @click="onCancel">
                        <mt-icon name="regular-chevron-left-s" size="16px" />
                        {{ translate('global.default.back') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_settings_position_detail_header">
                    <h2>{{ identifier }}</h2>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="ct_settings_position_detail_language">
                    <ct-language-switch :disabled="createMode || undefined" @on-change="onChangeLanguage" />
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_position_detail_actions">
                    <mt-button variant="secondary" size="default" :disabled="isLoading || undefined" @click="onCancel">
                        {{ translate('global.default.cancel') }}
                    </mt-button>
                    <ct-button-process
                        v-model:process-success="isSaveSuccessful"
                        class="ct-settings-position-detail__save-action"
                        variant="primary"
                        size="default"
                        :is-loading="isLoading"
                        :disabled="!allowSave || !position || !position.name || !position.code || undefined"
                        @click.prevent="onSave"
                    >
                        {{ translate('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_position_detail_content">
                    <ct-card-view>
                        <div class="ct-settings-position-detail__content">
                            <ct-language-info
                                v-if="position"
                                :entity-description="identifier"
                                :is-new-entity="isNewPosition"
                            />
                            <mt-card
                                position-identifier="ct-settings-position-detail-content"
                                :is-loading="isLoading || !position"
                            >
                                <mt-position-form
                                    v-if="position"
                                    :position-entity="position"
                                    :custom-field-sets="customFieldSets"
                                    :disabled="!allowSave"
                                    @update:position="onUpdatePosition"
                                />
                            </mt-card>
                        </div>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

import { computed, inject, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

type Position = Entity<'position'>;

const { Criteria } = Contena.Data;
const props = defineProps({
    createMode: {
        type: Boolean,
        default: false,
    },
});
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const translate = t;
const { createNotificationError, createNotificationSuccess } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const customFieldDataProviderService = inject<{
    // The tuple documents the injected service contract.

    getCustomFieldSets: (...args: [string]) => Promise<unknown[]>;
}>('customFieldDataProviderService');
const positionRepository = computed(() => repositoryFactory?.create('position'));
const position = ref<Position | null>(null);
const customFieldSets = ref<unknown[]>([]);
const isLoading = ref(true);
const isSaveSuccessful = ref(false);
const isNewPosition = computed(() => Boolean(position.value?.isNew?.()));
const identifier = computed(() => {
    if (!position.value) return translate('ct-settings-position.detail.titleEdit');
    return (
        position.value.translated?.name ||
        position.value.name ||
        position.value.code ||
        translate(isNewPosition.value ? 'ct-settings-position.detail.titleNew' : 'ct-settings-position.detail.titleEdit')
    );
});
const allowSave = computed(() =>
    isNewPosition.value ? Boolean(acl?.can('position.creator')) : Boolean(acl?.can('position.editor')),
);
const switchToSystemLanguage = (): void => {
    if (Contena.Context.api.languageId !== Contena.Context.api.systemLanguageId) {
        Contena.Store.get('context').setApiLanguageId(Contena.Context.api.systemLanguageId);
        Contena.Utils.EventBus.emit('on-change-language-clicked', Contena.Context.api.systemLanguageId);
    }
};
const loadPosition = async (): Promise<void> => {
    if (!positionRepository.value) return;
    isLoading.value = true;
    try {
        if (props.createMode) {
            switchToSystemLanguage();
            const newPosition = positionRepository.value.create(Contena.Context.api);
            newPosition.active = true;
            const criteria = new Criteria(1, 1);
            criteria.addSorting(Criteria.sort('position', 'DESC'));
            const result = await positionRepository.value.search(criteria, Contena.Context.api);
            newPosition.position = (result.first()?.position ?? 0) + 10;
            position.value = newPosition;
        } else if (route.params.id) {
            position.value = await positionRepository.value.get(route.params.id as string, Contena.Context.api);
        }
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-position.notification.loadError'),
        });
    } finally {
        isLoading.value = false;
    }
};
const loadCustomFieldSets = async (): Promise<void> => {
    customFieldSets.value = (await customFieldDataProviderService?.getCustomFieldSets('position')) ?? [];
};
const onUpdatePosition = (path: string, value: unknown): void => {
    if (position.value) Contena.Utils.object.set(position.value, path, value);
};
const onSave = async (): Promise<void> => {
    if (!positionRepository.value || !position.value || !allowSave.value) return;
    isLoading.value = true;
    const wasNew = isNewPosition.value;
    try {
        await positionRepository.value.save(position.value, Contena.Context.api);
        isSaveSuccessful.value = true;
        createNotificationSuccess({
            title: translate('global.default.success'),
            message: translate('ct-settings-position.notification.saveSuccess'),
        });
        if (wasNew) {
            await router.replace({ name: 'ct.settings.position.detail', params: { id: position.value.id } });
        } else {
            await loadPosition();
        }
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-position.notification.saveError'),
        });
    } finally {
        isLoading.value = false;
    }
};
const onCancel = (): void => {
    void router.push({ name: 'ct.settings.position.index' });
};
const onChangeLanguage = (): void => {
    if (!props.createMode) void loadPosition();
};

void Promise.all([
    loadPosition(),
    loadCustomFieldSets(),
]);

ctDefinePublic({
    position,
    customFieldSets,
    positionRepository,
    isLoading,
    isSaveSuccessful,
    isNewPosition,
    identifier,
    allowSave,
    loadPosition,
    loadCustomFieldSets,
    onUpdatePosition,
    onSave,
    onCancel,
    onChangeLanguage,
});

usePageTitle(() => identifier.value);

defineExpose({
    position,
    customFieldSets,
    positionRepository,
    isLoading,
    isSaveSuccessful,
    isNewPosition,
    identifier,
    allowSave,
    loadPosition,
    loadCustomFieldSets,
    onUpdatePosition,
    onSave,
    onCancel,
    onChangeLanguage,
});
</script>

<style scoped lang="scss">
.ct-settings-position-detail__content {
    width: 100%;
    max-width: 996px;
    margin: 0 auto;

    .mt-card {
        margin-top: var(--scale-size-24);
    }
}
</style>
