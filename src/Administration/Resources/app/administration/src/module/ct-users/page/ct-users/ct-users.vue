<template>
    <ct-block name="sw_users">
        <ct-page class="ct-users" :show-smart-bar="false" :show-search-bar="false">
            <template #content>
                <div class="ct-users__shell">
                    <ct-block name="sw_users_topbar">
                        <header class="ct-users__topbar">
                            <div class="ct-users__topbar-left">
                                <a-button
                                    class="ct-users__mobile-menu"
                                    type="text"
                                    shape="circle"
                                    :aria-label="$t('ct-users.general.openMenu')"
                                    @click="toggleMobileMenu"
                                >
                                    <ct-icon name="MenuOutlined" :size="18" />
                                </a-button>
                                <a-breadcrumb :items="breadcrumbs" />
                            </div>
                        </header>
                    </ct-block>

                    <main class="ct-users__workspace">
                        <ct-block name="sw_users_list_panel">
                            <section class="ct-users__list-panel">
                                <ct-block name="sw_users_smart_bar_header">
                                    <header class="ct-users__list-panel-header">
                                        <div>
                                            <h1>{{ $t('ct-users.general.labelUserList') }}</h1>
                                            <p v-if="!userListingLoading">
                                                {{ $t('ct-users.general.total', { total: userTotal }) }}
                                            </p>
                                        </div>

                                        <ct-block name="sw_users_smart_bar_actions">
                                            <a-tooltip
                                                :title="
                                                    acl.can('users_and_permissions.creator')
                                                        ? undefined
                                                        : $t('ct-privileges.tooltip.warning')
                                                "
                                            >
                                                <a-button
                                                    class="ct-users__create-user"
                                                    type="primary"
                                                    :disabled="!acl.can('users_and_permissions.creator')"
                                                    @click="$router.push({ name: 'ct.users.user.create' })"
                                                >
                                                    <template #icon><ct-icon name="PlusOutlined" /></template>
                                                    {{ $t('global.default.add') }}
                                                </a-button>
                                            </a-tooltip>
                                        </ct-block>
                                    </header>
                                </ct-block>

                                <ct-block name="sw_users_search_bar">
                                    <div class="ct-users__toolbar">
                                        <a-input-search
                                            v-model:value="userSearchTerm"
                                            class="ct-users__search"
                                            :placeholder="$t('ct-users.general.placeholderSearchBar')"
                                            allow-clear
                                            @search="onUserSearch"
                                            @change="onSearchInputChange"
                                        />
                                        <a-select
                                            v-model:value="statusFilter"
                                            class="ct-users__filter"
                                            :aria-label="$t('ct-users.filter.status')"
                                            :options="userListing?.statusFilterOptions ?? []"
                                            @change="onStatusFilterChange"
                                        />
                                        <a-select
                                            v-model:value="roleFilter"
                                            class="ct-users__role-filter"
                                            mode="multiple"
                                            allow-clear
                                            :max-tag-count="1"
                                            :placeholder="$t('ct-users.filter.rolesPlaceholder')"
                                            :options="userListing?.roleFilterOptions ?? []"
                                            @change="onRoleFilterChange"
                                        />
                                        <a-tooltip :title="$t('ct-users.filter.reset')">
                                            <a-button
                                                class="ct-users__reset-filters"
                                                :disabled="userListingFilterCount === 0"
                                                :aria-label="$t('ct-users.filter.reset')"
                                                @click="resetUserFilters"
                                            >
                                                <template #icon><ct-icon name="ReloadOutlined" /></template>
                                            </a-button>
                                        </a-tooltip>

                                        <div class="ct-users__table-tools">
                                            <template v-if="selectedUserCount > 0">
                                                <span class="ct-users__selection-count">
                                                    {{ $t('ct-users.bulk.selected', { count: selectedUserCount }) }}
                                                </span>
                                                <a-button danger @click="userListing?.requestBulkDelete()">
                                                    <template #icon><ct-icon name="DeleteOutlined" /></template>
                                                    {{ $t('ct-users.bulk.delete') }}
                                                </a-button>
                                            </template>

                                            <ct-table-column-setting
                                                :columns="localizedColumnSettings"
                                                :default-columns="defaultColumnSettings"
                                                :title="$t('ct-users.columns.title')"
                                                :all-label="$t('ct-users.columns.all')"
                                                :reset-label="$t('ct-users.columns.reset')"
                                                :cancel-label="$t('global.default.cancel')"
                                                :apply-label="$t('global.default.apply')"
                                                :fixed-left-label="$t('ct-users.columns.fixedLeft')"
                                                :fixed-right-label="$t('ct-users.columns.fixedRight')"
                                                @apply="onColumnSettingsApply"
                                            />
                                        </div>
                                    </div>
                                </ct-block>

                                <ct-block name="sw_users_content">
                                    <ct-users-user-listing
                                        ref="userListing"
                                        :column-settings="localizedColumnSettings"
                                        @loading-change="onUserLoadingChange"
                                        @selection-change="onSelectionChange"
                                        @total-change="onUserTotalChange"
                                    />
                                </ct-block>
                            </section>
                        </ct-block>
                    </main>
                </div>

                <ct-users-user-create v-if="$route.name === 'ct.users.user.create'" />
                <ct-users-user-detail v-else-if="$route.name === 'ct.users.user.detail'" />
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
import { useI18n } from 'vue-i18n';
import type { ComponentExposed } from 'vue-component-type-helpers';
import type AclService from 'src/app/service/acl.service';
import type { TableColumnSetting } from 'src/app/component/base/ct-table-column-setting';
import CtTableColumnSetting from 'src/app/component/base/ct-table-column-setting/ct-table-column-setting.vue';
import SwUsersUserListing from '../../component/ct-users-user-listing/ct-users-user-listing.vue';

