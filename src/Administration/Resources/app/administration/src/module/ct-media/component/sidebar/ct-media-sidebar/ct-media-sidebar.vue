<!-- eslint-disable vue/no-mutating-props -->
<template>
    <ct-block name="sw_media_sidebar">
        <div class="ct-media-sidebar" :class="mediaSidebarClasses">
            <ct-block name="sw_media_sidebar_headline">
                <div v-if="headLine" class="ct-media-sidebar__header">
                    <h3 class="ct-media-sidebar__headline">
                        {{ headLine }}
                    </h3>

                    <ct-block name="sw_media_sidebar_close">
                        <mt-button
                            class="ct-media-sidebar__close"
                            variant="tertiary"
                            size="small"
                            square
                            :aria-label="$t('global.default.close')"
                            @click="onClose"
                        >
                            <mt-icon name="regular-times" size="16px" />
                        </mt-button>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_media_sidebar_item_quickinfo_content">
                <div class="ct-media-sidebar__quickinfo-scroll-container">
                    <ct-media-quickinfo
                        v-if="isSingleFile && firstEntity.getEntityName() === 'media'"
                        :item="firstEntity"
                        :editable="editable"
                        v-bind="filteredAttributes"
                        @update:item="onFirstItemUpdated"
                    />

                    <ct-media-folder-info
                        v-else-if="isSingleFile && firstEntity.getEntityName() === 'media_folder'"
                        :media-folder="firstEntity"
                        :editable="editable"
                        v-bind="filteredAttributes"
                    />

                    <ct-media-quickinfo-multiple
                        v-else-if="isMultipleFile"
                        :editable="editable"
                        :items="items"
                        v-bind="filteredAttributes"
                    />

                    <ct-media-folder-info
                        v-else-if="currentFolder"
                        :media-folder="currentFolder"
                        :editable="editable"
                        v-bind="filteredAttributes"
                        @media-folder-renamed="onMediaFolderRenamed"
                    />

                    <mt-empty-state
                        v-else
                        :centered="true"
                        :icon="$route.meta.$module.icon"
                        :headline="$t('ct-media.sidebar.labelNoMediaSelected')"
                    />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
/* eslint-disable vue/no-mutating-props */
import './ct-media-sidebar.scss';
const { Filter, Context } = Contena;

const props = defineProps({
    items: {
        required: true,
        type: Array,
        validator(value) {
            const invalidElements = value.filter((element) => {
                return ![
                    'media',
                    'media_folder',
                ].includes(element.getEntityName());
            });
            return invalidElements.length === 0;
        },
    },

    currentFolderId: {
        type: String,
        required: false,
        default: null,
    },

    editable: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'media-sidebar-folder-renamed',
    'media-sidebar-close',
]);

import { ref, computed, inject, watch, useAttrs } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const attrs = useAttrs();
const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const repositoryFactory = inject('repositoryFactory');

const currentFolder = ref(null);

const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const mediaNameFilter = computed(() => {
    return Filter.getByName('mediaName');
});
const mediaSidebarClasses = computed(() => {
    return {
        'no-headline': !headLine.value,
    };
});
const isSingleFile = computed(() => {
    return props.items.length === 1;
});
const isMultipleFile = computed(() => {
    return props.items.length > 1;
});
const headLine = computed(() => {
    if (isSingleFile.value) {
        if (firstEntity.value.getEntityName() === 'media') {
            return mediaNameFilter.value(firstEntity.value);
        }
        return firstEntity.value.name;
    }

    if (isMultipleFile.value) {
        return getSelectedFilesCount.value;
    }

    if (currentFolder.value) {
        return currentFolder.value.name;
    }

    return null;
});
const getSelectedFilesCount = computed(() => {
    return `${t('ct-media.sidebar.labelHeadlineMultiple', { count: props.items.length }, props.items.length)}`;
});
const firstEntity = computed(() => {
    return props.items[0];
});
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});
const filteredAttributes = computed(() => {
    const filteredAttributes = {};

    Object.entries(attrs).forEach(
        ([
            key,
            value,
        ]) => {
            if (key.startsWith('on') && typeof value === 'function') {
                filteredAttributes[key] = value;
            }
        },
    );

    return filteredAttributes;
});

const createdComponent = () => {
    void fetchCurrentFolder();
};
const fetchCurrentFolder = async () => {
    if (!props.currentFolderId) {
        currentFolder.value = null;
        return;
    }

    currentFolder.value = await mediaFolderRepository.value.get(props.currentFolderId, Context.api);
};
const onMediaFolderRenamed = () => {
    emit('media-sidebar-folder-renamed');
};
const onClose = () => {
    emit('media-sidebar-close');
};
const onFirstItemUpdated = async (newItem) => {
    const firstItem = props.items[0];

    try {
        firstItem.isLoading = true;
        Object.assign(props.items[0], newItem);
        await mediaRepository.value.save(firstItem, Context.api);
        createNotificationSuccess({
            message: t('global.ct-media-media-item.notification.settingsSuccess.message'),
        });
    } catch {
        createNotificationError({
            message: t('global.notification.unspecifiedSaveErrorMessage'),
        });
    } finally {
        firstItem.isLoading = false;
    }
};

watch(
    () => props.currentFolderId,
    () => {
        void fetchCurrentFolder();
    },
);

createdComponent();

swDefinePublic({
    repositoryFactory,
    currentFolder,
    mediaRepository,
    mediaFolderRepository,
    mediaNameFilter,
    mediaSidebarClasses,
    isSingleFile,
    isMultipleFile,
    headLine,
    getSelectedFilesCount,
    firstEntity,
    assetFilter,
    filteredAttributes,
    createdComponent,
    fetchCurrentFolder,
    onMediaFolderRenamed,
    onFirstItemUpdated,
    onClose,
});

defineExpose({
    repositoryFactory,
    currentFolder,
    mediaRepository,
    mediaFolderRepository,
    mediaNameFilter,
    mediaSidebarClasses,
    isSingleFile,
    isMultipleFile,
    headLine,
    getSelectedFilesCount,
    firstEntity,
    assetFilter,
    filteredAttributes,
    createdComponent,
    fetchCurrentFolder,
    onMediaFolderRenamed,
    onFirstItemUpdated,
    onClose,
});
</script>
