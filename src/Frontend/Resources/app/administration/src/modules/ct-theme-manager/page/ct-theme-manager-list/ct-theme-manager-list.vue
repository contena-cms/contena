<template>
    <ct-block name="ct_theme_manager_list">
        <ct-page class="ct-theme-list">
            <template #search-bar>
                <ct-block name="ct_theme_manager_list_search_bar">
                    <mt-search
                        v-model="searchTerm"
                        :placeholder="t('ct-theme-manager.general.placeholderSearchBar')"
                        @change="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_theme_manager_list_toolbar">
                    <h2>
                        {{ t('ct-theme-manager.general.mainMenuItemGeneral') }}
                    </h2>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_theme_list_card_view">
                    <ct-card-view>
                        <ct-block name="ct_themes_list_listing">
                            <div class="ct-theme-list__content">
                                <ct-block name="ct_theme_list_listing_actions">
                                    <div class="ct-theme-list__actions">
                                        <ct-block name="ct_theme_list_listing_title">
                                            <h3>
                                                {{ t('ct-theme-manager.general.mainMenuHeader') }}
                                            </h3>
                                        </ct-block>

                                        <ct-block name="ct_theme_list_listing_actions_sorting">
                                            <div class="ct-theme-list__actions-sorting">
                                                <mt-select
                                                    :model-value="sortingConCat"
                                                    :options="sortOptions"
                                                    hide-clearable-button
                                                    @update:model-value="onSortingChanged"
                                                />
                                            </div>
                                        </ct-block>

                                        <ct-block name="ct_theme_list_listing_actions_mode">
                                            <mt-button
                                                class="ct-theme-list__actions-mode"
                                                variant="secondary"
                                                square
                                                @click="onListModeChange"
                                            >
                                                <mt-icon v-if="listMode === 'grid'" name="regular-view-normal" size="16" />
                                                <mt-icon v-if="listMode === 'list'" name="regular-view-grid" size="16" />
                                            </mt-button>
                                        </ct-block>
                                    </div>
                                </ct-block>

                                <ct-block name="ct_theme_list_listing_list">
                                    <div class="ct-theme-list__list">
                                        <ct-block name="ct_theme_list_listing_list_card">
                                            <mt-card v-if="listMode === 'list'" class="ct-theme-list__list-card">
                                                <template #grid>
                                                    <ct-block name="ct_theme_list_listing_list_data_grid">
                                                        <mt-data-table
                                                            class="ct-theme-list__list-data-grid"
                                                            :is-loading="isLoading"
                                                            :data-source="themes"
                                                            :columns="columnConfig"
                                                            :sort-by="sortBy"
                                                            :sort-direction="sortDirection"
                                                            :current-page="page"
                                                            :pagination-limit="limit"
                                                            :pagination-total-items="total"
                                                            :pagination-options="[5, 10, 25, 50]"
                                                            :disable-search="true"
                                                            :disable-settings-table="true"
                                                            :disable-edit="true"
                                                            :disable-delete="true"
                                                            enable-reload
                                                            @sort-change="onTableSortChange"
                                                            @pagination-current-page-change="onCurrentPageChange"
                                                            @pagination-limit-change="onTableLimitChange"
                                                            @reload="onRefresh"
                                                        >
                                                            <template #column-name="{ data }">
                                                                <ct-block
                                                                    name="ct_theme_list_listing_list_data_grid_column_name"
                                                                >
                                                                    <mt-icon
                                                                        v-if="data.technicalName"
                                                                        v-tooltip="lockToolTip"
                                                                        name="regular-lock"
                                                                        class="ct-theme-list__icon-lock"
                                                                        size="14"
                                                                    />
                                                                    <router-link
                                                                        :to="{
                                                                            name: 'ct.theme.manager.detail',
                                                                            params: {
                                                                                id: data.id,
                                                                            },
                                                                        }"
                                                                    >
                                                                        {{ data.name }}
                                                                    </router-link>
                                                                </ct-block>
                                                            </template>

                                                            <template #column-assignment="slotProps">
                                                                <ct-block
                                                                    name="ct_theme_list_listing_list_data_grid_column_assignment"
                                                                >
                                                                    <span v-text="slotProps.data.channels.length"></span>
                                                                </ct-block>
                                                            </template>

                                                            <template #column-createdAt="slotProps">
                                                                <ct-block
                                                                    name="ct_theme_list_listing_list_data_grid_column_created"
                                                                >
                                                                    <span
                                                                        v-text="
                                                                            dateFilter(slotProps.data.createdAt, {
                                                                                hour: '2-digit',
                                                                                minute: '2-digit',
                                                                            })
                                                                        "
                                                                    ></span>
                                                                </ct-block>
                                                            </template>

                                                            <template #column-actions="{ data }">
                                                                <ct-block
                                                                    name="ct_theme_list_listing_list_data_grid_actions"
                                                                >
                                                                    <mt-context-button>
                                                                        <ct-block
                                                                            name="ct_theme_list_listing_list_data_grid_actions_edit"
                                                                        >
                                                                            <mt-context-menu-item
                                                                                class="ct-theme-list-item__option-edit"
                                                                                :label="t('global.default.edit')"
                                                                                @click="onListItemClick(data)"
                                                                            />
                                                                        </ct-block>

                                                                        <ct-block
                                                                            name="ct_theme_list_listing_list_data_grid_actions_rename"
                                                                        >
                                                                            <mt-context-menu-item
                                                                                class="ct-theme-list-item__option-rename"
                                                                                :disabled="!acl.can('theme.editor')"
                                                                                :label="
                                                                                    t(
                                                                                        'ct-theme-manager.themeListItem.rename',
                                                                                    )
                                                                                "
                                                                                @click="onRenameTheme(data)"
                                                                            />
                                                                        </ct-block>

                                                                        <ct-block
                                                                            name="ct_theme_list_listing_list_data_grid_actions_delete"
                                                                        >
                                                                            <mt-context-menu-item
                                                                                v-if="!data.technicalName"
                                                                                v-tooltip="deleteDisabledToolTip(data)"
                                                                                type="critical"
                                                                                class="ct-theme-list-item__option-delete"
                                                                                :disabled="
                                                                                    data.channels.length > 0 ||
                                                                                    !acl.can('theme.deleter')
                                                                                "
                                                                                :label="t('global.default.delete')"
                                                                                @click="onDeleteTheme(data)"
                                                                            />
                                                                        </ct-block>

                                                                        <ct-block
                                                                            name="ct_theme_list_listing_list_data_grid_actions_create"
                                                                        >
                                                                            <mt-context-menu-item
                                                                                v-if="data.technicalName"
                                                                                class="ct-theme-list-item__option-duplicate"
                                                                                :disabled="!acl.can('theme.creator')"
                                                                                :label="t('global.default.duplicate')"
                                                                                @click="onDuplicateTheme(data)"
                                                                            />
                                                                        </ct-block>
                                                                    </mt-context-button>
                                                                </ct-block>
                                                            </template>
                                                        </mt-data-table>
                                                        <ct-block name="ct_theme_list_listing_list_data_grid_pagination" />
                                                    </ct-block>
                                                </template>
                                            </mt-card>
                                        </ct-block>

                                        <ct-block name="ct_theme_list_listing_list_grid">
                                            <div v-if="listMode === 'grid'" class="ct-theme-list__list-grid">
                                                <ct-block name="ct_theme_list_listing_list_grid_content">
                                                    <div class="ct-theme-list__list-grid-content">
                                                        <ct-block name="ct_theme_list_listing_list_item">
                                                            <template v-if="!isLoading">
                                                                <ct-theme-list-item
                                                                    v-for="theme in themes"
                                                                    :key="theme.id"
                                                                    :theme="theme"
                                                                    @preview-image-change="onPreviewChange"
                                                                    @item-click="onListItemClick"
                                                                >
                                                                    <template #contextMenu>
                                                                        <mt-context-button
                                                                            class="ct-theme-list-item__options"
                                                                        >
                                                                            <ct-block
                                                                                name="ct_theme_list_listing_list_item_option_add_preview"
                                                                            >
                                                                                <mt-context-menu-item
                                                                                    class="ct-theme-list-item__option-preview"
                                                                                    :disabled="!acl.can('theme.editor')"
                                                                                    :label="
                                                                                        t(
                                                                                            'ct-theme-manager.themeListItem.addPreviewImage',
                                                                                        )
                                                                                    "
                                                                                    @click="onPreviewChange(theme)"
                                                                                />
                                                                            </ct-block>

                                                                            <ct-block
                                                                                name="ct_theme_list_listing_list_item_option_remove_preview"
                                                                            >
                                                                                <mt-context-menu-item
                                                                                    v-if="theme.previewMediaId"
                                                                                    type="critical"
                                                                                    class="ct-theme-list-item__option-preview ct-theme-list-item__option-preview-remove"
                                                                                    :disabled="!acl.can('theme.editor')"
                                                                                    :label="
                                                                                        t(
                                                                                            'ct-theme-manager.themeListItem.removePreviewImage',
                                                                                        )
                                                                                    "
                                                                                    @click="onPreviewImageRemove(theme)"
                                                                                />
                                                                            </ct-block>

                                                                            <ct-block
                                                                                name="ct_theme_list_listing_list_item_option_rename"
                                                                            >
                                                                                <mt-context-menu-item
                                                                                    class="ct-theme-list-item__option-rename"
                                                                                    :disabled="!acl.can('theme.editor')"
                                                                                    :label="
                                                                                        t(
                                                                                            'ct-theme-manager.themeListItem.rename',
                                                                                        )
                                                                                    "
                                                                                    @click="onRenameTheme(theme)"
                                                                                />
                                                                            </ct-block>

                                                                            <ct-block
                                                                                name="ct_theme_list_listing_list_item_option_create"
                                                                            >
                                                                                <mt-context-menu-item
                                                                                    v-if="theme.technicalName"
                                                                                    class="ct-theme-list-item__option-duplicate"
                                                                                    :disabled="!acl.can('theme.creator')"
                                                                                    :label="t('global.default.duplicate')"
                                                                                    @click="onDuplicateTheme(theme)"
                                                                                />
                                                                            </ct-block>

                                                                            <ct-block
                                                                                name="ct_theme_list_listing_list_item_option_delete"
                                                                            >
                                                                                <mt-context-menu-item
                                                                                    v-if="!theme.technicalName"
                                                                                    v-tooltip="deleteDisabledToolTip(theme)"
                                                                                    class="ct-theme-list-item__option-delete"
                                                                                    type="critical"
                                                                                    :disabled="
                                                                                        (theme.channels?.length ?? 0) > 0 ||
                                                                                        !acl.can('theme.deleter')
                                                                                    "
                                                                                    :label="t('global.default.delete')"
                                                                                    @click="onDeleteTheme(theme)"
                                                                                />
                                                                            </ct-block>
                                                                        </mt-context-button>
                                                                    </template>
                                                                </ct-theme-list-item>
                                                            </template>

                                                            <template v-else>
                                                                <mt-skeleton-bar />
                                                                <mt-skeleton-bar />
                                                                <mt-skeleton-bar />
                                                                <mt-skeleton-bar />
                                                                <mt-skeleton-bar />
                                                                <mt-skeleton-bar />
                                                                <mt-skeleton-bar />
                                                                <mt-skeleton-bar />
                                                                <mt-skeleton-bar />
                                                            </template>
                                                        </ct-block>
                                                    </div>
                                                </ct-block>

                                                <ct-block name="ct_theme_list_listing_pagination">
                                                    <mt-pagination
                                                        v-if="!isLoading"
                                                        class="ct-theme-list__list-pagination"
                                                        :current-page="page"
                                                        :limit="limit"
                                                        :total-items="total"
                                                        @change-current-page="onCurrentPageChange"
                                                    />
                                                </ct-block>
                                            </div>
                                        </ct-block>
                                    </div>
                                </ct-block>
                            </div>
                        </ct-block>

                        <ct-block name="ct_theme_list_media_modal">
                            <ct-media-modal-v2
                                v-if="showMediaModal"
                                :caption="t('ct-theme-manager.general.captionMediaUpload')"
                                entity-context="theme"
                                :allow-multi-select="false"
                                @media-modal-selection-change="onPreviewImageChange"
                                @modal-close="onModalClose"
                            />
                        </ct-block>

                        <ct-block name="ct_theme_list_delete_modal">
                            <mt-modal-root v-if="showDeleteModal" :is-open="showDeleteModal" @change="onDeleteModalChange">
                                <mt-modal :title="t('global.default.warning')" width="s">
                                    <ct-block name="ct_theme_list_delete_modal_info">
                                        <div class="ct_theme_manager__confirm-delete-text">
                                            {{
                                                t('ct-theme-manager.modal.textDeleteInfo', {
                                                    themeName: modalTheme?.name,
                                                })
                                            }}
                                        </div>
                                    </ct-block>

                                    <template #footer>
                                        <ct-block name="ct_theme_list_delete_modal_footer">
                                            <ct-block name="ct_theme_list_delete_modal_cancel">
                                                <mt-button variant="secondary" size="small" @click="onCloseDeleteModal">
                                                    {{ t('global.default.cancel') }}
                                                </mt-button>
                                            </ct-block>

                                            <ct-block name="ct_theme_list_delete_modal_confirm">
                                                <mt-button variant="critical" size="small" @click="onConfirmThemeDelete">
                                                    {{ t('global.default.delete') }}
                                                </mt-button>
                                            </ct-block>
                                        </ct-block>
                                    </template>
                                </mt-modal>
                            </mt-modal-root>
                        </ct-block>

                        <ct-block name="ct_theme_list_duplicate_modal">
                            <mt-modal-root
                                v-if="showDuplicateModal"
                                :is-open="showDuplicateModal"
                                @change="onDuplicateModalChange"
                            >
                                <mt-modal
                                    class="ct_theme_manager__duplicate-modal"
                                    :title="t('ct-theme-manager.modal.modalTitleDuplicate')"
                                    width="s"
                                >
                                    <ct-block name="ct_theme_list_duplicate__modal_name_input">
                                        <div class="ct_theme_manager__duplicate-info">
                                            {{ t('ct-theme-manager.modal.textDuplicateInfo') }}
                                        </div>

                                        <mt-text-field
                                            v-model="newThemeName"
                                            name="ct-field--duplicate-theme-name"
                                            :label="t('ct-theme-manager.modal.labelDuplicateThemeName')"
                                            :placeholder="t('ct-theme-manager.modal.placeholderDuplicateThemeName')"
                                        />
                                    </ct-block>

                                    <template #footer>
                                        <ct-block name="ct_theme_list_duplicate_modal_footer">
                                            <ct-block name="ct_theme_list_duplicate_modal_cancel">
                                                <mt-button variant="secondary" size="small" @click="onCloseDuplicateModal">
                                                    {{ t('global.default.cancel') }}
                                                </mt-button>
                                            </ct-block>

                                            <ct-block name="ct_theme_list_duplicate_modal_confirm">
                                                <mt-button
                                                    variant="primary"
                                                    :disabled="newThemeName.length < 3"
                                                    size="small"
                                                    @click="onConfirmThemeDuplicate"
                                                >
                                                    {{ t('global.default.duplicate') }}
                                                </mt-button>
                                            </ct-block>
                                        </ct-block>
                                    </template>
                                </mt-modal>
                            </mt-modal-root>
                        </ct-block>

                        <ct-block name="ct_theme_list_rename_modal">
                            <mt-modal-root v-if="showRenameModal" :is-open="showRenameModal" @change="onRenameModalChange">
                                <mt-modal
                                    class="ct_theme_manager__rename-modal"
                                    :title="t('ct-theme-manager.modal.modalTitleRename')"
                                    width="s"
                                >
                                    <ct-block name="ct_theme_list_rename__modal_name_input">
                                        <div class="ct_theme_manager__rename-info">
                                            {{ t('ct-theme-manager.modal.textRenameInfo') }}
                                        </div>

                                        <mt-text-field
                                            v-model="newThemeName"
                                            name="ct-field--rename-theme-name"
                                            :label="t('ct-theme-manager.modal.labelRenameThemeName')"
                                            :placeholder="t('ct-theme-manager.modal.placeholderRenameThemeName')"
                                        />
                                    </ct-block>

                                    <template #footer>
                                        <ct-block name="ct_theme_list_rename_modal_footer">
                                            <ct-block name="ct_theme_list_rename_modal_cancel">
                                                <mt-button variant="secondary" size="small" @click="onCloseRenameModal">
                                                    {{ t('global.default.cancel') }}
                                                </mt-button>
                                            </ct-block>

                                            <ct-block name="ct_theme_list_rename_modal_confirm">
                                                <mt-button
                                                    variant="primary"
                                                    :disabled="newThemeName.length < 3"
                                                    size="small"
                                                    @click="onConfirmThemeRename"
                                                >
                                                    {{ t('global.default.save') }}
                                                </mt-button>
                                            </ct-block>
                                        </ct-block>
                                    </template>
                                </mt-modal>
                            </mt-modal-root>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';

