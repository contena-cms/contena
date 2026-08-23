<template>
    <ct-block name="sw_media_folder_content">
        <div class="ct-media-folder-content">
            <ct-block name="sw_media_folder_content_folder_listing">
                <ul v-if="subFolders.length > 0 || parentFolder !== null" class="ct-media-folder-content__folder-listing">
                    <ct-block name="sw_media_folder_content_list_item">
                        <li v-if="parentFolder !== null" class="ct-media-folder-content__list-item">
                            <button
                                class="ct-media-folder-content__button ct-media-folder-content__parent-folder"
                                @click="emitInput(parentFolder)"
                            >
                                <ct-block name="sw_media_folder_content_folder_icon">
                                    <img
                                        :src="
                                            assetFilter(
                                                '/administration/administration/static/img/media/folder--back--breadcrumb.svg',
                                            )
                                        "
                                        class="ct-media-folder-content__folder-icon"
                                        alt="Folder thumbnail"
                                    />
                                </ct-block>

                                {{ parentFolder.name }}
                            </button>
                        </li>

                        <li
                            v-for="(folder, index) in subFolders"
                            :key="folder.id"
                            :class="[
                                { 'is--selected': folder.id === selectedId },
                                'ct-media-folder-content__list-item--' + index,
                            ]"
                            class="ct-media-folder-content__list-item"
                            role="button"
                            tabindex="0"
                            @click="emitInput(folder)"
                            @keydown.enter="emitInput(folder)"
                        >
                            <ct-block name="sw_media_folder_content_button_folder">
                                <ct-block name="sw_media_folder_content_button_folder_button">
                                    <button class="ct-media-folder-content__button ct-media-folder-content__folder-button">
                                        <ct-block name="sw_media_folder_content_folder_button_icon">
                                            <img
                                                :src="
                                                    assetFilter(
                                                        '/administration/administration/static/img/media/folder-thumbnail.svg',
                                                    )
                                                "
                                                class="ct-media-folder-content__folder-icon"
                                                alt="Folder thumbnail"
                                            />
                                        </ct-block>
                                        {{ folder.name }}
                                    </button>
                                </ct-block>
                                <ct-block name="sw_media_folder_content_switch_button">
                                    <mt-icon
                                        v-if="getChildCount(folder) > 0"
                                        class="ct-media-folder-content__switch-button"
                                        name="regular-chevron-right-xxs"
                                        size="12px"
                                    />
                                </ct-block>
                            </ct-block>
                        </li>
                    </ct-block>
                </ul>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-folder-content.scss';
const { Context } = Contena;
const { Criteria } = Contena.Data;

const props = defineProps({
    startFolderId: {
        type: String,
        required: false,
        default: null,
    },

    selectedId: {
        type: String,
        required: false,
        default: null,
    },
});
const emit = defineEmits(['selected']);

import { ref, computed, inject, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const filterItems = inject('filterItems');
const repositoryFactory = inject('repositoryFactory');

const subFolders = ref([]);
const parentFolder = ref(null);

const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});

const mountedComponent = () => {
    void getSubFolders(props.startFolderId);
    void fetchParentFolder(props.startFolderId);
};
async function getSubFolders(parentId) {
    const criteria = new Criteria(1, 50)
        .addFilter(Criteria.equals('parentId', parentId))
        .addAssociation('children')
        .addSorting(Criteria.sort('name', 'asc'));
    const searchResult = await mediaFolderRepository.value.search(criteria, Context.api);
    subFolders.value = searchResult.filter(filterItems);
}
const getChildCount = (folder) => {
    return folder.children.filter(filterItems).length;
};
async function fetchParentFolder(folderId) {
    if (folderId !== null) {
        const folder = await mediaFolderRepository.value.get(folderId, Context.api);
        void updateParentFolder(folder);
    } else {
        parentFolder.value = null;
    }
}
async function updateParentFolder(child) {
    if (child.id === null) {
        parentFolder.value = null;
    } else if (child.parentId === null) {
        parentFolder.value = {
            id: null,
            name: t('ct-media.index.rootFolderName'),
        };
    } else {
        parentFolder.value = await mediaFolderRepository.value.get(child.parentId, Context.api);
    }
}
const emitInput = (folder) => {
    emit('selected', folder);
};

watch(
    () => props.startFolderId,
    () => {
        void getSubFolders(props.startFolderId);
        void fetchParentFolder(props.startFolderId);
    },
);

onMounted(() => {
    mountedComponent();
});

swDefinePublic({
    filterItems,
    repositoryFactory,
    subFolders,
    parentFolder,
    mediaFolderRepository,
    assetFilter,
    mountedComponent,
    getSubFolders,
    getChildCount,
    fetchParentFolder,
    updateParentFolder,
    emitInput,
});

defineExpose({
    filterItems,
    repositoryFactory,
    subFolders,
    parentFolder,
    mediaFolderRepository,
    assetFilter,
    mountedComponent,
    getSubFolders,
    getChildCount,
    fetchParentFolder,
    updateParentFolder,
    emitInput,
});
</script>
