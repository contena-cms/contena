<template>
    <ct-block name="ct_media_breadcrumbs">
        <nav class="ct-media-breadcrumbs" :class="ctMediaBreadcrumbsClasses">
            <ct-block name="ct_media_breadcrumbs_root_folder">
                <!-- eslint-disable-next-line vuejs-accessibility/anchor-has-content -->
                <a
                    class="ct-media-breadcrumbs__button-back-to-root"
                    role="button"
                    tabindex="0"
                    @click="onBreadcrumbsItemClicked(null)"
                    @keydown.enter="onBreadcrumbsItemClicked(null)"
                >
                    <img
                        v-if="!small"
                        :src="
                            assetFilter(
                                '/administration/administration/static/img/media/multicolor-folder-breadcrumbs-back-to-root.svg',
                            )
                        "
                        alt="Folder breadcrumbs back-to-root"
                    />
                    <img
                        v-else
                        :src="
                            assetFilter('/administration/administration/static/img/media/multicolor-folder-breadcrumbs.svg')
                        "
                        alt="Folder breadcrumbs"
                    />
                </a>
            </ct-block>

            <ct-block name="ct_media_breadcrumbs_parent_folder">
                <a
                    v-if="parentFolder"
                    class="ct-media-breadcrumbs__entry ct-media-breadcrumbs__parent-folder"
                    role="button"
                    tabindex="0"
                    @click="onBreadcrumbsItemClicked(parentFolder.id)"
                    @keydown.enter="onBreadcrumbsItemClicked(parentFolder.id)"
                >
                    <mt-icon class="ct-media-breadcrumbs__arrow-separator" name="regular-chevron-right-xxs" />
                    {{ parentFolder.name }}
                </a>
            </ct-block>

            <ct-block name="ct_media_breadcrumbs_current_folder">
                <span v-if="currentFolder" class="ct-media-breadcrumbs__entry ct-media-breadcrumbs__current-folder">
                    <mt-icon class="ct-media-breadcrumbs__arrow-separator" name="regular-chevron-right-xxs" />
                    {{ currentFolder.name }}
                </span>
            </ct-block>
        </nav>
    </ct-block>
</template>

<script setup>
import './ct-media-breadcrumbs.scss';
const { Context, Filter } = Contena;

const props = defineProps({
    currentFolderId: {
        type: String,
        required: false,
        default: null,
    },

    small: {
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
const emit = defineEmits(['update:currentFolderId']);

import { ref, computed, inject, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const repositoryFactory = inject('repositoryFactory');
const feature = inject('feature');

const currentFolder = ref(null);
const parentFolder = ref(null);

const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const rootFolder = computed(() => {
    const root = mediaFolderRepository.value.create(Context.api);
    root.name = t('ct-media.index.rootFolderName');
    root.id = null;
    return root;
});
const ctMediaBreadcrumbsClasses = computed(() => {
    return {
        'is--small': props.small,
    };
});
const assetFilter = computed(() => {
    return Filter.getByName('asset');
});

const createdComponent = () => {
    void updateFolder();
};
const updateFolder = async () => {
    if (!props.currentFolderId) {
        currentFolder.value = rootFolder.value;
        parentFolder.value = null;
    } else {
        currentFolder.value = await mediaFolderRepository.value.get(props.currentFolderId, Context.api);

        if (currentFolder.value && currentFolder.value.parentId) {
            parentFolder.value = await mediaFolderRepository.value.get(currentFolder.value.parentId, Context.api);
        } else {
            parentFolder.value = rootFolder.value;
        }
    }
};
const onBreadcrumbsItemClicked = (id) => {
    if (props.disabled) {
        return;
    }

    emit('update:currentFolderId', id);
};

watch(
    () => props.currentFolderId,
    () => {
        void updateFolder();
    },
);

createdComponent();

ctDefinePublic({
    repositoryFactory,
    feature,
    currentFolder,
    parentFolder,
    mediaFolderRepository,
    rootFolder,
    ctMediaBreadcrumbsClasses,
    assetFilter,
    createdComponent,
    updateFolder,
    onBreadcrumbsItemClicked,
});

defineExpose({
    repositoryFactory,
    feature,
    currentFolder,
    parentFolder,
    mediaFolderRepository,
    rootFolder,
    ctMediaBreadcrumbsClasses,
    assetFilter,
    createdComponent,
    updateFolder,
    onBreadcrumbsItemClicked,
});
</script>