const { t } = useI18n();
const userListing = ref<ComponentExposed<typeof SwUsersUserListing>>();
const userListingFilterCount = computed<number>(() => userListing.value?.filterCount ?? 0);
const userSearchTerm = ref('');
const statusFilter = ref('all');
const roleFilter = ref<string[]>([]);
const selectedUserCount = ref(0);
const defaultColumnSettings = computed<TableColumnSetting[]>(() => [
    {
        key: 'user',
        title: t('ct-users.user-grid.labelName'),
        checked: true,
        fixed: 'left',
        required: true,
    },
    { key: 'userCode', title: t('ct-users.user-grid.labelUserCode'), checked: true },
    { key: 'contact', title: t('ct-users.user-grid.labelContact'), checked: true },
    { key: 'aclRoles', title: t('ct-users.user-grid.labelRoles'), checked: true },
    { key: 'active', title: t('ct-users.user-grid.status'), checked: true },
    { key: 'action', title: '', checked: true, fixed: 'right', required: true },
]);
const columnSettings = ref<TableColumnSetting[]>(defaultColumnSettings.value.map((column) => ({ ...column })));
const localizedColumnSettings = computed<TableColumnSetting[]>(() => {
    const titles = new Map(
        defaultColumnSettings.value.map((column) => [
            column.key,
            column.title,
        ]),
    );

    return columnSettings.value.map((column) => ({
        ...column,
        title: titles.get(column.key) ?? column.title,
    }));
});
const breadcrumbs = computed(() => [
    { title: t('global.ct-admin-menu.navigation.mainMenuItemSystem') },
    { title: t('ct-users.general.cardLabel') },
]);

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
const onSearchInputChange = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    if (!value) {
        onUserSearch('');
    }
};
const onStatusFilterChange = (value: string) => {
    statusFilter.value = value;
    void userListing.value?.setStatusFilter(value);
};
const onRoleFilterChange = (value: string[]) => {
    roleFilter.value = value;
    userListing.value?.setRoleFilter(value);
};
const resetUserFilters = () => {
    statusFilter.value = 'all';
    roleFilter.value = [];
    userListing.value?.resetFilters();
};
const onUserTotalChange = (total: number) => {
    userTotal.value = total;
};
const onUserLoadingChange = (loading: boolean) => {
    userListingLoading.value = loading;
};
const onSelectionChange = (count: number) => {
    selectedUserCount.value = count;
};
const onColumnSettingsApply = (settings: TableColumnSetting[]) => {
    columnSettings.value = settings;
};
const toggleMobileMenu = (): void => {
    Contena.Utils.EventBus.emit('ct-admin-menu/toggle-offcanvas', true);
};

swDefinePublic({
    acl,
    userTotal,
    userListingLoading,
    reloadUserListing,
    onUserSearch,
    onSearchInputChange,
    onStatusFilterChange,
    onRoleFilterChange,
    resetUserFilters,
    onUserTotalChange,
    onUserLoadingChange,
    onSelectionChange,
    columnSettings,
    localizedColumnSettings,
    defaultColumnSettings,
    onColumnSettingsApply,
    toggleMobileMenu,
});

