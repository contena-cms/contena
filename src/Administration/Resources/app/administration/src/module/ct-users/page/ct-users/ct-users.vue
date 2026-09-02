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

import { inject, ref } from 'vue';
import type { ComponentExposed } from 'vue-component-type-helpers';
import type AclService from 'src/app/service/acl.service';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import CtUsersUserListing from '../../component/ct-users-user-listing/ct-users-user-listing.vue';

const { t } = useI18n();
const router = useRouter();
const userListing = ref<ComponentExposed<typeof CtUsersUserListing>>();
const statusFilter = ref('all');

const acl = inject<AclService>('acl');
if (!acl) {
    throw new Error('The ACL service is unavailable.');
}
const userTotal = ref(0);
const userListingLoading = ref(true);
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
    void router.push({ name: 'ct.users.create' });
};
const onEditUser = (user: { id: string }) => {
    void router.push({ name: 'ct.users.detail', params: { id: user.id } });
};

ctDefinePublic({
    acl,
    userTotal,
    userListingLoading,
    reloadUserListing,
    onUserSearch,
    onStatusFilterChange,
    resetUserFilters,
    onUserTotalChange,
    onUserLoadingChange,
    onCreateUser,
    onEditUser,
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
    onCreateUser,
    onEditUser,
});
</script>
