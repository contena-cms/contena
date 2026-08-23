<template>
    <ct-block name="sw_member_detail">
        <ct-page class="ct-member-detail">
            <template #smart-bar-header>
                <ct-block name="sw_member_detail_header">
                    <h2>{{ fullName }}</h2>
                    <mt-badge v-if="member?.createdById" class="ct-member-detail__created-by-admin">
                        {{ t('ct-member.detail.labelCreatedByAdmin') }}
                    </mt-badge>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_member_detail_actions">
                    <template v-if="!editMode">
                        <mt-button
                            v-tooltip="editTooltip"
                            variant="primary"
                            :disabled="isLoading || !canEdit || undefined"
                            @click="editMode = true"
                        >
                            {{ t('global.default.edit') }}
                        </mt-button>
                    </template>
                    <template v-else>
                        <mt-button variant="secondary" :disabled="isLoading || undefined" @click="onCancel">
                            {{ t('global.default.cancel') }}
                        </mt-button>
                        <ct-button-process
                            variant="primary"
                            :is-loading="isLoading"
                            :process-success="isSaveSuccessful"
                            :disabled="isLoading || !canEdit || undefined"
                            @update:process-success="saveFinish"
                            @click="onSave"
                        >
                            {{ t('global.default.save') }}
                        </ct-button-process>
                    </template>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_member_detail_content">
                    <ct-card-view>
                        <mt-banner v-if="member?.requestedGroup" variant="info">
                            <div class="ct-member-detail__registration-request">
                                <div>
                                    {{
                                        t('ct-member.memberGroupRegistration.alert', {
                                            name: member.requestedGroup.translated?.name || member.requestedGroup.name,
                                        })
                                    }}
                                    <p v-if="!member.active">
                                        {{ t('ct-member.memberGroupRegistration.memberInactiveMessage') }}
                                    </p>
                                </div>
                                <div class="ct-member-detail__registration-actions">
                                    <mt-button variant="critical" @click="declineMemberGroupRegistration">
                                        {{ t('ct-member.memberGroupRegistration.decline') }}
                                    </mt-button>
                                    <mt-button variant="primary" @click="acceptMemberGroupRegistration">
                                        {{ t('ct-member.memberGroupRegistration.accept') }}
                                    </mt-button>
                                </div>
                            </div>
                        </mt-banner>

                        <ct-block name="sw_member_detail_tabs">
                            <mt-tabs
                                position-identifier="ct-member-detail-tabs"
                                :default-item="$route.name"
                                :items="detailTabs"
                                small
                            />
                        </ct-block>

                        <ct-block name="sw_member_detail_view">
                            <mt-loader v-if="isLoading && !member" />
                            <router-view v-else-if="member" v-slot="{ Component }">
                                <component
                                    :is="Component"
                                    :member="member"
                                    :member-edit-mode="editMode"
                                    :is-loading="isLoading"
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
/* global Entity, Repository */
/* global Entity, Repository */
import type { PropType } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import type MemberGroupRegistrationApiService from 'src/core/service/api/member-group-registration.api.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-member-detail.scss';

