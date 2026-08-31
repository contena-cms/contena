<template>
    <ct-block name="ct_users">
        <ct-page class="ct-users" :show-smart-bar="false">
            <template #search-bar>
                <ct-block name="ct_users_search_bar">
                    <ct-search-bar initial-search-type="user" :ignore-route-term="true" @search="onUserSearch" />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_users_content">
                    <ct-users-user-listing
                        ref="userListing"
                        @loading-change="onUserLoadingChange"
                        @total-change="onUserTotalChange"
                        @edit="onEditUser"
                        @create="onCreateUser"
                    />
                </ct-block>
            </template>
        </ct-page>
    </ct-block>

    <ct-block name="ct_users_user_form_modal">
        <mt-modal-root v-if="userFormMode" :is-open="true" @change="onCloseUserForm">
            <ct-block name="ct_users_user_form_modal_content">
                <mt-modal :title="userFormTitle" width="l">
                    <ct-users-user-detail v-if="userFormMode === 'edit'" ref="userForm" :initial-user-id="userFormId" />
                    <ct-users-user-create v-else ref="userForm" />

                    <template #footer>
                        <ct-block name="ct_users_user_form_modal_footer">
                            <mt-button variant="secondary" @click="onCloseUserForm">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                            <mt-button variant="primary" :is-loading="isUserFormSaving" @click="onSaveUserForm">
                                {{ $t('global.default.save') }}
                            </mt-button>
                        </ct-block>
                    </template>
                </mt-modal>
            </ct-block>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

defineProps({});

import { computed, inject, ref } from 'vue';
import type { ComponentExposed } from 'vue-component-type-helpers';
import type AclService from 'src/app/service/acl.service';
import { useI18n } from 'vue-i18n';
import SwUsersUserListing from '../../component/ct-users-user-listing/ct-users-user-listing.vue';

const { t } = useI18n();
const userListing = ref<ComponentExposed<typeof SwUsersUserListing>>();
const statusFilter = ref('all');

const acl = inject<AclService>('acl');
if (!acl) {
    throw new Error('The ACL service is unavailable.');
}
const userTotal = ref(0);
const userListingLoading = ref(true);
const userFormMode = ref<'create' | 'edit' | null>(null);
const userFormId = ref('');
const userForm = ref<{
    onSave?: () => Promise<unknown>;
    isSaveSuccessful?: boolean;
    isLoading?: boolean;
    user?: { name?: string; username?: string };
}>();
const userFormTitle = computed(() =>
    userFormMode.value === 'create'
        ? t('ct-users.user-detail.labelNewUser')
        : userForm.value?.user?.name || userForm.value?.user?.username || t('ct-users.user-detail.labelCard'),
);
const isUserFormSaving = computed(() => Boolean(userForm.value?.isLoading));

const reloadUserListing = () => userListing.value?.getList();
const onUserSearch = (term: string) => {
    userListing.value?.onSearch(term);
};
const onStatusFilterChange = (value: string) => {
    if (statusFilter.value === value) {
        return;
    }

    statusFilter.value = value;
    void userListing.value?.setStatusFilter(value);
};
const resetUserFilters = () => {
    statusFilter.value = 'all';
    userListing.value?.resetFilters();
};
const onUserTotalChange = (total: number) => {
    userTotal.value = total;
};
const onUserLoadingChange = (loading: boolean) => {
    userListingLoading.value = loading;
};
const onCreateUser = () => {
    userFormId.value = '';
    userFormMode.value = 'create';
};
const onEditUser = (user: { id: string }) => {
    userFormId.value = user.id;
    userFormMode.value = 'edit';
};
const onCloseUserForm = () => {
    userFormMode.value = null;
    userFormId.value = '';
    userForm.value = undefined;
};
const onSaveUserForm = async () => {
    if (!userForm.value?.onSave) {
        return;
    }

    await userForm.value.onSave();
    if (userForm.value.isSaveSuccessful) {
        onCloseUserForm();
        void userListing.value?.getList();
    }
};

swDefinePublic({
    acl,
    userTotal,
    userListingLoading,
    reloadUserListing,
    onUserSearch,
    onStatusFilterChange,
    resetUserFilters,
    onUserTotalChange,
    onUserLoadingChange,
    userFormMode,
    userFormId,
    userFormTitle,
    isUserFormSaving,
    onCreateUser,
    onEditUser,
    onCloseUserForm,
    onSaveUserForm,
});

defineExpose({
    acl,
    statusFilter,
    userTotal,
    userListingLoading,
    reloadUserListing,
    onUserSearch,
    onStatusFilterChange,
    resetUserFilters,
    onUserTotalChange,
    onUserLoadingChange,
    userFormMode,
    userFormId,
    userFormTitle,
    isUserFormSaving,
    onCreateUser,
    onEditUser,
    onCloseUserForm,
    onSaveUserForm,
});
</script>
