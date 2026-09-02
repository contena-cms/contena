<template>
    <ct-block name="ct_media_modal_renderer">
        <ct-media-modal-v2
            v-if="mediaModal"
            :initial-folder-id="mediaModal.initialFolderId"
            :file-accept="mediaModal.fileAccept"
            :entity-context="mediaModal.entityContext"
            :default-tab="mediaModal.defaultTab"
            :allow-multi-select="mediaModal.allowMultiSelect"
            @media-modal-selection-change="onSelectionChange"
            @modal-close="closeModal"
        />

        <ct-media-save-modal
            v-if="saveMediaModal"
            :initial-folder-id="saveMediaModal.initialFolderId"
            :initial-file-name="saveMediaModal.initialFileName"
            :file-type="saveMediaModal.fileType"
            @save-media="onSaveMedia"
            @modal-close="closeSaveModal"
        />
    </ct-block>
</template>

<script setup lang="ts">
import type Entity from 'src/core/data/entity.data';
import type EntityCollection from 'src/core/data/entity-collection.data';

defineProps({});

import { computed } from 'vue';

const mediaModal = computed(() => {
    return Contena.Store.get('mediaModal').mediaModal;
});
const saveMediaModal = computed(() => {
    return Contena.Store.get('mediaModal').saveMediaModal;
});

const closeModal = () => {
    Contena.Store.get('mediaModal').closeModal();
};
const closeSaveModal = () => {
    Contena.Store.get('mediaModal').closeSaveModal();
};
const onSelectionChange = (selection: EntityCollection<'media'>) => {
    const selectors: string[] = mediaModal.value?.selectors || [
        'id',
        'fileName',
        'url',
    ];

    const mediaSelection = transformObjectsByPaths(selection, selectors);

    if (mediaModal.value && typeof mediaModal.value.callback === 'function') {
        mediaModal.value.callback(mediaSelection);
    }
};
const getValueByPath = (obj: unknown, path: string) => {
    if (typeof path !== 'string' || path.length === 0) {
        return undefined;
    }

    const parts = path.split('.');

    return parts.reduce((currentAccumulator: unknown, currentKey: string): unknown => {
        if (currentAccumulator && typeof currentAccumulator === 'object' && currentKey in currentAccumulator) {
            return (currentAccumulator as Record<string, unknown>)[currentKey];
        }
        return undefined;
    }, obj);
};
function transformObjectsByPaths(inputArray: Entity<'media'>[], keysToKeep: string[]) {
    if (!Array.isArray(inputArray) || !Array.isArray(keysToKeep)) {
        return [];
    }
    return inputArray.map((item) => {
        const transformedObject = {};
        keysToKeep
            .filter((keyPath) => typeof keyPath === 'string' && keyPath.length > 0) // 1. Filter for valid keyPaths
            .forEach((keyPath: string) => {
                const value = getValueByPath(item, keyPath);
                setValueByPath(transformedObject, keyPath, value);
            });
        return transformedObject;
    });
}
function setValueByPath(obj: unknown, path: string, value: unknown) {
    if (typeof path !== 'string' || path.length === 0 || typeof obj !== 'object' || obj === null) {
        return;
    }
    const parts = path.split('.');
    let currentContext: Record<string, unknown> = obj as Record<string, unknown>;
    const intermediateParts = parts.slice(0, -1);
    intermediateParts.forEach((pathSegment: string) => {
        const segmentValue: unknown = currentContext[pathSegment];
        if (!(segmentValue && typeof segmentValue === 'object')) {
            currentContext[pathSegment] = {};
        }
        // Update currentContext to point to the (potentially newly created) nested object.
        currentContext = currentContext[pathSegment] as Record<string, unknown>;
    });
    const finalSegment = parts[parts.length - 1];
    currentContext[finalSegment] = value;
}
const onSaveMedia = (params: { fileName: string; folderId: string; mediaId?: string }) => {
    if (saveMediaModal.value && typeof saveMediaModal.value.callback === 'function') {
        saveMediaModal.value.callback(params);
    }
};

ctDefinePublic({
    mediaModal,
    saveMediaModal,
    closeModal,
    closeSaveModal,
    onSelectionChange,
    getValueByPath,
    transformObjectsByPaths,
    setValueByPath,
    onSaveMedia,
});

defineExpose({
    mediaModal,
    saveMediaModal,
    closeModal,
    closeSaveModal,
    onSelectionChange,
    getValueByPath,
    transformObjectsByPaths,
    setValueByPath,
    onSaveMedia,
});
</script>
