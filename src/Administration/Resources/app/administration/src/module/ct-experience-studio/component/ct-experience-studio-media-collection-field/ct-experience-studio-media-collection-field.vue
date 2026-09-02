<template>
    <ct-block name="ct_experience_studio_media_collection_field">
        <div class="ct-experience-studio-media-collection-field">
            <ct-block name="ct_experience_studio_media_collection_field_label">
                <label v-if="label" class="ct-experience-studio-media-collection-field__label">
                    {{ label }}
                </label>
            </ct-block>

            <ct-block name="ct_experience_studio_media_collection_field_selection">
                <ct-media-list-selection-v2
                    :entity="listEntity"
                    :entity-media-items="entityMediaItems"
                    :upload-tag="uploadTag"
                    default-folder-name="media"
                    :disabled="disabled"
                    @open-sidebar="onOpenMediaModal"
                    @item-remove="onItemRemove"
                    @item-sort="onItemSort"
                />
            </ct-block>

            <ct-block name="ct_experience_studio_media_collection_field_upload_listener">
                <ct-upload-listener :upload-tag="uploadTag" auto-upload @media-upload-finish="onUploadFinish" />
            </ct-block>

            <ct-block name="ct_experience_studio_media_collection_field_modal">
                <ct-media-modal-v2
                    v-if="showMediaModal"
                    variant="full"
                    :allow-multi-select="true"
                    @media-modal-selection-change="onMediaSelectionChange"
                    @modal-close="onCloseMediaModal"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

import './ct-experience-studio-media-collection-field.scss';

type MediaListItem = {
    mediaId: string;
    url: string;
    position: number;
};

const props = withDefaults(
    defineProps<{
        value?: string[] | null;
        label?: string | null;
        disabled?: boolean;
    }>(),
    {
        value: null,
        label: null,
        disabled: false,
    },
);

const emit = defineEmits<{
    (event: 'update:value', value: string[] | null): void;
}>();

const showMediaModal = ref(false);
const uploadTag = Contena.Utils.createId();

const mediaIds = computed(() => (Array.isArray(props.value) ? props.value : []));
const listEntity = computed(() => ({
    id: uploadTag,
    isLoading: false,
    getEntityName: () => 'media',
}));
const entityMediaItems = computed<MediaListItem[]>(() =>
    mediaIds.value.map((mediaId, position) => ({
        mediaId,
        url: mediaId,
        position,
    })),
);

const onOpenMediaModal = () => {
    if (props.disabled) {
        return;
    }

    showMediaModal.value = true;
};

const onCloseMediaModal = () => {
    showMediaModal.value = false;
};

const onMediaSelectionChange = (selection: Array<{ id: string }>) => {
    const merged = [...mediaIds.value];

    selection.forEach((media) => {
        if (!merged.includes(media.id)) {
            merged.push(media.id);
        }
    });

    showMediaModal.value = false;
    emitValue(merged);
};

const onUploadFinish = ({ targetId }: { targetId: string }) => {
    if (mediaIds.value.includes(targetId)) {
        return;
    }

    emitValue([
        ...mediaIds.value,
        targetId,
    ]);
};

const onItemRemove = (mediaItem: MediaListItem) => {
    emitValue(mediaIds.value.filter((id) => id !== mediaItem.mediaId));
};

const onItemSort = (dragData: MediaListItem, dropData: MediaListItem) => {
    if (dragData.position === dropData.position) {
        return;
    }

    const ids = [...mediaIds.value];
    const [moved] = ids.splice(dragData.position, 1);
    ids.splice(dropData.position, 0, moved);

    emitValue(ids);
};

function emitValue(ids: string[]): void {
    emit('update:value', ids.length > 0 ? ids : null);
}

ctDefinePublic({
    showMediaModal,
    uploadTag,
    mediaIds,
    listEntity,
    entityMediaItems,
    onOpenMediaModal,
    onCloseMediaModal,
    onMediaSelectionChange,
    onUploadFinish,
    onItemRemove,
    onItemSort,
    emitValue,
});
</script>
