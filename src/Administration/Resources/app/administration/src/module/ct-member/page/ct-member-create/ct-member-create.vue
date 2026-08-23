<template>
    <ct-block name="sw_member_create">
        <ct-page class="ct-member-create">
            <template #smart-bar-header>
                <ct-block name="sw_member_create_header">
                    <h2>{{ t('ct-member.detail.textHeadline') }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_member_create_actions">
                    <mt-button variant="secondary" :disabled="isLoading || undefined" @click="onCancel">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <ct-button-process
                        variant="primary"
                        :is-loading="isLoading"
                        :process-success="isSaveSuccessful"
                        :disabled="!canCreate || isLoading || undefined"
                        @update:process-success="saveFinish"
                        @click="onSave"
                    >
                        {{ t('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_member_create_content">
                    <ct-card-view v-if="member && address">
                        <mt-card
                            position-identifier="ct-member-create-account"
                            :title="t('ct-member.detailBase.labelAccountCard')"
                            :is-loading="isLoading"
                        >
                            <ct-member-base-form
                                :member="member"
                                create-mode
                                :disabled="!canCreate"
                                @channel-change="onChannelChange"
                            />
                        </mt-card>
                        <mt-card
                            position-identifier="ct-member-create-address"
                            :title="t('ct-member.detailAddresses.title')"
                            :is-loading="isLoading"
                        >
                            <ct-member-address-form :member="member" :address="address" :disabled="!canCreate" />
                        </mt-card>
                    </ct-card-view>
                    <mt-loader v-else />
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, Repository */

import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

import { useNotification } from 'src/app/composables/use-notification';

interface NumberRangeService {
    reserve: (typeName: string, preview?: boolean) => Promise<{ number: string }>;
}

defineProps({});
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const numberRangeService = inject<NumberRangeService>('numberRangeService');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !numberRangeService || !acl) {
    throw new Error('The Member create services are unavailable.');
}
const memberRepository: Repository<'member'> = repositoryFactory.create('member');
const memberAddressRepository: Repository<'member_address'> = repositoryFactory.create('member_address');
const channelRepository: Repository<'channel'> = repositoryFactory.create('channel');
const member = ref<Entity<'member'> | null>(null);
const address = ref<Entity<'member_address'> | null>(null);
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const memberNumberPreview = ref('');
const canCreate = computed(() => acl.can('member.creator'));

const onChannelChange = async (channelId: string): Promise<void> => {
    if (!member.value || !channelId) return;
    const criteria = new Contena.Data.Criteria(1, 1);
    criteria.addAssociation('memberGroup');
    const channel = await channelRepository.get(channelId, Contena.Context.api, criteria);
    member.value.channelId = channelId;
    member.value.groupId = channel.memberGroupId;
    member.value.languageId = channel.languageId;
};
const createState = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const entity = memberRepository.create(Contena.Context.api);
        const memberAddress = memberAddressRepository.create(Contena.Context.api);
        entity.active = true;
        entity.password = '';
        entity.tagIds = [];
        memberAddress.memberId = entity.id;
        entity.addresses?.add(memberAddress);
        member.value = entity;
        address.value = memberAddress;

        const channelCriteria = new Contena.Data.Criteria(1, 1);
        channelCriteria.addFilter(Contena.Data.Criteria.equals('active', true));
        channelCriteria.addSorting(Contena.Data.Criteria.sort('createdAt', 'ASC'));
        const channels = await channelRepository.search(channelCriteria, Contena.Context.api);
        const defaultChannel = channels.first();
        if (defaultChannel) await onChannelChange(defaultChannel.id);

        const preview = await numberRangeService.reserve('member', true);
        memberNumberPreview.value = preview.number;
        entity.memberNumber = preview.number;
    } catch {
        createNotificationError({ message: t('ct-member.detail.messageSaveError') });
    } finally {
        isLoading.value = false;
    }
};
const validateRequiredFields = (): boolean => {
    if (!member.value || !address.value) return false;
    const memberFields = [
        member.value.name,
        member.value.email,
        member.value.groupId,
        member.value.channelId,
        member.value.languageId,
        member.value.memberNumber,
        member.value.password,
    ];
    const addressFields = [
        address.value.firstName,
        address.value.lastName,
        address.value.countryId,
        address.value.street,
        address.value.city,
    ];
    const valid = [
        ...memberFields,
        ...addressFields,
    ].every((value) => typeof value === 'string' && value.trim() !== '');
    if (!valid) createNotificationError({ message: t('ct-member.detail.messageSaveError') });
    return valid;
};
const onSave = async (): Promise<void> => {
    if (!member.value || !canCreate.value || !validateRequiredFields()) return;
    isLoading.value = true;
    isSaveSuccessful.value = false;
    try {
        if (member.value.memberNumber === memberNumberPreview.value) {
            const reserved = await numberRangeService.reserve('member');
            member.value.memberNumber = reserved.number;
        }
        await memberRepository.save(member.value, Contena.Context.api);
        isSaveSuccessful.value = true;
    } catch {
        createNotificationError({ message: t('ct-member.detail.messageSaveError') });
    } finally {
        isLoading.value = false;
    }
};
const onCancel = (): void => void router.push({ name: 'ct.member.index' });
const saveFinish = (): void => {
    isSaveSuccessful.value = false;
    if (member.value) void router.push({ name: 'ct.member.detail.base', params: { id: member.value.id } });
};

void createState();

swDefinePublic({
    member,
    address,
    isLoading,
    isSaveSuccessful,
    canCreate,
    memberRepository,
    memberNumberPreview,
    createState,
    onChannelChange,
    validateRequiredFields,
    onSave,
    onCancel,
    saveFinish,
});

defineExpose({
    member,
    address,
    isLoading,
    isSaveSuccessful,
    canCreate,
    memberRepository,
    memberNumberPreview,
    createState,
    onChannelChange,
    validateRequiredFields,
    onSave,
    onCancel,
    saveFinish,
});
</script>