defineExpose({
    acl,
    statusFilter,
    roleFilter,
    selectedUserCount,
    columnSettings,
    localizedColumnSettings,
    defaultColumnSettings,
    userTotal,
    userListingLoading,
    reloadUserListing,
    onUserSearch,
    onStatusFilterChange,
    onRoleFilterChange,
    resetUserFilters,
    onUserTotalChange,
    onUserLoadingChange,
    onSelectionChange,
    onColumnSettingsApply,
});
</script>

<style lang="scss">
.ct-users {
    .ct-page__main-content-inner {
        background: var(--ct-color-bg-layout);
    }

    &__shell {
        display: grid;
        grid-template-rows: var(--ct-layout-topbar-height) minmax(0, 1fr);
        min-height: 100%;
    }

    &__topbar {
        display: flex;
        align-items: center;
        min-width: 0;
        padding-inline: var(--ct-spacing-lg);
        background: var(--ct-color-bg-container);
        border-bottom: 1px solid var(--ct-color-border-secondary);
    }

    &__topbar-left {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 10px;
    }

    &__mobile-menu {
        display: none;
    }

    &__workspace {
        display: flex;
        width: 100%;
        max-width: var(--ct-layout-content-max-width);
        min-height: calc(100vh - var(--ct-layout-topbar-height));
        margin-inline: auto;
        padding: var(--ct-spacing-lg) var(--ct-spacing-lg) var(--ct-spacing-xl);
    }

    &__toolbar {
        display: flex;
        align-items: center;
        min-height: 58px;
        gap: 10px;
        padding: 12px var(--ct-spacing-lg);
        border-bottom: 1px solid var(--ct-color-border-secondary);
    }

    &__list-panel {
        display: flex;
        width: 100%;
        min-height: 100%;
        overflow: hidden;
        flex-direction: column;
        background: var(--ct-color-bg-container);
        border: 1px solid var(--ct-color-border-secondary);
        border-radius: var(--ct-border-radius);
    }

    &__list-panel > [data-block-name='sw_users_content'],
    &__list-panel > .ct-users-user-listing {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
    }

    &__list-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 68px;
        padding: 12px var(--ct-spacing-lg);
        border-bottom: 1px solid var(--ct-color-border-secondary);

        h1 {
            margin: 0;
            color: var(--ct-color-text);
            font-size: 16px;
            font-weight: 600;
            line-height: 24px;
            letter-spacing: 0;
        }

        p {
            margin: 2px 0 0;
            color: var(--ct-color-text-tertiary);
            font-size: var(--ct-font-size-sm);
            line-height: 18px;
        }
    }

    &__search {
        width: 280px;
    }

    &__filter {
        width: 130px;
    }

    &__role-filter {
        width: 220px;
    }

    &__table-tools {
        display: flex;
        align-items: center;
        gap: var(--ct-spacing-sm);
        margin-left: auto;
    }

    &__selection-count {
        color: var(--ct-color-text-secondary);
        font-size: var(--ct-font-size-sm);
        white-space: nowrap;
    }

    &__column-popover {
        .ant-popover-inner {
            width: 208px;
            padding: 0;
        }

        .ant-popover-title {
            min-width: 0;
            min-height: 44px;
            margin: 0;
            padding: 6px 8px 6px var(--ct-spacing-md);
            border-bottom: 1px solid var(--ct-color-border-secondary);
        }

        .ant-popover-inner-content {
            padding: 12px var(--ct-spacing-md) var(--ct-spacing-md);
        }

        .ant-divider {
            margin: 10px 0;
        }

        .ant-checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ant-checkbox-wrapper {
            margin-inline-start: 0;
            color: var(--ct-color-text);
        }
    }

    &__column-popover-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 32px;

        .ant-btn {
            padding-inline: 8px;
        }
    }

    @media screen and (max-width: 1280px) {
        &__mobile-menu {
            display: inline-flex;
        }
    }

    @media screen and (max-width: 760px) {
        &__topbar {
            padding-inline: var(--ct-spacing-sm);
        }

        &__workspace {
            padding: var(--ct-spacing-md) var(--ct-spacing-sm) var(--ct-spacing-xl);
        }

        &__toolbar {
            flex-wrap: wrap;
        }

        &__search,
        &__role-filter {
            width: 100%;
        }

        &__filter {
            flex: 1;
        }

        &__table-tools {
            width: 100%;
        }
    }
}
</style>
