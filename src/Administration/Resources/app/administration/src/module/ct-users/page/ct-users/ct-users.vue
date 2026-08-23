<template>
    <ct-block name="sw_users">
        <ct-page class="ct-users">
            <template #search-bar>
                <ct-block name="sw_users_search_bar">
                    <mt-search
                        :model-value="userSearchTerm"
                        :placeholder="$t('ct-users.general.placeholderSearchBar')"
                        @change="onUserSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_users_smart_bar_header">
                    <h2>
                        <ct-block name="sw_users_smart_bar_header_title_text">
                            <span>{{ $t('ct-users.general.cardLabel') }}</span>
                        </ct-block>

                        <span v-if="!userListingLoading" class="ct-page__smart-bar-amount">({{ userTotal }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_users_smart_bar_actions">
                    <mt-popover width="medium" :title="$t('ct-users.filter.title')">
                        <template #trigger="{ toggleFloatingUi }">
                            <mt-button
                                class="ct-users__filter-menu-trigger"
                                variant="secondary"
                                size="default"
                                @click.stop="toggleFloatingUi"
                            >
                                <mt-icon name="regular-filter-s" size="16" />
                                {{ $t('ct-users.filter.title') }}
                                <i v-if="userListingFilterCount > 0" class="filter-badge">
                                    {{ userListingFilterCount }}
                                </i>
                            </mt-button>
                        </template>

                        <template #popover-items__base>
                            <mt-select
                                :model-value="statusFilter"
                                class="ct-users__status-filter"
                                :label="$t('ct-users.filter.status')"
                                :options="userListing?.statusFilterOptions ?? []"
                                @update:model-value="onStatusFilterChange"
                            />

                            <mt-select
                                :model-value="userListing?.roleFilter ?? []"
                                :label="$t('ct-users.filter.roles')"
                                :placeholder="$t('ct-users.filter.rolesPlaceholder')"
                                :options="userListing?.roleFilterOptions ?? []"
                                enable-multi-selection
                                @update:model-value="userListing?.setRoleFilter($event)"
                            />

                            <mt-popover-item
                                type="critical"
                                icon="solid-undo"
                                :label="$t('ct-users.filter.reset')"
                                :on-label-click="resetUserFilters"
                            />
                        </template>
                    </mt-popover>

                    <mt-button
                        v-tooltip.bottom="{
                            message: $t('ct-privileges.tooltip.warning'),
                            disabled: acl.can('users_and_permissions.creator'),
                            showOnDisabledElements: true,
                        }"
                        class="ct-users__create-user"
                        variant="primary"
                        size="default"
                        :disabled="!acl.can('users_and_permissions.creator') || undefined"
                        @click="$router.push({ name: 'ct.users.user.create' })"
                    >
                        {{ $t('global.default.add') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_users_content">
                    <ct-card-view>
                        <ct-users-user-listing
                            ref="userListing"
                            @loading-change="onUserLoadingChange"
                            @total-change="onUserTotalChange"
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

import { computed, inject, ref } from 'vue';
import type { ComponentExposed } from 'vue-component-type-helpers';
import type AclService from 'src/app/service/acl.service';
import SwUsersUserListing from '../../component/ct-users-user-listing/ct-users-user-listing.vue';

const userListing = ref<ComponentExposed<typeof SwUsersUserListing>>();
const userListingFilterCount = computed<number>(() => userListing.value?.filterCount ?? 0);
const userSearchTerm = ref('');
const statusFilter = ref('all');

const acl = inject<AclService>('acl');
if (!acl) {
    throw new Error('The ACL service is unavailable.');
}
const userTotal = ref(0);
const userListingLoading = ref(true);

const reloadUserListing = () => userListing.value?.getList();
const onUserSearch = (term: string) => {
    userSearchTerm.value = term;
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
});
</script>
