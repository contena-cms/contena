<template>
    <!-- DAL entities are intentionally edited in place before the parent repository save. -->
    <!-- eslint-disable vue/no-mutating-props -->
    <ct-block name="ct_member_base_form">
        <div class="ct-member-base-form">
            <ct-block name="ct_member_base_form_identity">
                <div class="ct-member-base-form__grid">
                    <mt-text-field
                        v-model="member.title"
                        :label="t('ct-member.baseForm.labelTitle')"
                        :placeholder="t('ct-member.baseForm.placeholderTitle')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="member.name"
                        required
                        :label="t('ct-member.baseForm.labelName')"
                        :placeholder="t('ct-member.baseForm.placeholderName')"
                        :error="getApiError('name')"
                        :disabled="disabled || undefined"
                    />
                    <mt-email-field
                        v-model="member.email"
                        required
                        :label="t('ct-member.baseForm.labelEmail')"
                        :placeholder="t('ct-member.baseForm.placeholderEmail')"
                        :error="getApiError('email')"
                        :disabled="disabled || undefined"
                    />
                    <mt-datepicker
                        v-model="member.birthday"
                        date-type="date"
                        :label="t('ct-member.baseForm.labelBirthday')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="member.phoneNumber"
                        :label="t('ct-member.baseForm.labelPhoneNumber')"
                        :placeholder="t('ct-member.baseForm.placeholderPhoneNumber')"
                        :disabled="disabled || undefined"
                    />
                </div>
            </ct-block>

            <ct-block name="ct_member_base_form_assignment">
                <div class="ct-member-base-form__grid">
                    <mt-entity-select
                        v-model="member.groupId"
                        entity="member_group"
                        required
                        :label="t('ct-member.baseForm.labelMemberGroup')"
                        :placeholder="t('ct-member.baseForm.placeholderMemberGroup')"
                        :error="getApiError('groupId')"
                        :disabled="disabled || undefined"
                    />
                    <mt-entity-select
                        v-model="member.channelId"
                        entity="channel"
                        required
                        :label="t('ct-member.baseForm.labelChannel')"
                        :placeholder="t('ct-member.baseForm.placeholderChannel')"
                        :error="getApiError('channelId')"
                        :disabled="disabled || undefined"
                        @update:model-value="onChannelChange"
                    />
                    <mt-entity-select
                        v-model="member.languageId"
                        entity="language"
                        required
                        :label="t('ct-member.baseForm.labelLanguage')"
                        :placeholder="t('ct-member.baseForm.placeholderLanguage')"
                        :error="getApiError('languageId')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="member.memberNumber"
                        required
                        :label="t('ct-member.baseForm.labelMemberNumber')"
                        :placeholder="t('ct-member.baseForm.placeholderMemberNumber')"
                        :error="getApiError('memberNumber')"
                        :disabled="disabled || undefined"
                    />
                    <mt-switch
                        v-model="member.active"
                        bordered
                        :label="t('ct-member.baseForm.labelActive')"
                        :disabled="disabled || undefined"
                    />
                </div>
            </ct-block>

            <ct-block name="ct_member_base_form_password">
                <div class="ct-member-base-form__grid">
                    <mt-password-field
                        v-if="createMode"
                        v-model="member.password"
                        autocomplete="new-password"
                        required
                        :label="t('ct-member.baseForm.labelPassword')"
                        :placeholder="t('ct-member.baseForm.placeholderPassword')"
                        :error="getApiError('password')"
                        :disabled="disabled || undefined"
                    />
                    <template v-else>
                        <mt-password-field
                            v-model="member.passwordNew"
                            autocomplete="new-password"
                            :label="t('ct-member.baseForm.labelNewPassword')"
                            :error="getApiError('passwordNew')"
                            :disabled="disabled || undefined"
                        />
                        <mt-password-field
                            v-model="member.passwordConfirm"
                            autocomplete="new-password"
                            :label="t('ct-member.baseForm.labelPasswordConfirmation')"
                            :error="getApiError('passwordConfirm')"
                            :disabled="disabled || undefined"
                        />
                    </template>
                </div>
            </ct-block>

            <ct-block name="ct_member_base_form_tags">
                <mt-entity-select
                    v-model="member.tagIds"
                    entity="tag"
                    enable-multi-selection
                    :label="t('ct-member.baseForm.labelTags')"
                    :placeholder="t('ct-member.baseForm.placeholderTags')"
                    :disabled="disabled || undefined"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import './ct-member-base-form.scss';

type Member = Entity<'member'> & { passwordNew?: string; passwordConfirm?: string };
const props = defineProps({
    member: { type: Object as PropType<Member>, required: true },
    disabled: { type: Boolean, default: false },
    createMode: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'channel-change': [channelId: string] }>();
const { t } = useI18n();

const getApiError = (property: string): unknown => Contena.Store.get('error').getApiError(props.member, property);
const onChannelChange = (channelId: string): void => emit('channel-change', channelId);

ctDefinePublic({
    getApiError,
    onChannelChange,
});

defineExpose({ getApiError, onChannelChange });
</script>