import { useListing } from 'src/app/composables/use-listing';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { useTheme, type ThemeEntity } from '../../composable/use-theme';
import './ct-theme-manager-list.scss';

type Theme = ThemeEntity;

interface ColumnConfig {
    property: string;
    label: string;
    renderer: 'text';
    position: number;
    sortable: boolean;
    allowResize?: boolean;
    width?: number;
}

interface PageChange {
    page: number;
    limit: number;
}

type DateFilter = (value: string, options?: Record<string, string>) => string;
type ThemeList = EntityCollection<'theme'> | Theme[];

const Criteria = Contena.Data.Criteria;
const router = useRouter();
const route = useRoute();
const { t } = useI18n();

defineOptions({
    metaInfo(this: { $createTitle: (identifier?: string | null) => string; identifier?: string | null }) {
        return {
            title: this.$createTitle(this.identifier),
        };
    },
});
const injectedRepositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const injectedAcl = inject<AclService>('acl');
if (!injectedRepositoryFactory || !injectedAcl) {
    throw new Error('The repositoryFactory and acl services are required.');
}

const repositoryFactory = injectedRepositoryFactory;
const acl = injectedAcl;

const themes = ref<ThemeList>([]);
const isLoading = ref(false);
const total = ref(0);
const disableRouteParams = ref(true);
const channelId = ref(route.params.id as string);
const listMode = ref<'grid' | 'list'>('grid');
const sortBy = ref('createdAt');
const sortDirection = ref<'ASC' | 'DESC'>('DESC');
const limit = ref(9);
const term = ref<string | null>(null);
const searchTerm = ref('');
const currentTheme = ref<Theme | null>();
const listing = useListing();
const { page, updateRoute, onSortColumn } = listing;
const theme = useTheme({ isLoading, getList });
const { themeRepository } = theme;
const languageRepository = computed(() => repositoryFactory.create('language'));
const columnConfig = computed(() => getColumnConfig());
const sortOptions = computed(() => [
    {
        value: 'createdAt:DESC',
        label: t('ct-theme-manager.sorting.labelSortByCreatedDsc'),
    },
    {
        value: 'createdAt:ASC',
        label: t('ct-theme-manager.sorting.labelSortByCreatedAsc'),
    },
    {
        value: 'updatedAt:DESC',
        label: t('ct-theme-manager.sorting.labelSortByUpdatedDsc'),
    },
    {
        value: 'updatedAt:ASC',
        label: t('ct-theme-manager.sorting.labelSortByUpdatedAsc'),
    },
]);
const sortingConCat = computed(() => `${sortBy.value}:${sortDirection.value}`);
const lockToolTip = computed(() => ({
    showDelay: 100,
    message: t('ct-theme-manager.general.lockedToolTip'),
}));
const dateFilter = computed(() => Contena.Filter.getByName('date') as DateFilter);

