<!-- eslint-disable vue/no-mutating-props -->
<template>
    <ct-block name="ct_category_detail_menu">
        <mt-card
            class="ct-category-detail-base__menu"
            position-identifier="ct-category-detail-menu"
            :title="$t('ct-category.base.menu.title')"
            :is-loading="isLoading"
        >
            <ct-block name="ct_category_detail_information_visible">
                <mt-switch
                    v-model="reversedVisibility"
                    bordered
                    :disabled="!acl.can('category.editor')"
                    :label="$t('ct-category.base.menu.visible')"
                />
            </ct-block>

            <ct-block name="ct_category_detail_menu_media">
                <ct-upload-listener
                    :key="category.id + 'uploadListener'"
                    :upload-tag="category.id"
                    auto-upload
                    @media-upload-finish="onSetMediaItem"
                />
                <ct-media-upload-v2
                    :key="category.id + 'upload'"
                    :label="$t('ct-category.base.menu.imageLabel')"
                    variant="regular"
                    :disabled="!acl.can('category.editor')"
                    :source="mediaItem"
                    :upload-tag="category.id"
                    :allow-multi-select="false"
                    :default-folder="category.getEntityName()"
                    @media-drop="onMediaDropped"
                    @media-upload-sidebar-open="showMediaModal = true"
                    @media-upload-remove-image="onRemoveMediaItem"
                />
            </ct-block>

            <ct-block name="ct_category_detail_menu_media_modal">
                <ct-media-modal-v2
                    v-if="showMediaModal"
                    :allow-multi-select="false"
                    :entity-context="category.getEntityName()"
                    @media-modal-selection-change="onMediaSelectionChange"
                    @modal-close="showMediaModal = false"
                />
            </ct-block>

            <ct-block name="ct_category_detail_menu_description">
                <mt-text-editor
                    :key="category.id + 'description-meteor'"
                    v-model="category.description"
                    class="ct-category-detail-base__description"
                    type="textarea"
                    :disabled="!acl.can('category.editor')"
                    sanitize-input
                    sanitize-field-name="category_translation.description"
                    :label="$t('ct-category.base.menu.descriptionLabel')"
                    :placeholder="$t('ct-category.base.menu.descriptionPlaceholder')"
                />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup>
/* eslint-disable vue/no-mutating-props */
const props = defineProps({
    category: {
        type: Object,
        required: true,
    },

    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { ref, computed, inject } from 'vue';

const acl = inject('acl');
const repositoryFactory = inject('repositoryFactory');

const showMediaModal = ref(false);

const reversedVisibility = computed({
    get: () => {
        return !props.category.visible;
    },
    set: (visibility) => {
        props.category.visible = !visibility;
    },
});
const mediaItem = computed(() => {
    return props.category !== null ? props.category.media : null;
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});

const onMediaSelectionChange = (mediaItems) => {
    const media = mediaItems[0];
    if (!media) {
        return;
    }

    mediaRepository.value.get(media.id).then((updatedMedia) => {
        props.category.mediaId = updatedMedia.id;
        props.category.media = updatedMedia;
    });
};
const onSetMediaItem = ({ targetId }) => {
    mediaRepository.value.get(targetId).then((updatedMedia) => {
        props.category.mediaId = targetId;
        props.category.media = updatedMedia;
    });
};
const onRemoveMediaItem = () => {
    props.category.mediaId = null;
    props.category.media = null;
};
const onMediaDropped = (dropItem) => {
    // to be consistent refetch entity with repository
    onSetMediaItem({ targetId: dropItem.id });
};

ctDefinePublic({
    acl,
    repositoryFactory,
    showMediaModal,
    reversedVisibility,
    mediaItem,
    mediaRepository,
    onMediaSelectionChange,
    onSetMediaItem,
    onRemoveMediaItem,
    onMediaDropped,
});

defineExpose({
    acl,
    repositoryFactory,
    showMediaModal,
    reversedVisibility,
    mediaItem,
    mediaRepository,
    onMediaSelectionChange,
    onSetMediaItem,
    onRemoveMediaItem,
    onMediaDropped,
});
</script>
