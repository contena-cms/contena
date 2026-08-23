<template>
    <!-- DAL entities are intentionally edited in place before the repository save. -->
    <!-- eslint-disable vue/no-mutating-props -->
    <ct-block name="sw_settings_member_group_detail">
        <ct-page class="ct-settings-member-group-detail">
            <template #smart-bar-header>
                <ct-block name="sw_settings_member_group_detail_header">
                    <h2>{{ pageTitle }}</h2>
                </ct-block>
            </template>
            <template #smart-bar-actions>
                <ct-block name="sw_settings_member_group_detail_actions">
                    <mt-button variant="secondary" @click="onCancel">{{ t('global.default.cancel') }}</mt-button>
                    <ct-button-process
                        variant="primary"
                        :is-loading="isLoading"
                        :process-success="isSaveSuccessful"
                        :disabled="!allowSave || undefined"
                        @update:process-success="saveFinish"
                        @click="onSave"
                    >
                        {{ t('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>
            <template #language-switch>
                <ct-language-switch :disabled="createMode || undefined" @on-change="onChangeLanguage" />
            </template>
            <template #content>
                <ct-block name="sw_settings_member_group_detail_content">
                    <ct-card-view v-if="memberGroup">
                        <mt-card
                            position-identifier="ct-settings-member-group-detail-general"
                            :title="t('ct-settings-member-group.general.mainMenuItemGeneral')"
                            :is-loading="isLoading"
                        >
                            <mt-text-field
                                v-model="memberGroup.name"
                                required
                                :label="t('ct-settings-member-group.detail.labelName')"
                                :placeholder="t('ct-settings-member-group.detail.placeholderName')"
                                :error="getApiError('name')"
                                :disabled="!allowSave || undefined"
                            />
                        </mt-card>

                        <ct-block name="sw_settings_member_group_detail_registration">
                            <mt-card
                                position-identifier="ct-settings-member-group-detail-registration"
                                :title="t('ct-settings-member-group.detail.titleRegistration')"
                                :is-loading="isLoading"
                            >
                                <div class="ct-settings-member-group-detail__grid">
                                    <mt-switch
                                        v-model="memberGroup.registrationActive"
                                        bordered
                                        :label="t('ct-settings-member-group.detail.labelRegistrationActive')"
                                        :disabled="!allowSave || undefined"
                                    />
                                    <mt-text-field
                                        v-model="memberGroup.registrationTitle"
                                        :required="memberGroup.registrationActive"
                                        :label="t('ct-settings-member-group.detail.labelRegistrationTitle')"
                                        :disabled="!allowSave || undefined"
                                    />
                                    <mt-text-editor
                                        v-model="memberGroup.registrationIntroduction"
                                        :label="t('ct-settings-member-group.detail.labelRegistrationIntroduction')"
                                        :disabled="!allowSave || undefined"
                                        sanitize-input
                                        sanitize-field-name="member_group_translation.registrationIntroduction"
                                    />
                                    <mt-textarea
                                        v-model="memberGroup.registrationSeoMetaDescription"
                                        :label="t('ct-settings-member-group.detail.labelRegistrationSeoMetaDescription')"
                                        :disabled="!allowSave || undefined"
                                    />
                                    <mt-entity-select
                                        :model-value="registrationChannelIds"
                                        entity="channel"
                                        enable-multi-selection
                                        :label="t('ct-settings-member-group.detail.labelChannels')"
                                        :disabled="!allowSave || undefined"
                                        @item-add="onChannelAdd"
                                        @item-remove="onChannelRemove"
                                        @update:model-value="onChannelsUpdate"
                                    />
                                </div>
                            </mt-card>
                        </ct-block>

                        <ct-block name="sw_settings_member_group_detail_custom_fields">
                            <mt-card
                                v-if="customFieldSets.length > 0"
                                position-identifier="ct-settings-member-group-detail-custom-fields"
                                :title="t('ct-settings-custom-field.general.mainMenuItemGeneral')"
                            >
                                <ct-custom-field-set-renderer
                                    :entity="memberGroup"
                                    :disabled="!allowSave"
                                    :sets="customFieldSets"
                                />
                            </mt-card>
                        </ct-block>
                    </ct-card-view>
                    <mt-loader v-else />
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection, Repository */
/* global Entity, EntityCollection, Repository */
import type { PropType } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-settings-member-group-detail.scss';

const props = defineProps({
    memberGroupId: { type: String as PropType<string | null>, default: null },
    createMode: { type: Boolean, default: false },
});
const router = useRouter();
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) throw new Error('The MemberGroup detail services are unavailable.');
const repository: Repository<'member_group'> = repositoryFactory.create('member_group');
const channelRepository: Repository<'channel'> = repositoryFactory.create('channel');
const memberGroup = ref<Entity<'member_group'> | null>(null);
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const customFieldSets = ref<Entity<'custom_field_set'>[]>([]);
const pageTitle = computed(() =>
    props.createMode
        ? t('ct-settings-member-group.detail.titleNew')
        : memberGroup.value?.translated?.name || memberGroup.value?.name || t('ct-settings-member-group.detail.titleEdit'),
);
const allowSave = computed(() => acl.can(props.createMode ? 'member_groups.creator' : 'member_groups.editor'));
const registrationChannelIds = computed(() => memberGroup.value?.registrationChannels?.getIds() ?? []);
const getApiError = (property: string): unknown =>
    memberGroup.value ? Contena.Store.get('error').getApiError(memberGroup.value, property) : null;
const loadCustomFieldSets = async (): Promise<void> => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.addFilter(Contena.Data.Criteria.equals('relations.entityName', 'member_group'));
    const result = await repositoryFactory.create('custom_field_set').search(criteria, Contena.Context.api);
    customFieldSets.value = Array.from(result);
};
const loadMemberGroup = async (): Promise<void> => {
    isLoading.value = true;
    try {
        if (props.createMode) {
            const entity = repository.create(Contena.Context.api);
            entity.registrationActive = false;
            memberGroup.value = entity;
        } else if (props.memberGroupId) {
            const criteria = new Contena.Data.Criteria(1, 1);
            criteria.addAssociation('registrationChannels');
            memberGroup.value = await repository.get(props.memberGroupId, Contena.Context.api, criteria);
        }
        await loadCustomFieldSets();
    } catch {
        createNotificationError({ message: t('ct-settings-member-group.detail.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const ensureCollection = (): EntityCollection<'channel'> | null => memberGroup.value?.registrationChannels ?? null;
const onChannelAdd = (channel: Entity<'channel'>): void => {
    const collection = ensureCollection();
    if (collection && !collection.has(channel.id)) collection.add(channel);
};
const onChannelRemove = (channel: Entity<'channel'>): void => ensureCollection()?.remove(channel.id);
const onChannelsUpdate = async (ids: string[]): Promise<void> => {
    const collection = ensureCollection();
    if (!collection) return;
    collection
        .getIds()
        .filter((id) => !ids.includes(id))
        .forEach((id) => collection.remove(id));
    for (const id of ids) {
        if (!collection.has(id)) collection.add(await channelRepository.get(id, Contena.Context.api));
    }
};
const onSave = async (): Promise<void> => {
    if (!memberGroup.value || !allowSave.value || !memberGroup.value.name?.trim()) return;
    isLoading.value = true;
    try {
        const id = memberGroup.value.id;
        await repository.save(memberGroup.value, Contena.Context.api);
        isSaveSuccessful.value = true;
        createNotificationSuccess({ message: t('ct-settings-member-group.detail.saveSuccess') });
        if (props.createMode) await router.replace({ name: 'ct.settings.member.group.detail', params: { id } });
        else await loadMemberGroup();
    } catch {
        createNotificationError({ message: t('ct-settings-member-group.detail.saveError') });
    } finally {
        isLoading.value = false;
    }
};
const onCancel = (): void => void router.push({ name: 'ct.settings.member.group.index' });
const saveFinish = (): void => {
    isSaveSuccessful.value = false;
};
const onChangeLanguage = (languageId: string): void => {
    Contena.Store.get('context').setApiLanguageId(languageId);
    void loadMemberGroup();
};
watch(
    [
        () => props.memberGroupId,
        () => props.createMode,
    ],
    () => void loadMemberGroup(),
    { immediate: true },
);

swDefinePublic({
    memberGroup,
    isLoading,
    isSaveSuccessful,
    customFieldSets,
    pageTitle,
    allowSave,
    registrationChannelIds,
    repository,
    loadMemberGroup,
    loadCustomFieldSets,
    getApiError,
    onChannelAdd,
    onChannelRemove,
    onChannelsUpdate,
    onSave,
    onCancel,
    saveFinish,
    onChangeLanguage,
});

defineExpose({
    memberGroup,
    isLoading,
    isSaveSuccessful,
    customFieldSets,
    pageTitle,
    allowSave,
    registrationChannelIds,
    repository,
    loadMemberGroup,
    loadCustomFieldSets,
    getApiError,
    onChannelAdd,
    onChannelRemove,
    onChannelsUpdate,
    onSave,
    onCancel,
    saveFinish,
    onChangeLanguage,
});
</script>
