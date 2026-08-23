<template>
    <ct-block name="sw_media_index">
        <ct-page class="ct-media-index">
            <template #search-bar>
                <ct-block name="sw_media_index_search_bar">
                    <mt-search
                        :model-value="term"
                        :placeholder="$t('ct-media.general.placeholderSearchBar')"
                        @change="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_media_index_smart_bar_header">
                    <h2>
                        <ct-block name="sw_media_index_smart_bar_heading">
                            {{ $t('ct-media.index.titleLibrary') }}
                        </ct-block>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_media_index_smart_bar_media_upload">
                    <ct-media-upload-v2
                        v-tooltip="{
                            message: $t('ct-privileges.tooltip.warning'),
                            disabled: acl.can('media.creator'),
                            showOnDisabledElements: true,
                        }"
                        :disabled="!acl.can('media.creator') || undefined"
                        variant="compact"
                        :file-accept="fileAccept"
                        :target-folder-id="routeFolderId"
                        :upload-tag="uploadTag"
                    />
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="sw_media_index_language_switch">
                    <ct-language-switch @on-change="reloadList" />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_media_index_page_content">
                    <div
                        class="ct-media-index__page-content"
                        :class="{ 'ct-media-index__page-content--has-selection': selectedItems.length > 0 }"
                    >
                        <ct-block name="sw_media_index_folder_navigation">
                            <aside class="ct-media-index__folder-navigation" :aria-label="$t('ct-media.index.labelFolders')">
                                <div class="ct-media-index__folder-navigation-label">
                                    {{ $t('ct-media.index.labelFolders') }}
                                </div>

                                <div class="ct-media-index__folder-tree" role="tree">
                                    <div
                                        class="ct-media-index__folder-tree-row ct-media-index__folder-tree-row--root"
                                        :class="{ 'is--active': !routeFolderId }"
                                        role="treeitem"
                                        aria-level="1"
                                        :aria-expanded="isTreeFolderExpanded(null)"
                                    >
                                        <mt-button
                                            class="ct-media-index__folder-tree-expand"
                                            variant="tertiary"
                                            size="small"
                                            square
                                            :aria-label="$t('ct-media.index.labelToggleFolder')"
                                            @click.stop="toggleTreeFolder(null)"
                                        >
                                            <mt-icon
                                                :name="
                                                    isTreeFolderExpanded(null)
                                                        ? 'regular-chevron-down-xs'
                                                        : 'regular-chevron-right-xs'
                                                "
                                                size="12px"
                                            />
                                        </mt-button>

                                        <button
                                            class="ct-media-index__folder-tree-content"
                                            type="button"
                                            @click="updateRoute(null)"
                                        >
                                            <span class="ct-media-index__folder-tree-icon is--root">
                                                <mt-icon name="regular-image" size="16px" />
                                            </span>
                                            <span>{{ $t('ct-media.index.titleAllMedia') }}</span>
                                            <small>{{ navigationMediaTotal }}</small>
                                        </button>

                                        <div class="ct-media-index__folder-tree-actions">
                                            <mt-button
                                                v-if="acl.can('media.creator')"
                                                variant="tertiary"
                                                size="small"
                                                square
                                                :aria-label="$t('ct-media.index.buttonCreateFolder')"
                                                @click.stop="onTreeAddFolder(null)"
                                            >
                                                <mt-icon name="regular-plus-circle-s" size="14px" />
                                            </mt-button>
                                        </div>
                                    </div>

                                    <div
                                        v-for="entry in visibleFolderEntries"
                                        :key="entry.folder.id"
                                        class="ct-media-index__folder-tree-row"
                                        :class="{ 'is--active': entry.folder.id === routeFolderId }"
                                        :style="{ '--media-folder-depth': entry.depth }"
                                        role="treeitem"
                                        :aria-level="entry.depth + 1"
                                        :aria-expanded="
                                            entry.hasChildren ? isTreeFolderExpanded(entry.folder.id) : undefined
                                        "
                                    >
                                        <mt-button
                                            v-if="entry.hasChildren"
                                            class="ct-media-index__folder-tree-expand"
                                            variant="tertiary"
                                            size="small"
                                            square
                                            :aria-label="$t('ct-media.index.labelToggleFolder')"
                                            @click.stop="toggleTreeFolder(entry.folder.id)"
                                        >
                                            <mt-icon
                                                :name="
                                                    isTreeFolderExpanded(entry.folder.id)
                                                        ? 'regular-chevron-down-xs'
                                                        : 'regular-chevron-right-xs'
                                                "
                                                size="12px"
                                            />
                                        </mt-button>
                                        <span v-else class="ct-media-index__folder-tree-expand-placeholder"></span>

                                        <mt-text-field
                                            v-if="editingFolderId === entry.folder.id"
                                            v-autofocus
                                            class="ct-media-index__folder-tree-name-field"
                                            size="small"
                                            :model-value="editingFolderName"
                                            @update:model-value="editingFolderName = $event"
                                            @blur="onTreeRenameSubmit(entry.folder)"
                                            @keydown.enter="onTreeRenameSubmit(entry.folder)"
                                            @keydown.esc="cancelTreeRename"
                                        />
                                        <button
                                            v-else
                                            class="ct-media-index__folder-tree-content"
                                            type="button"
                                            :title="entry.folder.name"
                                            @click="updateRoute(entry.folder.id)"
                                        >
                                            <span class="ct-media-index__folder-tree-icon">
                                                <mt-icon
                                                    :name="
                                                        entry.folder.id === routeFolderId
                                                            ? 'regular-folder-open'
                                                            : 'regular-folder'
                                                    "
                                                    size="16px"
                                                />
                                            </span>
                                            <span>{{ entry.folder.name || $t('ct-media.index.labelUntitledFolder') }}</span>
                                        </button>

                                        <div
                                            v-if="editingFolderId !== entry.folder.id"
                                            class="ct-media-index__folder-tree-actions"
                                        >
                                            <mt-dropdown-menu-root>
                                                <mt-dropdown-menu-trigger as-child>
                                                    <mt-button
                                                        variant="tertiary"
                                                        size="small"
                                                        square
                                                        :aria-label="$t('ct-media.index.labelFolderActions')"
                                                    >
                                                        <mt-icon name="ellipsis-h" size="16px" />
                                                    </mt-button>
                                                </mt-dropdown-menu-trigger>
                                                <mt-dropdown-menu-portal>
                                                    <mt-action-menu>
                                                        <mt-action-menu-item
                                                            v-if="acl.can('media.creator')"
                                                            @select="onTreeAddFolder(entry.folder.id)"
                                                        >
                                                            {{ $t('ct-media.index.buttonCreateFolder') }}
                                                        </mt-action-menu-item>
                                                        <mt-action-menu-item
                                                            v-if="acl.can('media.editor')"
                                                            @select="startTreeRename(entry.folder)"
                                                        >
                                                            {{ $t('global.default.edit') }}
                                                        </mt-action-menu-item>
                                                        <mt-action-menu-item
                                                            v-if="acl.can('media.deleter')"
                                                            variant="critical"
                                                            @select="treeFolderPendingDelete = entry.folder"
                                                        >
                                                            {{ $t('global.default.delete') }}
                                                        </mt-action-menu-item>
                                                    </mt-action-menu>
                                                </mt-dropdown-menu-portal>
                                            </mt-dropdown-menu-root>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </ct-block>

                        <ct-block name="sw_media_index_listing_grid">
                            <main class="ct-media-index__workspace">
                                <ct-block name="sw_media_index_workspace_header">
                                    <header class="ct-media-index__workspace-header">
                                        <nav
                                            class="ct-media-index__workspace-breadcrumb"
                                            :aria-label="$t('ct-media.index.labelBreadcrumb')"
                                        >
                                            <mt-button variant="tertiary" size="small" @click="updateRoute(null)">
                                                {{ $t('ct-media.index.rootFolderName') }}
                                            </mt-button>

                                            <mt-icon name="regular-chevron-right-xs" size="12px" />
                                            <span>{{ currentFolderTitle }}</span>
                                        </nav>

                                        <div class="ct-media-index__workspace-heading-row">
                                            <div class="ct-media-index__workspace-heading">
                                                <h1>{{ currentFolderTitle }}</h1>
                                                <p>
                                                    {{
                                                        $t('ct-media.index.labelFolderSummary', {
                                                            folders: folderCount,
                                                            files: mediaCount,
                                                        })
                                                    }}
                                                </p>
                                            </div>

                                            <div class="ct-media-index__workspace-actions">
                                                <mt-select
                                                    v-model="mediaType"
                                                    class="ct-media-index__type-filter"
                                                    name="mediaType"
                                                    small
                                                    :label="$t('ct-media.filter.labelType')"
                                                    :options="mediaTypeOptions"
                                                    :disabled="libraryIsLoading"
                                                    hide-clearable-button
                                                    @update:model-value="onMediaTypeChanged"
                                                />
                                                <ct-media-display-options
                                                    inline
                                                    :presentation="presentation"
                                                    :sorting="sorting"
                                                    :disabled="libraryIsLoading"
                                                    @media-presentation-change="onPresentationChanged"
                                                    @media-sorting-change="onSortingChanged"
                                                />
                                            </div>
                                        </div>
                                    </header>
                                </ct-block>

                                <ct-upload-listener
                                    :upload-tag="uploadTag"
                                    @media-upload-add="onUploadsAdded"
                                    @media-upload-finish="onUploadFinished"
                                    @media-upload-fail="onUploadFailed"
                                    @media-upload-cancel="onUploadCanceled"
                                />

                                <ct-media-library
                                    ref="mediaLibrary"
                                    v-model:selection="selectedItems"
                                    class="ct-media-index__media-library"
                                    :folder-id="routeFolderId"
                                    :pending-uploads="uploads"
                                    :term="term"
                                    hide-search
                                    hide-create-folder
                                    hide-display-options
                                    hide-parent-folder
                                    show-section-headers
                                    editable
                                    @media-folder-change="updateRoute"
                                    @media-folder-changed="loadNavigationFolders"
                                    @media-term-change="onSearch"
                                />
                            </main>
                        </ct-block>

                        <ct-block name="sw_media_index_sidebar">
                            <ct-media-sidebar
                                v-if="selectedItems.length > 0"
                                :items="selectedItems"
                                :current-folder-id="routeFolderId"
                                editable
                                @media-sidebar-folder-renamed="updateFolder"
                                @media-sidebar-items-delete="onItemsDeleted"
                                @media-sidebar-folder-items-dissolve="onMediaFoldersDissolved"
                                @media-sidebar-items-move="reloadList"
                                @media-item-replaced="reloadList"
                                @media-item-selection-remove="onMediaUnselect"
                                @media-sidebar-close="clearSelection"
                            />
                        </ct-block>

                        <ct-media-modal-delete
                            v-if="treeFolderPendingDelete"
                            :items-to-delete="[treeFolderPendingDelete]"
                            @media-delete-modal-items-delete="onTreeFolderDeleted"
                            @media-delete-modal-close="treeFolderPendingDelete = null"
                        />

                        <ct-block name="sw_media_index_list_grid_loader">
                            <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                            <mt-loader v-if="isLoading" />
                        </ct-block>
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-media-index.scss';
const { Context, Filter } = Contena;
const { Criteria } = Contena.Data;

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