type Member = Entity<'member'> & { passwordNew?: string; passwordConfirm?: string };
type Tab = { label: string; name: string; onClick: () => void };
const props = defineProps({
    memberId: { type: String as PropType<string>, required: true },
});
const router = useRouter();
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const memberGroupRegistrationService = inject<MemberGroupRegistrationApiService>('memberGroupRegistrationService');
if (!repositoryFactory || !acl || !memberGroupRegistrationService) {
    throw new Error('The Member detail services are unavailable.');
}
const memberRepository: Repository<'member'> = repositoryFactory.create('member');
const member = ref<Member | null>(null);
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const editMode = ref(false);
const fullName = computed(() => member.value?.name ?? '');
const detailTabs = computed<Tab[]>(() => [
    {
        label: t('ct-member.detail.tabGeneral'),
        name: 'ct.member.detail.base',
        onClick: () => void router.push({ name: 'ct.member.detail.base', params: { id: props.memberId } }),
    },
    {
        label: t('ct-member.detail.tabAddresses'),
        name: 'ct.member.detail.addresses',
        onClick: () => void router.push({ name: 'ct.member.detail.addresses', params: { id: props.memberId } }),
    },
]);
const canEdit = computed(() => acl.can('member.editor'));
const editTooltip = computed(() => ({
    message: t('ct-privileges.tooltip.warning'),
    disabled: canEdit.value,
    showOnDisabledElements: true,
}));
const getCriteria = () => {
    const criteria = new Contena.Data.Criteria(1, 1);
    criteria.addAssociation('group');
    criteria.addAssociation('channel');
    criteria.addAssociation('language');
    criteria.addAssociation('requestedGroup');
    criteria.addAssociation('tags');
    criteria.addAssociation('addresses.country');
    criteria.addAssociation('addresses.region.parent.parent');
    criteria.getAssociation('addresses').addSorting(Contena.Data.Criteria.sort('createdAt', 'ASC'));
    return criteria;
};
const loadMember = async (): Promise<void> => {
    isLoading.value = true;
    try {
        member.value = (await memberRepository.get(props.memberId, Contena.Context.api, getCriteria())) as Member;
        if (!member.value) {
            createNotificationError({ message: t('ct-member.detail.messageMemberNotFound') });
            await router.push({ name: 'ct.member.index' });
        }
    } catch {
        createNotificationError({ message: t('global.notification.notificationLoadingDataErrorMessage') });
    } finally {
        isLoading.value = false;
    }
};
const validPassword = (): boolean => {
    if (!member.value) return false;
    const { passwordNew, passwordConfirm } = member.value;
    if (!passwordNew && !passwordConfirm) return true;
    if (passwordNew === passwordConfirm) return true;
    createNotificationError({ message: t('ct-member.detail.messagePasswordMismatch') });
    return false;
};
const onSave = async (): Promise<void> => {
    if (!member.value || !canEdit.value || !validPassword()) return;
    isLoading.value = true;
    isSaveSuccessful.value = false;
    try {
        if (member.value.passwordNew) member.value.password = member.value.passwordNew;
        if (!member.value.birthday) member.value.birthday = undefined;
        await memberRepository.save(member.value, Contena.Context.api);
        await loadMember();
        isSaveSuccessful.value = true;
        createNotificationSuccess({
            message: t('ct-member.detail.messageSaveSuccess', { name: fullName.value }),
        });
    } catch {
        createNotificationError({ message: t('ct-member.detail.messageSaveError') });
    } finally {
        isLoading.value = false;
    }
};
const onCancel = async (): Promise<void> => {
    editMode.value = false;
    await loadMember();
};
const saveFinish = (): void => {
    isSaveSuccessful.value = false;
    editMode.value = false;
};
const updateRegistration = async (action: 'accept' | 'decline'): Promise<void> => {
    try {
        await memberGroupRegistrationService[action](props.memberId);
        createNotificationSuccess({ message: t(`ct-member.memberGroupRegistration.${action}Message`) });
        await loadMember();
    } catch {
        createNotificationError({ message: t('ct-member.memberGroupRegistration.errorMessage') });
    }
};
const acceptMemberGroupRegistration = (): Promise<void> => updateRegistration('accept');
const declineMemberGroupRegistration = (): Promise<void> => updateRegistration('decline');

watch(
    () => props.memberId,
    () => void loadMember(),
    { immediate: true },
);

swDefinePublic({
    member,
    isLoading,
    isSaveSuccessful,
    editMode,
    fullName,
    detailTabs,
    canEdit,
    editTooltip,
    memberRepository,
    loadMember,
    validPassword,
    onSave,
    onCancel,
    saveFinish,
    acceptMemberGroupRegistration,
    declineMemberGroupRegistration,
});

defineExpose({
    member,
    isLoading,
    isSaveSuccessful,
    editMode,
    fullName,
    detailTabs,
    canEdit,
    editTooltip,
    memberRepository,
    loadMember,
    validPassword,
    onSave,
    onCancel,
    saveFinish,
    acceptMemberGroupRegistration,
    declineMemberGroupRegistration,
});
</script>
