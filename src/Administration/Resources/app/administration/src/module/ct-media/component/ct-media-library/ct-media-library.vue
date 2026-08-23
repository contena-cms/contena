<template>
    <ct-block name="sw_media_library">
        <div class="ct-media-library" :class="{ 'ct-media-library--without-toolbar': !showToolbar }">
            <ct-block name="sw_media_library_display_options">
                <div
                    v-if="showToolbar"
                    class="ct-media-library__options-container"
                    :class="{
                        'ct-media-library__options-container--without-search': hideSearch,
                        'ct-media-library__options-container--without-create-folder': hideCreateFolder,
                        'ct-media-library__options-container--inline': inlineDisplayOptions,
                    }"
                >
                    <ct-block name="sw_media_library_search">
                        <mt-text-field
                            v-if="!hideSearch"
                            class="ct-media-library__search"
                            name="mediaSearch"
                            size="small"
                            :model-value="term"
                            :placeholder="translate('ct-media.general.placeholderSearchBar')"
                            @update:model-value="onTermChanged"
                        >
                            <template #prefix>
                                <mt-icon name="regular-search" />
                            </template>
                        </mt-text-field>
                    </ct-block>

                    <ct-block name="sw_media_library_type_filter">
                        <mt-select
                            v-if="showTypeFilter"
                            v-model="mediaType"
                            class="ct-media-library__type-filter"
                            name="mediaType"
                            small
                            :label="translate('ct-media.filter.labelType')"
                            :options="mediaTypeOptions"
                            :disabled="disabled"
                            hide-clearable-button
                        />
                    </ct-block>

                    <ct-media-display-options
                        v-if="!hideDisplayOptions"
                        class="ct-media-library__display-options"
                        :presentation="presentation"
                        :sorting="sorting"
                        :hide-presentation="compact"
                        :disabled="disabled"
                        :inline="inlineDisplayOptions"
                        @media-presentation-change="presentation = $event"
                        @media-sorting-change="sorting = $event"
                    />

                    <ct-block name="sw_media_index_create_folder">
                        <mt-button
                            v-if="!hideCreateFolder && (editable || allowCreateFolder)"
                            v-tooltip="{
                                message: translate('ct-privileges.tooltip.warning'),
                                disabled: acl.can('media.creator'),
                                showOnDisabledElements: true,
                            }"
                            :disabled="!acl.can('media.creator') || disabled"
                            class="ct-media-index__create-folder-action"
                            size="small"
                            variant="secondary"
                            @click="createFolder"
                        >
                            {{ translate('ct-media.index.buttonCreateFolder') }}
                        </mt-button>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_media_library_scroll_container">
                <div ref="scrollContainer" class="ct-media-library__scroll-container">
                    <div class="ct-media-library__scroll-content">
                        <ct-block name="sw_media_library_folder_section">
                            <section
                                v-if="(parentFolder && !hideParentFolder) || subFolders.length > 0"
                                class="ct-media-library__section ct-media-library__folder-section"
                            >
                                <header v-if="showSectionHeaders" class="ct-media-library__section-header">
                                    <h2>{{ translate('ct-media.index.labelFolders') }}</h2>
                                    <span>{{ folderTotal }}</span>
                                </header>

                                <ct-media-grid
                                    ref="mediaGrid"
                                    class="ct-media-library__folder-grid"
                                    presentation="list-preview"
                                    @media-grid-selection-clear="clearSelection"
                                >
                                    <ct-block name="sw_media_library_back_to_parent_item">
                                        <ct-media-folder-item
                                            v-if="
                                                parentFolder &&
                                                !hideParentFolder &&
                                                (!isLoading || selectableItems.length > 0)
                                            "
                                            :allow-edit="acl.can('media.editor')"
                                            :allow-delete="acl.can('media.deleter')"
                                            :disabled="disabled"
                                            class="ct-media-library__parent-folder"
                                            :item="parentFolder"
                                            :show-selection-indicator="false"
                                            :show-context-menu-button="false"
                                            :allow-multi-select="allowMultiSelect"
                                            is-list
                                            is-parent
                                            @media-item-click="goToParentFolder"
                                        />
                                    </ct-block>

                                    <ct-block name="sw_media_library_folder_item_list">
                                        <ct-media-entity-mapper
                                            v-for="(folder, index) in subFolders"
                                            :key="'media_folder_' + folder.id"
                                            :class="`ct-media-grid-item__folder--${index}`"
                                            :item="folder"
                                            :disabled="disabled"
                                            :allow-edit="acl.can('media.editor')"
                                            :allow-delete="acl.can('media.deleter')"
                                            :selected="showItemSelected(folder)"
                                            :show-selection-indicator="isListSelect"
                                            :show-context-menu-button="editable"
                                            is-list
                                            :editable="editable"
                                            :allow-multi-select="allowMultiSelect"
                                            @media-folder-delete="refreshFolders"
                                            @media-folder-remove="removeNewFolder"
                                            @media-folder-dissolve="refreshFolders"
                                            @media-folder-move="refreshFolders"
                                            @media-folder-changed="refreshFolders"
                                            @media-item-click="handleMediaItemClicked"
                                            @media-item-selection-add="handleMediaGridItemSelected"
                                            @media-item-selection-remove="handleMediaGridItemUnselected"
                                            @media-item-play="handleMediaItemClicked"
                                        />
                                    </ct-block>
                                </ct-media-grid>
                            </section>
                        </ct-block>

                        <ct-block name="sw_media_library_media_grid">
                            <section
                                v-if="
                                    pendingUploads.length > 0 ||
                                    items.length > 0 ||
                                    isLoading ||
                                    showLoadMoreButton ||
                                    showLoadAllButton
                                "
                                class="ct-media-library__section ct-media-library__media-section"
                            >
                                <header v-if="showSectionHeaders" class="ct-media-library__section-header">
                                    <h2>{{ translate('ct-media.index.labelMediaFiles') }}</h2>
                                    <span>{{ itemTotal }}</span>
                                </header>

                                <ct-media-grid
                                    class="ct-media-library__media-grid"
                                    :presentation="gridPresentation"
                                    @media-grid-selection-clear="clearSelection"
                                >
                                    <ct-block name="sw_media_library_media_item_list">
                                        <ct-media-entity-mapper
                                            v-for="(gridItem, index) in mediaItems"
                                            :key="gridItem.getEntityName() + '_' + gridItem.id"
                                            :class="`ct-media-grid-item__item--${index}`"
                                            :item="gridItem"
                                            :disabled="disabled"
                                            :allow-edit="acl.can('media.editor')"
                                            :allow-delete="acl.can('media.deleter')"
                                            :selected="showItemSelected(gridItem)"
                                            :show-selection-indicator="isListSelect"
                                            :show-context-menu-button="editable"
                                            show-grid-metadata
                                            :is-list="showItemsAsList"
                                            :editable="editable"
                                            :allow-multi-select="allowMultiSelect"
                                            @media-item-replaced="refreshList"
                                            @media-item-delete="refreshList"
                                            @media-item-click="handleMediaItemClicked"
                                            @media-item-selection-add="handleMediaGridItemSelected"
                                            @media-item-selection-remove="handleMediaGridItemUnselected"
                                            @media-item-play="handleMediaItemClicked"
                                        />
                                    </ct-block>

                                    <template v-if="isLoading">
                                        <ct-skeleton variant="media" />
                                        <ct-skeleton variant="media" />
                                        <ct-skeleton variant="media" />
                                        <ct-skeleton variant="media" />
                                    </template>

                                    <ct-block name="sw_media_library_load_buttons">
                                        <div
                                            v-if="showLoadMoreButton || showLoadAllButton"
                                            class="ct-media-library__load-buttons"
                                        >
                                            <ct-block name="sw_media_library_load_more_button">
                                                <mt-button
                                                    v-if="showLoadMoreButton"
                                                    class="ct-media-library__load-more-button"
                                                    variant="secondary"
                                                    @click="loadNextItems"
                                                >
                                                    {{ translate('ct-media.mediaLibrary.labelButtonLoadMore') }}
                                                </mt-button>
                                            </ct-block>

                                            <ct-block name="sw_media_library_load_all_button">
                                                <mt-button
                                                    v-if="showLoadAllButton"
                                                    class="ct-media-library__load-all-button"
                                                    variant="secondary"
                                                    @click="loadAll"
                                                >
                                                    {{ translate('ct-media.mediaLibrary.labelButtonLoadAll') }}
                                                </mt-button>
                                            </ct-block>
                                        </div>
                                    </ct-block>
                                </ct-media-grid>
                            </section>
                        </ct-block>

                        <ct-block name="sw_media_library_empty_state">
                            <mt-empty-state
                                v-if="shouldDisplayEmptyState"
                                class="ct-media-library__empty-state"
                                :centered="true"
                                icon="regular-image"
                                :headline="translate('ct-media.mediaLibrary.titleEmptyState')"
                                :description="translate('ct-media.mediaLibrary.descriptionEmptyState')"
                            />
                        </ct-block>
                    </div>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-library.scss';