const props = defineProps({
    routeFolderId: {
        type: String,
        default: null,
    },

    fileAccept: {
        type: String,
        required: false,
        default: '*/*',
    },
});

import { ref, computed, inject, watch, onUnmounted, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const ROOT_TREE_ID = 'media-root';

const mediaLibrary = ref(null);

const repositoryFactory = inject('repositoryFactory');
const mediaService = inject('mediaService');
const acl = inject('acl');

const isLoading = ref(false);
const selectedItems = ref([]);
const uploads = ref([]);
const pendingUploadsCount = ref(0);
const term = ref(route.query?.term ?? '');
const uploadTag = ref('upload-tag-ct-media-index');
const parentFolder = ref(null);
const currentFolder = ref(null);
const navigationFolders = ref([]);
const navigationMediaTotal = ref(0);
const expandedFolderIds = ref(new Set([ROOT_TREE_ID]));
const editingFolderId = ref(null);
const editingFolderName = ref('');
const treeFolderPendingDelete = ref(null);
const pendingTreeCreateFolderId = ref(undefined);
const presentation = ref('medium-preview');
const sorting = ref({ sortBy: 'createdAt', sortDirection: 'desc' });
const mediaType = ref('all');
const mediaTypeOptions = computed(() => [
    { value: 'all', label: t('ct-media.filter.labelAll') },
    { value: 'image', label: t('ct-media.filter.labelImages') },
    { value: 'video', label: t('ct-media.filter.labelVideos') },
    { value: 'audio', label: t('ct-media.filter.labelAudio') },
    { value: 'application', label: t('ct-media.filter.labelDocuments') },
]);
const visibleFolderEntries = computed(() => {
    if (!expandedFolderIds.value.has(ROOT_TREE_ID)) {
        return [];
    }

    const foldersByParent = new Map();
    navigationFolders.value.forEach((folder) => {
        const parentId = folder.parentId ?? null;
        foldersByParent.set(parentId, [
            ...(foldersByParent.get(parentId) ?? []),
            folder,
        ]);
    });

    const result = [];
    const appendFolders = (parentId, depth) => {
        (foldersByParent.get(parentId) ?? []).forEach((folder) => {
            const children = foldersByParent.get(folder.id) ?? [];
            result.push({
                folder,
                depth,
                hasChildren: children.length > 0,
            });

            if (expandedFolderIds.value.has(folder.id)) {
                appendFolders(folder.id, depth + 1);
            }
        });
    };

    appendFolders(null, 1);
    return result;
});

const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const rootFolder = computed(() => {
    const root = mediaFolderRepository.value.create(Context.api);
    root.name = t('ct-media.index.rootFolderName');
    root.id = null;
    return root;
});
const assetFilter = computed(() => {
    return Filter.getByName('asset');
});

const createdComponent = () => {
    void updateFolder();
    void loadNavigationFolders();
};
const updateFolder = async () => {
    if (!props.routeFolderId) {
        currentFolder.value = rootFolder.value;
        parentFolder.value = null;
    } else {
        currentFolder.value = await mediaFolderRepository.value.get(props.routeFolderId, Context.api);

        if (currentFolder.value && currentFolder.value.parentId) {
            parentFolder.value = await mediaFolderRepository.value.get(currentFolder.value.parentId, Context.api);
        } else {
            parentFolder.value = rootFolder.value;
        }
    }
};
const loadNavigationFolders = async () => {
    const folderCriteria = new Criteria(1, 500).addSorting(Criteria.sort('name', 'asc'));
    const mediaCriteria = new Criteria(1, 1);

    const [
        folders,
        media,
    ] = await Promise.all([
        mediaFolderRepository.value.search(folderCriteria, Context.api),
        mediaRepository.value.search(mediaCriteria, Context.api),
    ]);

    navigationFolders.value = folders ?? [];
    navigationMediaTotal.value = media?.total ?? media?.length ?? 0;
    expandTreeToFolder(props.routeFolderId);
};
const destroyedComponent = () => {};
const onUploadsAdded = async ({ data } = {}) => {
    if (Array.isArray(data) && data.length > 0) {
        pendingUploadsCount.value += data.length;
    }

    await mediaService.runUploads(uploadTag.value);
};
const onUploadFinished = ({ targetId, originalTargetId } = {}) => {
    if (targetId || originalTargetId) {
        uploads.value = uploads.value.filter((upload) => {
            return upload.id !== targetId && upload.id !== originalTargetId;
        });
    }

    decrementPendingUploads();
};
const onUploadFailed = ({ targetId } = {}) => {
    if (targetId) {
        uploads.value = uploads.value.filter((upload) => {
            return targetId !== upload.id;
        });
    }

    decrementPendingUploads();
};
const onUploadCanceled = ({ data } = {}) => {
    if (Array.isArray(data) && data.length > 0) {
        pendingUploadsCount.value = Math.max(0, pendingUploadsCount.value - data.length);
    }

    if (pendingUploadsCount.value === 0) {
        reloadList();
    }
};
const onChangeLanguage = () => {
    clearSelection();
};
const onSearch = (value) => {
    term.value = value;
    clearSelection();
};
const onItemsDeleted = (ids) => {
    onMediaFoldersDissolved(ids.folderIds);
};
const onMediaFoldersDissolved = (ids) => {
    clearSelection();
    if (ids.includes(props.routeFolderId)) {
        let routeId = null;
        if (parentFolder.value) {
            routeId = parentFolder.value.id;
        }

        void router.push({
            name: 'ct.media.index',
            params: {
                folderId: routeId,
            },
        });
        return;
    }

    reloadList();
};
const reloadList = () => {
    mediaLibrary.value.refreshList();
    void loadNavigationFolders();
};
const decrementPendingUploads = () => {
    if (pendingUploadsCount.value > 0) {
        pendingUploadsCount.value -= 1;
    }

    if (pendingUploadsCount.value === 0) {
        reloadList();
    }
};
const clearSelection = () => {
    selectedItems.value.splice(0, selectedItems.value.length);
};
const onMediaUnselect = ({ item }) => {
    const index = selectedItems.value.findIndex((selected) => {
        return selected === item;
    });

    if (index > -1) {
        selectedItems.value.splice(index, 1);
    }
};
const updateRoute = (newFolderId) => {
    term.value = route.query?.term ?? term.value ?? '';
    void router.push({
        name: 'ct.media.index',
        params: {
            folderId: newFolderId,
        },
    });
};
const createFolder = () => {
    mediaLibrary.value?.createFolder();
};
const isTreeFolderExpanded = (folderId) => {
    return expandedFolderIds.value.has(folderId ?? ROOT_TREE_ID);
};
const toggleTreeFolder = (folderId) => {
    const id = folderId ?? ROOT_TREE_ID;
    const next = new Set(expandedFolderIds.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expandedFolderIds.value = next;
};
const expandTreeToFolder = (folderId) => {
    if (!folderId) {
        return;
    }

    const byId = new Map(
        navigationFolders.value.map((folder) => [
            folder.id,
            folder,
        ]),
    );
    const next = new Set(expandedFolderIds.value);
    next.add(ROOT_TREE_ID);

    let folder = byId.get(folderId);
    while (folder) {
        next.add(folder.id);
        folder = folder.parentId ? byId.get(folder.parentId) : null;
    }

    expandedFolderIds.value = next;
};
const onTreeAddFolder = async (folderId) => {
    pendingTreeCreateFolderId.value = folderId;
    if ((props.routeFolderId ?? null) !== folderId) {
        updateRoute(folderId);
        return;
    }

    if (mediaLibrary.value?.isLoading) {
        return;
    }

    await nextTick();
    createFolder();
    pendingTreeCreateFolderId.value = undefined;
};
const startTreeRename = (folder) => {
    editingFolderId.value = folder.id;
    editingFolderName.value = folder.name ?? '';
};
const cancelTreeRename = () => {
    editingFolderId.value = null;
    editingFolderName.value = '';
};
const onTreeRenameSubmit = async (folder) => {
    if (editingFolderId.value !== folder.id) {
        return;
    }

    const nextName = editingFolderName.value.trim();
    if (!nextName || nextName === folder.name) {
        cancelTreeRename();
        return;
    }

    folder.name = nextName;
    await mediaFolderRepository.value.save(folder, Context.api);
    cancelTreeRename();
    await Promise.all([
        loadNavigationFolders(),
        updateFolder(),
    ]);
    mediaLibrary.value?.refreshList();
};
const onTreeFolderDeleted = (ids) => {
    treeFolderPendingDelete.value = null;
    onMediaFoldersDissolved(ids.folderIds ?? []);
    void loadNavigationFolders();
};
const onPresentationChanged = (value) => {
    presentation.value = value;
    mediaLibrary.value?.setPresentation(value);
};
const onSortingChanged = (value) => {
    sorting.value = value;
    mediaLibrary.value?.setSorting(value);
};
const onMediaTypeChanged = (value) => {
    const nextMediaType = value ?? 'all';
    mediaType.value = nextMediaType;
    mediaLibrary.value?.setMediaType(nextMediaType);
};

watch(
    () => props.routeFolderId,
    () => {
        // Adopt the term from the new route query (e.g. when the user clicks a
        // global search-bar suggestion that points at a different folder) instead
        // of unconditionally clearing it.
        term.value = route.query?.term ?? '';
        clearSelection();
        void updateFolder();
        expandTreeToFolder(props.routeFolderId);

        if (pendingTreeCreateFolderId.value === (props.routeFolderId ?? null)) {
            void nextTick(() => {
                void onTreeAddFolder(pendingTreeCreateFolderId.value);
            });
        }
    },
);
watch(
    () => route.query.term,
    (value) => {
        // When the route changes only in its `term` query (same folder, e.g. the
        // user clicks a media search suggestion while already on `ct.media.index`),
        // the `routeFolderId` watcher does not fire — sync the term explicitly so
        // the media library reloads with the new search.
        const next = value ?? '';
        if (term.value === next) {
            return;
        }

        term.value = next;
        clearSelection();
    },
);

createdComponent();

onUnmounted(() => {
    destroyedComponent();
});

swDefinePublic({
    repositoryFactory,
    mediaService,
    acl,
    isLoading,
    selectedItems,
    uploads,
    pendingUploadsCount,
    term,
    uploadTag,
    parentFolder,
    currentFolder,
    navigationFolders,
    navigationMediaTotal,
    expandedFolderIds,
    editingFolderId,
    editingFolderName,
    treeFolderPendingDelete,
    pendingTreeCreateFolderId,
    presentation,
    sorting,
    mediaType,
    mediaTypeOptions,
    visibleFolderEntries,
    mediaFolderRepository,
    mediaRepository,
    rootFolder,
    assetFilter,
    createdComponent,
    updateFolder,
    loadNavigationFolders,
    destroyedComponent,
    onUploadsAdded,
    onUploadFinished,
    onUploadFailed,
    onUploadCanceled,
    onChangeLanguage,
    onSearch,
    onItemsDeleted,
    onMediaFoldersDissolved,
    reloadList,
    decrementPendingUploads,
    clearSelection,
    onMediaUnselect,
    updateRoute,
    createFolder,
    isTreeFolderExpanded,
    toggleTreeFolder,
    expandTreeToFolder,
    onTreeAddFolder,
    startTreeRename,
    cancelTreeRename,
    onTreeRenameSubmit,
    onTreeFolderDeleted,
    onPresentationChanged,
    onSortingChanged,
    onMediaTypeChanged,
});

const folderEntries = computed(() => {
    if (!props.routeFolderId) {
        return mediaLibrary.value?.subFolders ?? navigationFolders.value;
    }

    return navigationFolders.value;
});
const folderCount = computed(() => mediaLibrary.value?.folderTotal ?? 0);
const mediaCount = computed(() => mediaLibrary.value?.itemTotal ?? 0);
const libraryIsLoading = computed(() => mediaLibrary.value?.isLoading ?? false);

watch(libraryIsLoading, (isLoading) => {
    if (
        !isLoading &&
        pendingTreeCreateFolderId.value !== undefined &&
        pendingTreeCreateFolderId.value === (props.routeFolderId ?? null)
    ) {
        void onTreeAddFolder.value(pendingTreeCreateFolderId.value);
    }
});

const currentFolderTitle = computed(() => {
    if (!props.routeFolderId) {
        return t('ct-media.index.titleAllMedia');
    }

    return currentFolder.value?.name ?? t('ct-media.index.labelUntitledFolder');
});

defineExpose({
    folderEntries,
    folderCount,
    mediaCount,
    libraryIsLoading,
    currentFolderTitle,
    repositoryFactory,
    mediaService,
    acl,
    isLoading,
    selectedItems,
    uploads,
    pendingUploadsCount,
    term,
    uploadTag,
    parentFolder,
    currentFolder,
    navigationFolders,
    navigationMediaTotal,
    expandedFolderIds,
    editingFolderId,
    editingFolderName,
    treeFolderPendingDelete,
    pendingTreeCreateFolderId,
    presentation,
    sorting,
    mediaType,
    mediaTypeOptions,
    visibleFolderEntries,
    mediaFolderRepository,
    mediaRepository,
    rootFolder,
    assetFilter,
    createdComponent,
    updateFolder,
    loadNavigationFolders,
    destroyedComponent,
    onUploadsAdded,
    onUploadFinished,
    onUploadFailed,
    onUploadCanceled,
    onChangeLanguage,
    onSearch,
    onItemsDeleted,
    onMediaFoldersDissolved,
    reloadList,
    decrementPendingUploads,
    clearSelection,
    onMediaUnselect,
    updateRoute,
    createFolder,
    isTreeFolderExpanded,
    toggleTreeFolder,
    expandTreeToFolder,
    onTreeAddFolder,
    startTreeRename,
    cancelTreeRename,
    onTreeRenameSubmit,
    onTreeFolderDeleted,
    onPresentationChanged,
    onSortingChanged,
    onMediaTypeChanged,
});
</script>
