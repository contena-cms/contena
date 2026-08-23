<template>
    <ct-block name="sw_channel_detail">
        <ct-page class="ct-channel-detail">
            <template #smart-bar-header>
                <ct-block name="sw_channel_detail_header">
                    <h2>{{ pageTitle }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_channel_detail_actions">
                    <mt-button
                        v-tooltip.bottom="saveTooltip"
                        variant="primary"
                        :disabled="!allowSaving || undefined"
                        :is-loading="isLoading"
                        @click="onSave"
                    >
                        {{ t('global.default.save') }}
                    </mt-button>
                </ct-block>
            </template>

            <template v-if="!createMode" #language-switch>
                <ct-block name="sw_channel_detail_language_switch">
                    <ct-language-switch
                        :save-changes-function="saveOnLanguageChange"
                        :abort-change-function="abortOnLanguageChange"
                        @on-change="onChangeLanguage"
                    />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_channel_detail_content">
                    <ct-card-view position-identifier="ct-channel-detail">
                        <ct-block name="sw_channel_detail_language_info">
                            <template v-if="channel && !createMode">
                                <ct-language-info :entity-description="pageTitle" />
                            </template>
                        </ct-block>

                        <ct-block name="sw_channel_detail_content_tabs">
                            <template v-if="!createMode">
                                <mt-tabs
                                    v-if="channel"
                                    class="ct-channel-detail__tabs"
                                    position-identifier="ct-channel-detail"
                                    :default-item="$route.name"
                                    :items="tabs"
                                    :small="true"
                                />
                            </template>
                        </ct-block>

                        <ct-block name="sw_channel_detail_content_view">
                            <template v-if="isLoading && !channel">
                                <ct-skeleton />
                                <ct-skeleton />
                            </template>
                            <router-view v-else-if="channel" :key="$route.params.id" v-slot="{ Component }">
                                <component
                                    :is="Component"
                                    :channel="channel"
                                    :is-loading="isLoading"
                                    :create-mode="createMode"
                                />
                            </router-view>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
/* global Entity, EntityCollection */
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import type AclService from 'src/app/service/acl.service';
import type ChannelApiService from 'src/core/service/api/channel.api.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-channel-detail.scss';

type Tab = { name: string; label: string; disabled?: boolean; onClick: () => void };
const props = defineProps({
    createMode: { type: Boolean, default: false },
});
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const channelService = inject<ChannelApiService>('channelService');
if (!repositoryFactory || !acl || !channelService) {
    throw new Error('The Channel detail services are unavailable.');
}

const channelRepository = computed(() => repositoryFactory.create('channel', undefined, { useSync: true }));
const channel = ref<Entity<'channel'> | null>(null);
const isLoading = ref(false);
const pageTitle = computed(() =>
    props.createMode
        ? t('ct-channel.detail.titleNew')
        : channel.value?.translated?.name || channel.value?.name || t('ct-channel.detail.titleEdit'),
);
const createRouteTab = (label: string, name: string, disabled = false): Tab => ({
    label: t(label),
    name,
    disabled,
    onClick: () => openTab(name),
});
const tabs = computed<Tab[]>(() => [
    createRouteTab('ct-channel.detail.tabBase', 'ct.channel.detail.base'),
    createRouteTab('ct-channel.detail.tabTheme', 'ct.channel.detail.theme', isLoading.value),
]);
const allowSaving = computed(() => (props.createMode ? acl.can('channel.creator') : acl.can('channel.editor')));
const saveTooltip = computed(() => ({
    message: t('ct-privileges.tooltip.warning'),
    disabled: allowSaving.value,
    showOnDisabledElements: true,
}));

const getLoadCriteria = (): InstanceType<typeof Contena.Data.Criteria> => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.addAssociation('type');
    criteria
        .getAssociation('languages')
        .addSorting(Contena.Data.Criteria.sort('name', 'ASC'))
        .addFilter(Contena.Data.Criteria.equals('active', true));
    criteria.getAssociation('countries').addSorting(Contena.Data.Criteria.sort('name', 'ASC'));
    criteria.addAssociation('domains.language');
    criteria.addAssociation('domains.snippetSet');
    criteria.addAssociation('themes');
    return criteria;
};
const loadChannel = async (): Promise<void> => {
    const id = String(route.params.id || '').toLowerCase();
    if (!id) return;
    isLoading.value = true;
    try {
        channel.value = await channelRepository.value.get(id, Contena.Context.api, getLoadCriteria());
    } catch {
        createNotificationError({ message: t('ct-channel.detail.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const ensureAssociation = async <EntityName extends 'language' | 'country'>(
    entityName: EntityName,
    id: string | undefined,
    collection: EntityCollection<EntityName> | undefined,
): Promise<void> => {
    if (!id || !collection || collection.has(id)) return;
    const entity = await repositoryFactory.create(entityName).get(id, Contena.Context.api);
    if (entity) collection.add(entity);
};
const createChannel = async (): Promise<void> => {
    const typeId = String(route.params.typeId || '');
    if (!typeId) return;
    isLoading.value = true;
    try {
        const entity = channelRepository.value.create(Contena.Context.api);
        entity.typeId = typeId;
        entity.active = false;
        entity.maintenance = false;
        entity.navigationCategoryDepth = 2;
        entity.languageId = Contena.Store.get('context').api.languageId;
        const key = await channelService.generateKey();
        entity.accessKey = key.accessKey;
        channel.value = entity;
        await ensureAssociation('language', entity.languageId, entity.languages);
    } catch {
        createNotificationError({ message: t('ct-channel.detail.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const validateRequiredFields = (): boolean => {
    const entity = channel.value;
    if (!entity) return false;
    const required = [
        entity.name,
        entity.typeId,
        entity.languageId,
        entity.countryId,
        entity.memberGroupId,
        entity.navigationCategoryId,
        entity.accessKey,
    ];
    const valid = required.every((value) => typeof value === 'string' && value.trim() !== '');
    if (!valid) createNotificationError({ message: t('ct-channel.detail.requiredFields') });
    return valid;
};
const onSave = async (): Promise<boolean> => {
    if (!channel.value || !allowSaving.value || !validateRequiredFields()) return false;
    isLoading.value = true;
    try {
        const id = channel.value.id;
        await channelRepository.value.save(channel.value, Contena.Context.api);
        Contena.Utils.EventBus.emit('ct-channel-detail-channel-change');
        createNotificationSuccess({ message: t('ct-channel.detail.saveSuccess') });
        if (props.createMode) {
            await router.replace({ name: 'ct.channel.detail.base', params: { id } });
            return true;
        }
        await loadChannel();
        return true;
    } catch {
        createNotificationError({ message: t('ct-channel.detail.saveError') });
        return false;
    } finally {
        isLoading.value = false;
    }
};
const openTab = (name: string): void => {
    if (channel.value) void router.push({ name, params: { id: channel.value.id } });
};
const abortOnLanguageChange = (): boolean => {
    return channel.value ? channelRepository.value.hasChanges(channel.value) : false;
};
const saveOnLanguageChange = async (): Promise<true> => {
    if (!(await onSave())) {
        return Promise.reject(new Error('The Channel could not be saved.'));
    }

    return true;
};
const onChangeLanguage = (): void => {
    void loadChannel();
};

watch(
    () => [
        route.params.id,
        route.params.typeId,
        props.createMode,
    ],
    () => {
        void (props.createMode ? createChannel() : loadChannel());
    },
    { immediate: true },
);

swDefinePublic({
    channel,
    isLoading,
    pageTitle,
    tabs,
    allowSaving,
    saveTooltip,
    channelRepository,
    loadChannel,
    createChannel,
    validateRequiredFields,
    onSave,
    openTab,
    abortOnLanguageChange,
    saveOnLanguageChange,
    onChangeLanguage,
});

defineExpose({
    channel,
    isLoading,
    pageTitle,
    loadChannel,
    createChannel,
    validateRequiredFields,
    onSave,
    openTab,
    abortOnLanguageChange,
    saveOnLanguageChange,
    onChangeLanguage,
});
</script>
