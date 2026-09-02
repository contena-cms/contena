<template>
    <ct-block name="ct_profile_index_general">
        <div class="ct-profile-index-general">
            <ct-block name="ct_profile_index_general_information">
                <mt-card
                    position-identifier="ct-profile-index-general"
                    :title="$t('ct-profile.index.titleInfoCard')"
                    :is-loading="isUserLoading || !languageId"
                >
                    <ct-container v-bind="{ columns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '0 30px' }">
                        <ct-block name="ct_profile_index_general_information_name">
                            <mt-text-field
                                v-model="user.name"
                                name="ct-field--user-name"
                                :label="$t('ct-profile.index.labelNameField')"
                                :disabled="isDisabled || !acl.can('user.update_profile')"
                            />
                        </ct-block>

                        <ct-block name="ct_profile_index_general_information_phone_number">
                            <mt-text-field
                                v-model="user.phoneNumber"
                                name="ct-field--user-phoneNumber"
                                :label="$t('ct-profile.index.labelPhoneNumberField')"
                                :disabled="isDisabled || !acl.can('user.update_profile')"
                            />
                        </ct-block>
                    </ct-container>

                    <ct-container v-bind="{ columns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '0 30px' }">
                        <ct-block name="ct_profile_index_general_information_username">
                            <mt-text-field
                                v-model="user.username"
                                name="ct-field--user-username"
                                :label="$t('ct-profile.index.labelUsernameField')"
                                :disabled="isDisabled || !acl.can('user.update_profile')"
                            />
                        </ct-block>

                        <ct-block name="ct_profile_index_general_information_language">
                            <mt-select
                                v-model="user.localeId"
                                name="ct-field--user-localeId"
                                :label="$t('ct-users.user-detail.labelLanguage')"
                                :disabled="!acl.can('user.update_profile')"
                                :placeholder="$t('ct-users.user-detail.labelLanguagePlaceholder')"
                                :options="localeOptions"
                            />
                        </ct-block>
                    </ct-container>

                    <ct-container v-bind="{ columns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '0 30px' }">
                        <!-- eslint-disable ct-deprecation-rules/no-twigjs-blocks,vue/no-duplicate-attributes,vue/no-parsing-error -->
                        <ct-block name="ct_profile_index_general_information_email">
                            <mt-text-field
                                v-model="user.email"
                                name="ct-field--user-email"
                                validation="email"
                                required
                                :label="$t('ct-profile.index.labelEmailField')"
                                :disabled="!acl.can('user.update_profile')"
                            />
                        </ct-block>

                        <ct-block name="ct_profile_index_general_information_timezone">
                            <mt-select
                                v-model="user.timeZone"
                                name="ct-field--user-timeZone"
                                class="ct-profile--timezone"
                                :options="timezoneOptions"
                                required
                                :label="$t('ct-users.user-detail.labelTimezone')"
                                :is-loading="timezoneOptions.length <= 0"
                                :disabled="!acl.can('user.update_profile')"
                            />
                        </ct-block>
                    </ct-container>
                </mt-card>
            </ct-block>

            <ct-block name="ct_profile_index_general_image">
                <mt-card
                    v-if="acl.can('media.creator')"
                    position-identifier="ct-profile-index-general-image"
                    :title="$t('ct-profile.index.titleImageCard')"
                    :is-loading="isUserLoading || !languageId"
                >
                    <ct-block name="ct_profile_index_general_image_content">
                        <ct-upload-listener
                            auto-upload
                            upload-tag="ct-profile-upload-tag"
                            @media-upload-finish="onUploadMedia"
                        />
                        <ct-media-upload-v2
                            upload-tag="ct-profile-upload-tag"
                            :source="avatarMediaItem"
                            :source-context="user"
                            :default-folder="userRepository.schema.entity"
                            :label="$t('ct-profile.index.labelUploadAvatar')"
                            :disabled="!acl.can('user.update_profile')"
                            :allow-multi-select="false"
                            @media-drop="onDropMedia"
                            @media-upload-sidebar-open="onOpenMedia"
                            @media-upload-remove-image="onRemoveMedia"
                        />
                    </ct-block>
                </mt-card>
            </ct-block>

            <ct-block name="ct_profile_index_general_password">
                <mt-card
                    position-identifier="ct-profile-index-general-password"
                    :title="$t('ct-profile.index.titlePasswordCard')"
                    :is-loading="isUserLoading || !languageId"
                >
                    <ct-block name="ct_profile_index_general_password_new_password">
                        <mt-password-field
                            v-model="computedNewPassword"
                            name="ct-field--computedNewPassword"
                            :label="$t('ct-profile.index.labelNewPassword')"
                            :disabled="!acl.can('user.update_profile')"
                            :placeholder="$t('ct-profile.index.placeholderNewPassword')"
                            :error="userPasswordError"
                            autocomplete="new-password"
                        />
                    </ct-block>

                    <ct-block name="ct_profile_index_general_password_new_password_confirm">
                        <mt-password-field
                            v-model="computedNewPasswordConfirm"
                            name="ct-field--computedNewPasswordConfirm"
                            :label="$t('ct-profile.index.labelNewPasswordConfirm')"
                            :disabled="!acl.can('user.update_profile')"
                            :placeholder="$t('ct-profile.index.placeholderNewPasswordConfirm')"
                            :validation="computedNewPassword === computedNewPasswordConfirm"
                            autocomplete="new-password"
                        />
                    </ct-block>
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    languages: {
        type: Array,
        required: true,
    },
    newPassword: {
        type: String,
        required: false,
        default: null,
    },
    newPasswordConfirm: {
        type: String,
        required: false,
        default: null,
    },
    avatarMediaItem: {
        type: Object,
        required: false,
        default: null,
    },
    isUserLoading: {
        type: Boolean,
        required: true,
    },
    languageId: {
        type: String,
        required: false,
        default: null,
    },
    isDisabled: {
        type: Boolean,
        required: true,
    },
    userRepository: {
        type: Object,
        required: true,
    },
    timezoneOptions: {
        type: Array,
        required: true,
    },
});
const emit = defineEmits([
    'new-password-change',
    'new-password-confirm-change',
    'media-upload',
    'media-remove',
    'media-open',
]);