const { Context } = Contena;
const { Criteria } = Contena.Data;
const getDefaultMediaSorting = () => {
    return { sortBy: 'createdAt', sortDirection: 'desc' };
};

const props = defineProps({
    selection: {
        type: Array,
        required: true,
    },

    folderId: {
        type: String,
        required: false,
        default: null,
    },

    pendingUploads: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },

    limit: {
        type: Number,
        required: false,
        default: 100,
        validValues: [
            1,
            5,
            25,
            50,
            100,
            500,
        ],
        validator(value) {
            return [
                1,
                5,
                25,
                50,
                100,
                500,
            ].includes(value);
        },
    },

    term: {
        type: String,
        required: false,
        default: '',
    },

    compact: {
        type: Boolean,
        required: false,
        default: false,
    },

    hideSearch: {
        type: Boolean,
        required: false,
        default: false,
    },

    hideCreateFolder: {
        type: Boolean,
        required: false,
        default: false,
    },

    hideParentFolder: {
        type: Boolean,
        required: false,
        default: false,
    },

    hideDisplayOptions: {
        type: Boolean,
        required: false,
        default: false,
    },

    showSectionHeaders: {
        type: Boolean,
        required: false,
        default: false,
    },

    showTypeFilter: {
        type: Boolean,
        required: false,
        default: false,
    },

    inlineDisplayOptions: {
        type: Boolean,
        required: false,
        default: false,
    },

    editable: {
        type: Boolean,
        required: false,
        default: false,
    },

    allowMultiSelect: {
        type: Boolean,
        required: false,
        default: true,
    },

    allowCreateFolder: {
        type: Boolean,
        required: false,
        default: false,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'update:selection',
    'media-folder-change',
    'media-folder-changed',
    'media-term-change',
]);

