<template>
    <ct-block name="sw_permissions">
        <ct-page class="ct-permissions">
            <template #search-bar>
                <ct-block name="sw_permissions_search_bar">
                    <mt-search
                        :model-value="roleSearchTerm"
                        :placeholder="$t('ct-permissions.roles.general.placeholderSearchBar')"
                        @change="onRoleSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_permissions_smart_bar_header">
                    <h2>
                        <span>{{ $t('ct-permissions.roles.grid.title') }}</span>
                        <span v-if="!roleListingLoading" class="ct-page__smart-bar-amount">({{ roleTotal }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_permissions_smart_bar_actions">
                    <mt-button
                        v-tooltip.bottom="{
                            message: $t('ct-privileges.tooltip.warning'),
                            disabled: acl.can('users_and_permissions.creator'),
                            showOnDisabledElements: true,
                        }"
                        class="ct-permissions__create-role"
                        variant="primary"
                        size="default"
                        :disabled="!acl.can('users_and_permissions.creator') || undefined"
                        @click="openCreateRole"
                    >
                        {{ $t('global.default.add') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_permissions_content">
                    <ct-card-view>
                        <ct-permissions-role-listing
                            ref="roleListing"
                            @loading-change="onRoleLoadingChange"
                            @total-change="onRoleTotalChange"
                        />
                    </ct-card-view>
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
import type AclService from 'src/app/service/acl.service';

interface RoleListingApi {
    getList(): void;

    onSearch: (term: string) => void;
    openCreateRole(): void;
}

const roleListing = ref<RoleListingApi | null>(null);
const roleSearchTerm = ref('');

const acl = inject<AclService>('acl');
if (!acl) {
    throw new Error('The ACL service is unavailable.');
}
const roleTotal = ref(0);
const roleListingLoading = ref(true);
const reloadRoleListing = () => roleListing.value?.getList();
const onRoleSearch = (term: string) => {
    roleSearchTerm.value = term;
    roleListing.value?.onSearch(term);
};
const openCreateRole = () => roleListing.value?.openCreateRole();
const onRoleTotalChange = (total: number) => {
    roleTotal.value = total;
};
const onRoleLoadingChange = (loading: boolean) => {
    roleListingLoading.value = loading;
};

swDefinePublic({
    acl,
    roleTotal,
    roleListingLoading,
    reloadRoleListing,
    onRoleSearch,
    openCreateRole,
    onRoleTotalChange,
    onRoleLoadingChange,
});

defineExpose({
    acl,
    roleSearchTerm,
    roleTotal,
    roleListingLoading,
    reloadRoleListing,
    onRoleSearch,
    openCreateRole,
    onRoleTotalChange,
    onRoleLoadingChange,
});
</script>
