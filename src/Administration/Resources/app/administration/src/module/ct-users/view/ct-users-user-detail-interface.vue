<template>
    <ct-block name="ct_users_user_detail_interface">
        <ct-block name="ct_users_user_detail_card_user_interface">
            <mt-card
                position-identifier="ct-users-user-detail-user-interface"
                :title="translate('ct-users.user-detail.labelUserInterface')"
                :is-loading="isLoading"
            >
                <ct-block name="ct_users_user_detail_user_interface_grid">
                    <div v-if="user" class="ct-users-user-detail__grid ct-users-user-detail__user-interface-grid">
                        <ct-block name="ct_users_user_detail_grid_content_language">
                            <mt-select
                                v-model="user.localeId"
                                name="ct-field--user-localeId"
                                class="ct-users-user-detail__grid-language"
                                :label="translate('ct-users.user-detail.labelLanguage')"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                :error="userLocaleIdError"
                                :options="localeOptions"
                                required
                                :placeholder="translate('ct-users.user-detail.labelLanguagePlaceholder')"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_grid_content_timezone">
                            <mt-select
                                v-model="user.timeZone"
                                name="ct-field--user-timeZone"
                                class="ct-users-user-detail__grid-timezone"
                                :options="timezoneOptions"
                                required
                                :label="translate('ct-users.user-detail.labelTimezone')"
                                :disabled="!acl.can('user.update_profile') || undefined"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_content_media_upload">
                            <ct-upload-listener :upload-tag="user.id" auto-upload @media-upload-finish="setMediaItem" />
                            <ct-media-upload-v2
                                class="ct-users-user-detail__grid-profile-picture"
                                :source="avatarMedia"
                                :label="translate('ct-users.user-detail.labelProfilePicture')"
                                :upload-tag="user.id"
                                :allow-multi-select="false"
                                :source-context="user"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                default-folder="user"
                                @media-drop="onDropMedia"
                                @media-upload-sidebar-open="onOpenMedia"
                                @media-upload-remove-image="onUnlinkLogo"
                            />
                        </ct-block>
                    </div>
                </ct-block>
            </mt-card>
        </ct-block>
    </ct-block>
</template>

<script setup>
import { inject } from 'vue';

const {
    user,
    isLoading,
    acl,
    translate,
    userLocaleIdError,
    localeOptions,
    timezoneOptions,
    avatarMedia,
    setMediaItem,
    onDropMedia,
    onOpenMedia,
    onUnlinkLogo,
} = inject('ctUsersUserDetailContext');

ctDefinePublic({});
</script>