async function getList(): Promise<EntityCollection<'theme'> | undefined> {
    isLoading.value = true;
    const criteria = new Criteria(page.value, limit.value);
    criteria.addAssociation('previewMedia');
    criteria.addAssociation('channels');
    criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value));
    criteria.addFilter(Criteria.equals('active', true));

    if (term.value !== null) {
        criteria.setTerm(term.value);
    }

    try {
        const searchResult = await themeRepository.value.search(criteria, Contena.Context.api);
        total.value = searchResult.total ?? 0;
        themes.value = searchResult;

        return searchResult;
    } catch {
        return undefined;
    } finally {
        isLoading.value = false;
    }
}
function onRefresh(): void {
    void getList();
}
function resetList(): void {
    page.value = 1;
    themes.value = [];
    updateRoute({
        page: page.value,
        limit: limit.value,
        term: term.value,
        sortBy: sortBy.value,
        sortDirection: sortDirection.value,
    });
    void getList();
}
function onChangeLanguage(languageId: string): void {
    Contena.Context.api.languageId = languageId;
    resetList();
}
function onListItemClick(item: Theme): void {
    void router.push({
        name: 'ct.theme.manager.detail',
        params: { id: item.id },
    });
}
function onSortingChanged(value: string): void {
    const [
        newSortBy,
        newSortDirection,
    ] = value.split(':');
    sortBy.value = newSortBy;
    sortDirection.value = newSortDirection as 'ASC' | 'DESC';
    resetList();
}
function onSearch(value = ''): void {
    searchTerm.value = value;
    term.value = value.length > 0 ? value : null;
    resetList();
}
function onPageChange({ page: newPage, limit: newLimit }: PageChange): void {
    page.value = newPage;
    limit.value = newLimit;
    void getList();
    updateRoute({ page: page.value, limit: limit.value });
}
function onCurrentPageChange(newPage: number): void {
    onPageChange({ page: newPage, limit: limit.value });
}
function onTableLimitChange(newLimit: number): void {
    onPageChange({ page: 1, limit: newLimit });
}
function onTableSortChange(property: string, direction: 'ASC' | 'DESC'): void {
    sortBy.value = property;
    sortDirection.value = direction;
    resetList();
}
function onListModeChange(): void {
    listMode.value = listMode.value === 'grid' ? 'list' : 'grid';
    limit.value = listMode.value === 'grid' ? 9 : 10;
    resetList();
}
function onPreviewChange(item: Theme): void {
    if (!acl.can('theme.editor')) {
        return;
    }

    theme.showMediaModal.value = true;
    currentTheme.value = item;
}
function onPreviewImageRemove(item: Theme): void {
    if (!acl.can('theme.editor')) {
        return;
    }

    item.previewMediaId = null;
    item.previewMedia = null;
    void saveTheme(item);
}
function onModalClose(): void {
    theme.showMediaModal.value = false;
    currentTheme.value = null;
}
function onPreviewImageChange([image]: Entity<'media'>[]): void {
    if (!currentTheme.value) {
        return;
    }

    currentTheme.value.previewMediaId = image.id;
    void saveTheme(currentTheme.value);
    currentTheme.value.previewMedia = image;
}
async function saveTheme(item: Theme): Promise<void> {
    isLoading.value = true;
    try {
        await themeRepository.value.save(item as Entity<'theme'>, Contena.Context.api);
    } catch {
        // The legacy implementation deliberately ignores persistence errors here.
    } finally {
        isLoading.value = false;
    }
}
function getColumnConfig(): ColumnConfig[] {
    return [
        {
            property: 'name',
            label: t('ct-theme-manager.list.gridHeaderName'),
            renderer: 'text',
            position: 100,
            sortable: true,
        },
        {
            property: 'assignment',
            label: t('ct-theme-manager.list.gridHeaderAssignment'),
            renderer: 'text',
            position: 200,
            sortable: false,
        },
        {
            property: 'createdAt',
            label: t('ct-theme-manager.list.gridHeaderCreated'),
            renderer: 'text',
            position: 300,
            sortable: true,
        },
        {
            property: 'actions',
            label: '',
            renderer: 'text',
            position: 400,
            sortable: false,
            allowResize: false,
            width: 64,
        },
    ];
}
function deleteDisabledToolTip(item: Theme): {
    showDelay: number;
    message: string;
    disabled: boolean;
} {
    return {
        showDelay: 300,
        message: t('ct-theme-manager.actions.deleteDisabledToolTip'),
        disabled: (item.channels?.length ?? 0) === 0,
    };
}
function onDeleteModalChange(isOpen: boolean): void {
    if (!isOpen) {
        theme.onCloseDeleteModal();
    }
}
function onDuplicateModalChange(isOpen: boolean): void {
    if (!isOpen) {
        theme.onCloseDuplicateModal();
    }
}
function onRenameModalChange(isOpen: boolean): void {
    if (!isOpen) {
        theme.onCloseRenameModal();
    }
}

