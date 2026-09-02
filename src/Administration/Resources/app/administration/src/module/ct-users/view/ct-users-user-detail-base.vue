<template>
    <ct-block name="ct_users_user_detail_base">
        <ct-block name="ct_users_user_detail_card_basic_information">
            <mt-card
                position-identifier="ct-users-user-detail"
                :title="translate('ct-users.user-detail.labelCard')"
                :is-loading="isLoading"
            >
                <ct-block name="ct_users_user_detail_content_grid">
                    <div v-if="user" class="ct-users-user-detail__grid ct-users-user-detail__information-grid">
                        <ct-block name="ct_users_user_detail_content_name">
                            <mt-text-field
                                v-model="user.name"
                                name="ct-field--user-name"
                                class="ct-users-user-detail__grid-name"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                :error="userNameError"
                                required
                                :label="translate('ct-users.user-detail.labelName')"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_content_user_code">
                            <template v-if="user && user.userCode">
                                <mt-text-field
                                    :model-value="user.userCode"
                                    name="ct-field--user-userCode"
                                    class="ct-users-user-detail__grid-user-code"
                                    disabled
                                    :label="translate('ct-users.user-detail.labelUserCode')"
                                />
                            </template>
                        </ct-block>

                        <ct-block name="ct_users_user_detail_content_phone_number">
                            <mt-text-field
                                v-model="user.phoneNumber"
                                name="ct-field--user-phoneNumber"
                                class="ct-users-user-detail__grid-phoneNumber"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                :error="userPhoneNumberError"
                                :label="translate('ct-users.user-detail.labelPhoneNumber')"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_content_gender">
                            <ct-data-dictionary-select
                                v-model="user.gender"
                                technical-name="core.gender"
                                name="ct-field--user-gender"
                                class="ct-users-user-detail__grid-gender"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                :label="translate('ct-users.user-detail.labelGender')"
                                :placeholder="translate('ct-users.user-detail.labelGenderPlaceholder')"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_content_email">
                            <mt-text-field
                                v-model="user.email"
                                name="ct-field--user-email"
                                class="ct-users-user-detail__grid-eMail"
                                :error="userEmailError"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                required
                                :label="translate('ct-users.user-detail.labelEmail')"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_content_username">
                            <mt-text-field
                                v-model="user.username"
                                name="ct-field--user-username"
                                class="ct-users-user-detail__grid-username"
                                :error-message="isUsernameUsed ? translate('ct-users.user-detail.errorUsernameUsed') : ''"
                                :error="userUsernameError"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                required
                                :label="translate('ct-users.user-detail.labelUsername')"
                                @update:model-value="checkUsername"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_content_password">
                            <mt-password-field
                                class="ct-users-user-detail__grid-password"
                                :model-value="user.password"
                                name="ct-field--user-password"
                                :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                :label="translate('ct-users.user-detail.labelPassword')"
                                :error="userPasswordError"
                                autocomplete="new-password"
                                @update:model-value="setPassword"
                            />
                        </ct-block>

                        <ct-block name="ct_users_user_detail_grid_content_active">
                            <mt-switch
                                v-model="user.active"
                                name="ct-field--user-active"
                                class="ct-users-user-detail__grid-active"
                                :label="translate('ct-users.filter.active')"
                                :disabled="isCurrentUser || !acl.can('users_and_permissions.editor') || undefined"
                            />
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="ct_users_user_detail_content_tags">
                    <ct-entity-tag-select
                        v-if="user"
                        v-model:entity-collection="user.tags"
                        name="ct-field--user-tags"
                        class="ct-users-user-detail__tags"
                        :label="translate('ct-users.user-detail.labelTags')"
                        :placeholder="translate('ct-users.user-detail.placeholderTags')"
                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                    />
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
    userNameError,
    userPhoneNumberError,
    userEmailError,
    userUsernameError,
    userPasswordError,
    isUsernameUsed,
    checkUsername,
    isCurrentUser,
    setPassword,
} = inject('ctUsersUserDetailContext');

ctDefinePublic({});
</script>