import { computed, inject } from 'vue';

const user = computed(() => props.user);

const acl = inject('acl');

const userPasswordError = computed(() => {
    const entity = props.user;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'password');
});
const computedNewPassword = computed({
    get: () => {
        return props.newPassword;
    },
    set: (newPassword) => {
        emit('new-password-change', newPassword);
    },
});
const computedNewPasswordConfirm = computed({
    get: () => {
        return props.newPasswordConfirm;
    },
    set: (newPasswordConfirm) => {
        emit('new-password-confirm-change', newPasswordConfirm);
    },
});
const localeOptions = computed(() => {
    return props.languages.map((language) => {
        return {
            id: language.locale.id,
            value: language.locale.id,
            label: language.customLabel,
        };
    });
});

const onUploadMedia = (media) => {
    emit('media-upload', { targetId: media.targetId });
};
const onDropMedia = (media) => {
    emit('media-upload', { targetId: media.id });
};
const onRemoveMedia = () => {
    emit('media-remove');
};
const onOpenMedia = () => {
    emit('media-open');
};

ctDefinePublic({
    acl,
    userPasswordError,
    computedNewPassword,
    computedNewPasswordConfirm,
    localeOptions,
    onUploadMedia,
    onDropMedia,
    onRemoveMedia,
    onOpenMedia,
});

defineExpose({
    acl,
    userPasswordError,
    computedNewPassword,
    computedNewPasswordConfirm,
    localeOptions,
    onUploadMedia,
    onDropMedia,
    onRemoveMedia,
    onOpenMedia,
});
</script>