listing.initializeListing({
    limit,
    total,
    sortBy,
    sortDirection,
    term,
    disableRouteParams,
    getList,
});

const {
    showDeleteModal,
    showMediaModal,
    showRenameModal,
    showDuplicateModal,
    newThemeName,
    modalTheme,
    onDeleteTheme,
    onCloseDeleteModal,
    onConfirmThemeDelete,
    deleteTheme,
    onDuplicateTheme,
    onCloseDuplicateModal,
    onConfirmThemeDuplicate,
    duplicateTheme,
    onRenameTheme,
    onCloseRenameModal,
    onConfirmThemeRename,
    RenameTheme,
} = theme;

ctDefinePublic({
    acl,
    themes,
    isLoading,
    total,
    disableRouteParams,
    channelId,
    listMode,
    sortBy,
    sortDirection,
    limit,
    term,
    searchTerm,
    currentTheme,
    languageRepository,
    columnConfig,
    sortOptions,
    sortingConCat,
    lockToolTip,
    dateFilter,
    page,
    updateRoute,
    onSortColumn,
    getList,
    onRefresh,
    resetList,
    onChangeLanguage,
    onListItemClick,
    onSortingChanged,
    onSearch,
    onPageChange,
    onCurrentPageChange,
    onTableLimitChange,
    onTableSortChange,
    onListModeChange,
    onPreviewChange,
    onPreviewImageRemove,
    onModalClose,
    onPreviewImageChange,
    saveTheme,
    getColumnConfig,
    deleteDisabledToolTip,
    onDeleteModalChange,
    onDuplicateModalChange,
    onRenameModalChange,
    showDeleteModal,
    showMediaModal,
    showRenameModal,
    showDuplicateModal,
    newThemeName,
    modalTheme,
    themeRepository,
    onDeleteTheme,
    onCloseDeleteModal,
    onConfirmThemeDelete,
    deleteTheme,
    onDuplicateTheme,
    onCloseDuplicateModal,
    onConfirmThemeDuplicate,
    duplicateTheme,
    onRenameTheme,
    onCloseRenameModal,
    onConfirmThemeRename,
    RenameTheme,
});

