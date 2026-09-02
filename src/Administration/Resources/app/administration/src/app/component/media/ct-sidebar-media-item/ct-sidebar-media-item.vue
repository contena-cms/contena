<template>
    <ct-block name="ct_sidebar_media_item">
        <ct-sidebar-item
            ref="sidebarItem"
            class="ct-sidebar-media-item"
            icon="regular-image"
            :title="$t('global.ct-sidebar-media-item.title')"
            :disabled="disabled"
        >
            <ct-block name="ct_sidebar_media_item_content">
                <div class="ct-sidebar-media-item__content">
                    <ct-block name="ct_sidebar_media_item_search_field">
                        <mt-search v-model="term" size="small" @update:model-value="onSearchTermChange" />
                    </ct-block>

                    <ct-block name="ct_sidebar_media_item_folder_navigation">
                        <ct-media-breadcrumbs v-if="!term.length" v-model:current-folder-id="mediaFolderId" small />
                    </ct-block>

                    <ct-block name="ct_sidebar_media_item_media_item_list">
                        <ct-block name="ct_sidebar_media_item_media_item_list_folder_item">
                            <template v-if="!term.length">
                                <ct-media-folder-item
                                    v-for="folder in subFolders"
                                    :key="folder.id"
                                    :item="folder"
                                    :show-selection-indicator="false"
                                    :show-context-menu-button="true"
                                    :selected="false"
                                    :is-list="true"
                                    @media-item-click="onNavigateToFolder(folder.id)"
                                    @media-folder-delete="handleFolderGridItemDelete"
                                />
                            </template>
                        </ct-block>

                        <ct-block name="ct_sidebar_media_item_media_item_list_media_item">
                            <ct-media-media-item
                                v-for="mediaItem in mediaItems"
                                :key="mediaItem.id"
                                v-draggable="{ dragGroup: 'media', data: { mediaItem } }"
                                :item="mediaItem"
                                :show-selection-indicator="false"
                                :show-context-menu-button="true"
                                :selected="false"
                                :is-list="true"
                                @media-item-delete="handleMediaGridItemDelete"
                            >
                                <ct-block name="ct_sidebar_media_item_context_items">
                                    <slot name="context-menu-items" :media-item="mediaItem"></slot>
                                </ct-block>
                            </ct-media-media-item>
                        </ct-block>

                        <ct-block name="ct_sidebar_media_item_load_more_button">
                            <mt-button
                                v-if="showMore"
                                size="small"
                                block
                                class="ct-sidebar-media-item__load-more-button"
                                variant="secondary"
                                @click="onLoadMore"
                            >
                                {{ $t('global.ct-sidebar-media-item.labelLoadMore') }}
                            </mt-button>
                        </ct-block>
                    </ct-block>

                    <ct-block name="ct_sidebar_media_item_loader">
                        <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                        <mt-loader v-if="isLoading" />
                    </ct-block>
                </div>
            </ct-block>
        </ct-sidebar-item>
    </ct-block>
</template>

<script setup>
import './ct-sidebar-media-item.scss';
const { Context } = Contena;
const { Criteria } = Contena.Data;
const { debounce } = Contena.Utils;

const props = defineProps({
    initialFolderId: {
        type: String,
        required: false,
        default: null,
    },
    isParentLoading: {
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

import { ref, computed, inject, watch } from 'vue';

const sidebarItem = ref(null);

const repositoryFactory = inject('repositoryFactory');

const isLoading = ref(true);
const mediaFolderId = ref(props.initialFolderId);
const mediaItems = ref([]);
const subFolders = ref([]);
const page = ref(1);
const limit = ref(25);
const total = ref(0);
const term = ref('');

const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const showMore = computed(() => {
    return itemsLoaded.value < total.value;
});
const itemsLoaded = computed(() => {
    return mediaItems.value.length;
});

const createdComponent = () => {
    initializeContent();
};
const onSearchTermChange = () => {
    page.value = 1;
    debouncedSearch();
};
const debouncedSearch = debounce(() => {
    void getList();
}, 400);
function initializeContent() {
    if (props.disabled) {
        return;
    }
    page.value = 1;
    term.value = '';
    mediaItems.value = [];
    void getSubFolders();
    void getList();
}
async function getSubFolders() {
    const criteria = new Criteria(1, 50);
    criteria.addFilter(Criteria.equals('parentId', mediaFolderId.value));
    const folder = await mediaFolderRepository.value.search(criteria, Context.api);
    subFolders.value = folder;
    return folder;
}
const handleFolderGridItemDelete = () => {
    void getSubFolders();
};
const handleMediaGridItemDelete = () => {
    const pages = page.value;
    page.value = 1;
    void getList().then(() => {
        while (page.value < pages) {
            page.value += 1;
            void extendList();
        }
    });
};
const onLoadMore = () => {
    page.value += 1;
    void extendList();
};
async function extendList() {
    const criteria = getListingCriteria();
    const searchResult = await mediaRepository.value.search(criteria, Context.api);
    mediaItems.value = mediaItems.value.concat(searchResult);
    return mediaItems.value;
}
async function getList() {
    if (props.isParentLoading === true) {
        return null;
    }
    isLoading.value = true;
    const criteria = getListingCriteria();
    mediaItems.value = await mediaRepository.value.search(criteria, Context.api);
    total.value = mediaItems.value.total;
    isLoading.value = false;
    return mediaItems.value;
}
function getListingCriteria() {
    const criteria = new Criteria(page.value, limit.value);
    if (!term.value.length) {
        criteria.addFilter(Criteria.equals('mediaFolderId', mediaFolderId.value));
    }
    if (term.value) {
        criteria.term = term.value;
    }
    criteria.addSorting(Criteria.sort('uploadedAt', 'DESC'));
    return criteria;
}
const openContent = () => {
    sidebarItem.value.openContent();
};
const onNavigateToFolder = (folderId) => {
    mediaFolderId.value = folderId;
};

watch(
    () => props.initialFolderId,
    () => {
        mediaFolderId.value = props.initialFolderId;
    },
);
watch(
    () => mediaFolderId.value,
    () => {
        initializeContent();
    },
);
watch(
    () => props.isParentLoading,
    () => {
        void getList();
    },
);

createdComponent();

ctDefinePublic({
    repositoryFactory,
    isLoading,
    mediaFolderId,
    mediaItems,
    subFolders,
    page,
    limit,
    total,
    term,
    mediaRepository,
    mediaFolderRepository,
    showMore,
    itemsLoaded,
    createdComponent,
    onSearchTermChange,
    debouncedSearch,
    initializeContent,
    getSubFolders,
    handleFolderGridItemDelete,
    handleMediaGridItemDelete,
    onLoadMore,
    extendList,
    getList,
    getListingCriteria,
    openContent,
    onNavigateToFolder,
});

defineExpose({
    repositoryFactory,
    isLoading,
    mediaFolderId,
    mediaItems,
    subFolders,
    page,
    limit,
    total,
    term,
    mediaRepository,
    mediaFolderRepository,
    showMore,
    itemsLoaded,
    createdComponent,
    onSearchTermChange,
    debouncedSearch,
    initializeContent,
    getSubFolders,
    handleFolderGridItemDelete,
    handleMediaGridItemDelete,
    onLoadMore,
    extendList,
    getList,
    getListingCriteria,
    openContent,
    onNavigateToFolder,
});
</script>