import { ref, computed, inject, watch, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import useMediaGridListener from 'src/app/composables/use-media-grid-listener';

const { t } = useI18n();

const translate = t;
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');
const searchRankingService = inject('searchRankingService');

const isLoading = ref(false);
const pageItem = ref(1);
const pageFolder = ref(1);
const itemLoaderDone = ref(false);
const folderLoaderDone = ref(false);
const items = ref([]);
const subFolders = ref([]);
const itemTotal = ref(0);
const folderTotal = ref(0);
const currentFolder = ref(null);
const parentFolder = ref(null);
const presentation = ref('medium-preview');
const sorting = ref(getDefaultMediaSorting());
const mediaType = ref('all');
const mediaTypeOptions = computed(() => {
    return [
        { value: 'all', label: t('ct-media.filter.labelAll') },
        { value: 'image', label: t('ct-media.filter.labelImages') },
        { value: 'video', label: t('ct-media.filter.labelVideos') },
        { value: 'audio', label: t('ct-media.filter.labelAudio') },
        { value: 'application', label: t('ct-media.filter.labelDocuments') },
    ];
});
const showToolbar = computed(() => {
    return !props.hideSearch || props.showTypeFilter || !props.hideDisplayOptions || !props.hideCreateFolder;
});

const shouldDisplayEmptyState = computed(() => {
    return (
        !isLoading.value &&
        (selectableItems.value.length === 0 || (isValidTerm(props.term) && selectableItems.value.length === 0))
    );
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const mediaFolderConfigurationRepository = computed(() => {
    return repositoryFactory.create('media_folder_configuration');
});
const selectableItems = computed(() => {
    return [
        ...subFolders.value,
        ...props.pendingUploads,
        ...items.value,
    ];
});
const mediaItems = computed(() => {
    return [
        ...props.pendingUploads,
        ...items.value,
    ];
});
const rootFolder = computed(() => {
    const root = mediaFolderRepository.value.create(Context.api);
    root.id = '';
    root.name = t('ct-media.index.rootFolderName');

    return root;
});
const gridPresentation = computed(() => {
    if (props.compact) {
        return 'list-preview';
    }

    return presentation.value;
});
const showItemsAsList = computed(() => {
    return gridPresentation.value === 'list-preview';
});
const allLoaded = computed(() => {
    return itemLoaderDone.value && folderLoaderDone.value;
});
const loadMoreLoadsEverything = computed(() => {
    const foldersComplete =
        folderLoaderDone.value || (folderTotal.value > 0 && folderTotal.value - subFolders.value.length <= props.limit);
    const itemsComplete =
        itemLoaderDone.value || (itemTotal.value > 0 && itemTotal.value - items.value.length <= props.limit);

    return foldersComplete && itemsComplete;
});
const showLoadMoreButton = computed(() => {
    if (isLoading.value || shouldDisplayEmptyState.value || allLoaded.value) {
        return false;
    }

    // when a single "load more" would already load everything, only the "load all" button is shown
    return !loadMoreLoadsEverything.value;
});
const showLoadAllButton = computed(() => {
    if (isLoading.value || shouldDisplayEmptyState.value) {
        return false;
    }

    return !allLoaded.value;
});
/* eslint-disable vue/no-side-effects-in-computed-properties -- Criteria is a local mutable query object. */
const nextMediaCriteria = computed(() => {
    // always search without folderId criteria --> search for all items
    const criteria = new Criteria(pageItem.value, props.limit);

    criteria.addSorting(Criteria.sort(sorting.value.sortBy, sorting.value.sortDirection)).setTerm(props.term);

    if (mediaType.value && mediaType.value !== 'all') {
        criteria.addFilter(Criteria.prefix('mimeType', `${mediaType.value}/`));
    }

    // eslint-disable-next-line no-warning-comments
    // ToDo NEXT-22186 - will be replaced by a new overview
    [
        'tags',
        'avatarUsers',
    ].forEach((association) => {
        const associationParts = association.split('.');

        criteria.addAssociation(association);

        let path = null;
        associationParts.forEach((currentPart) => {
            path = path ? `${path}.${currentPart}` : currentPart;

            criteria.getAssociation(path).setLimit(25);
        });
    });

    return criteria;
});
const nextFoldersCriteria = computed(() => {
    const criteria = new Criteria(pageFolder.value, props.limit)
        .addSorting(Criteria.sort('name', 'asc'))
        .setTerm(props.term);

    if (!props.term) {
        criteria.addFilter(Criteria.equals('parentId', props.folderId));
    }

    return criteria;
});
/* eslint-enable vue/no-side-effects-in-computed-properties */
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});

const beforeUnmountedComponent = () => {
    Contena.Utils.EventBus.off('ct-media-library-item-updated', onMediaUpdated);
};
const isValidTerm = (term) => {
    return searchRankingService.isValidTerm(term);
};
const loadNextItems = () => {
    if (isLoading.value === true) {
        return;
    }
    isLoading.value = true;
    void loadItems();
};
const loadAll = async () => {
    if (isLoading.value === true) {
        return;
    }

    // stop once everything is loaded, or when a load made no progress (e.g. a failed request)
    let loadedCount = -1;
    while (!allLoaded.value && items.value.length + subFolders.value.length !== loadedCount) {
        loadedCount = items.value.length + subFolders.value.length;
        await loadItems();
    }
};
const isLoaderDone = (criteria, data) => {
    return criteria.limit >= data.total || criteria.limit > data.length;
};
const loadItems = async () => {
    isLoading.value = true;
    const [
        nextFoldersValue,
        nextMediaValue,
    ] = await Promise.allSettled([
        nextFolders(),
        nextMedia(),
    ]);
    if (nextMediaValue.status === 'fulfilled') {
        items.value.push(...nextMediaValue.value);
    } else {
        itemLoaderDone.value = false;
    }
    if (nextFoldersValue.status === 'fulfilled') {
        subFolders.value.push(...nextFoldersValue.value);
    } else {
        folderLoaderDone.value = false;
    }
    isLoading.value = false;
};
const nextMedia = async () => {
    if (itemLoaderDone.value) {
        return [];
    }

    let criteria = nextMediaCriteria.value;

    if (isValidTerm(props.term)) {
        const searchRankingFields = await searchRankingService.getSearchFieldsByEntity('media');

        if (!searchRankingFields || Object.keys(searchRankingFields).length < 1) {
            isLoading.value = false;
            itemLoaderDone.value = true;

            return [];
        }

        criteria = searchRankingService.buildSearchQueriesForEntity(searchRankingFields, props.term, criteria);
    }

    if (!isValidTerm(props.term)) {
        criteria.addFilter(Criteria.equals('mediaFolderId', props.folderId));
    }

    if (props.folderId != null && isValidTerm(props.term)) {
        criteria.addFilter(
            Criteria.multi('OR', [
                Criteria.equals('mediaFolderId', props.folderId),
                Criteria.contains('mediaFolder.path', props.folderId),
            ]),
        );
    }

    const media = await mediaRepository.value.search(criteria, Context.api);

    itemTotal.value = media.total ?? 0;
    itemLoaderDone.value = isLoaderDone(criteria, media);

    pageItem.value += 1;

    return media;
};
const nextFolders = async () => {
    if (folderLoaderDone.value) {
        return [];
    }

    const subFolders = await mediaFolderRepository.value.search(nextFoldersCriteria.value, Context.api);

    folderTotal.value = subFolders.total ?? 0;
    folderLoaderDone.value = isLoaderDone(nextFoldersCriteria.value, subFolders);

    pageFolder.value += 1;

    return subFolders;
};
const fetchAssociatedFolders = async () => {
    if (props.folderId === null) {
        currentFolder.value = null;
        parentFolder.value = null;
        return;
    }

    currentFolder.value = await mediaFolderRepository.value.get(props.folderId, Context.api);

    if (currentFolder.value && currentFolder.value.parentId) {
        parentFolder.value = await mediaFolderRepository.value.get(currentFolder.value.parentId, Context.api);
    } else {
        parentFolder.value = rootFolder.value;
    }
};
const goToParentFolder = () => {
    emit('media-folder-change', parentFolder.value.id || null);
};
const injectItem = (item) => {
    if (item.getEntityName() === 'media') {
        injectMedia(item);
        return;
    }

    throw new Error("Injected entity has to be of 'type media'");
};
const injectMedia = (mediaEntity) => {
    if (mediaEntity.mediaFolderId !== props.folderId) {
        return;
    }

    if (
        !items.value.some((alreadyListed) => {
            return alreadyListed.id === mediaEntity.id;
        })
    ) {
        items.value.unshift(mediaEntity);
    }
};
const createFolder = async () => {
    const newFolder = mediaFolderRepository.value.create(Context.api);
    newFolder.parentId = props.folderId;
    newFolder.name = '';

    if (props.folderId !== null) {
        newFolder.configurationId = currentFolder.value.configurationId;
        newFolder.useParentConfiguration = true;
    } else {
        const configuration = mediaFolderConfigurationRepository.value.create(Context.api);
        configuration.createThumbnails = true;
        configuration.keepProportions = true;
        configuration.thumbnailQuality = 80;

        await mediaFolderConfigurationRepository.value.save(configuration, Context.api);

        newFolder.configurationId = configuration.id;
        newFolder.useParentConfiguration = false;
    }

    subFolders.value.unshift(newFolder);
};
const removeNewFolder = () => {
    subFolders.value.shift();
};
const refreshFolders = async () => {
    await refreshList();
    emit('media-folder-changed');
};
const refreshItem = async (mediaId) => {
    const itemsIndex = items.value.findIndex((item) => item.id === mediaId);
    const selectedItemsIndex = selectedItems.value.findIndex((item) => item.id === mediaId);

    isLoading.value = true;

    try {
        const media = await mediaRepository.value.get(mediaId, Context.api);

        if (itemsIndex !== -1) {
            items.value.splice(itemsIndex, 1, media);
        }

        if (selectedItemsIndex !== -1) {
            selectedItems.value.splice(selectedItemsIndex, 1, media);
        }
    } finally {
        isLoading.value = false;
    }
};
const onMediaUpdated = (mediaId) => {
    void refreshItem(mediaId);
};
const onTermChanged = (value) => {
    emit('media-term-change', value);
};
const {
    selectedItems,
    listSelectionStartItem,
    isListSelect,
    mediaItemSelectionHandler,
    isItemSelected,
    showItemSelected,
    clearSelection,
    navigateToFolder,
    showDetails,
    handleMediaItemClicked: handleMediaItemClickedMultiple,
    handleMediaGridItemSelected: handleMediaGridItemSelectedMultiple,
    handleMediaGridItemUnselected,
} = useMediaGridListener({
    selectableItems: () => selectableItems.value,
    onFolderChange: (folderId) => emit('media-folder-change', folderId),
});
selectedItems.value = props.selection;
const handleMediaItemClicked = (event) => {
    if (!props.allowMultiSelect) {
        showDetails(event.item);
        return;
    }

    handleMediaItemClickedMultiple(event);
};
const handleMediaGridItemSelected = (event) => {
    if (!props.allowMultiSelect) {
        return;
    }

    handleMediaGridItemSelectedMultiple(event);
};
const refreshList = async () => {
    if (isLoading.value) {
        return;
    }

    subFolders.value = [];
    items.value = [];
    itemTotal.value = 0;
    folderTotal.value = 0;
    isLoading.value = true;
    clearSelection();
    await fetchAssociatedFolders();
    pageItem.value = 1;
    pageFolder.value = 1;
    itemLoaderDone.value = false;
    folderLoaderDone.value = false;
    await loadItems();
};
const createdComponent = () => {
    Contena.Utils.EventBus.on('ct-media-library-item-updated', onMediaUpdated);
    void refreshList();
};
const setPresentation = (value) => {
    presentation.value = value;
};
const setSorting = (value) => {
    sorting.value = value;
};
const setMediaType = (value) => {
    mediaType.value = value ?? 'all';
};

watch(
    () => props.selection,
    (selection) => {
        selectedItems.value = selection;
        if (listSelectionStartItem.value && !selection.includes(listSelectionStartItem.value)) {
            listSelectionStartItem.value = selection[0] ?? null;
        }
    },
);
watch(sorting, refreshList, { deep: true });
watch(mediaType, refreshList);
watch(() => props.folderId, refreshList);
watch(() => props.term, refreshList);

watch(
    () => selectedItems.value,
    () => {
        emit('update:selection', selectedItems.value);
    },
);

createdComponent();

onBeforeUnmount(() => {
    beforeUnmountedComponent();
});

swDefinePublic({
    repositoryFactory,
    acl,
    searchRankingService,
    isLoading,
    selectedItems,
    pageItem,
    pageFolder,
    itemLoaderDone,
    folderLoaderDone,
    items,
    subFolders,
    itemTotal,
    folderTotal,
    currentFolder,
    parentFolder,
    presentation,
    sorting,
    mediaType,
    mediaTypeOptions,
    showToolbar,
    shouldDisplayEmptyState,
    mediaRepository,
    mediaFolderRepository,
    mediaFolderConfigurationRepository,
    selectableItems,
    mediaItems,
    rootFolder,
    gridPresentation,
    showItemsAsList,
    allLoaded,
    loadMoreLoadsEverything,
    showLoadMoreButton,
    showLoadAllButton,
    nextMediaCriteria,
    nextFoldersCriteria,
    assetFilter,
    beforeUnmountedComponent,
    isValidTerm,
    loadNextItems,
    loadAll,
    isLoaderDone,
    loadItems,
    nextMedia,
    nextFolders,
    fetchAssociatedFolders,
    goToParentFolder,
    injectItem,
    injectMedia,
    createFolder,
    removeNewFolder,
    refreshFolders,
    refreshItem,
    listSelectionStartItem,
    isListSelect,
    mediaItemSelectionHandler,
    isItemSelected,
    showItemSelected,
    clearSelection,
    navigateToFolder,
    showDetails,
    handleMediaItemClicked,
    handleMediaGridItemSelected,
    handleMediaGridItemUnselected,
    refreshList,
    createdComponent,
    onTermChanged,
    setPresentation,
    setSorting,
    setMediaType,
});

defineExpose({
    repositoryFactory,
    acl,
    searchRankingService,
    isLoading,
    selectedItems,
    pageItem,
    pageFolder,
    itemLoaderDone,
    folderLoaderDone,
    items,
    subFolders,
    itemTotal,
    folderTotal,
    currentFolder,
    parentFolder,
    presentation,
    sorting,
    mediaType,
    mediaTypeOptions,
    showToolbar,
    shouldDisplayEmptyState,
    mediaRepository,
    mediaFolderRepository,
    mediaFolderConfigurationRepository,
    selectableItems,
    mediaItems,
    rootFolder,
    gridPresentation,
    showItemsAsList,
    allLoaded,
    loadMoreLoadsEverything,
    showLoadMoreButton,
    showLoadAllButton,
    nextMediaCriteria,
    nextFoldersCriteria,
    assetFilter,
    beforeUnmountedComponent,
    isValidTerm,
    loadNextItems,
    loadAll,
    isLoaderDone,
    loadItems,
    nextMedia,
    nextFolders,
    fetchAssociatedFolders,
    goToParentFolder,
    injectItem,
    injectMedia,
    createFolder,
    removeNewFolder,
    refreshItem,
    listSelectionStartItem,
    isListSelect,
    mediaItemSelectionHandler,
    isItemSelected,
    showItemSelected,
    clearSelection,
    navigateToFolder,
    showDetails,
    handleMediaItemClicked,
    handleMediaGridItemSelected,
    handleMediaGridItemUnselected,
    refreshList,
    createdComponent,
    onTermChanged,
    setPresentation,
    setSorting,
    setMediaType,
});
</script>