defineExpose({
    acl,
    themes,
    isLoading,
    total,
    disableRouteParams,
    channelId,
    listMode,
    sortBy,
    sortDirection,
    limit,
    term,
    searchTerm,
    currentTheme,
    languageRepository,
    columnConfig,
    sortOptions,
    sortingConCat,
    lockToolTip,
    dateFilter,
    page,
    updateRoute,
    onSortColumn,
    showDeleteModal,
    showMediaModal,
    showRenameModal,
    showDuplicateModal,
    newThemeName,
    modalTheme,
    themeRepository,
    onDeleteTheme,
    onCloseDeleteModal,
    onConfirmThemeDelete,
    deleteTheme,
    onDuplicateTheme,
    onCloseDuplicateModal,
    onConfirmThemeDuplicate,
    duplicateTheme,
    onRenameTheme,
    onCloseRenameModal,
    onConfirmThemeRename,
    RenameTheme,
    getList,
    onRefresh,
    resetList,
    onChangeLanguage,
    onListItemClick,
    onSortingChanged,
    onSearch,
    onPageChange,
    onCurrentPageChange,
    onTableLimitChange,
    onTableSortChange,
    onListModeChange,
    onPreviewChange,
    onPreviewImageRemove,
    onModalClose,
    onPreviewImageChange,
    saveTheme,
    getColumnConfig,
    deleteDisabledToolTip,
    onDeleteModalChange,
    onDuplicateModalChange,
    onRenameModalChange,
});
</script>
