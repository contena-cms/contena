<template>
    <ct-block name="sw_media_preview_v2">
        <div
            ref="previewRoot"
            v-tooltip.bottom="{ message: mediaName, disabled: hideTooltip }"
            class="ct-media-preview-v2"
            :class="mediaPreviewClasses"
        >
            <ct-block name="sw_media_preview_v2_no_media">
                <template v-if="!source">
                    <ct-block name="sw_media_preview_v2_no_media_icon">
                        <mt-icon name="regular-image" size="var(--scale-size-16)" />
                    </ct-block>
                </template>
            </ct-block>

            <ct-block name="sw_media_preview_v2_file_types">
                <template v-if="!source"><!-- Keeps the conditional chain connected across ct-block. --></template>
                <template v-else>
                    <ct-block name="sw_media_preview_v2_file_type_check">
                        <template v-if="mimeTypeGroup === 'image'">
                            <ct-block name="sw_media_preview_v2_file_type_image">
                                <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
                                <img
                                    v-if="!imagePreviewFailed"
                                    class="ct-media-preview-v2__item"
                                    :class="transparencyClass"
                                    :src="previewUrl"
                                    :alt="alt"
                                    :srcset="sourceSet"
                                    :sizes="`${width}px`"
                                    :draggable="false"
                                    @click="$emit('click')"
                                    @error="showEvent"
                                />
                                <img
                                    v-else
                                    class="ct-media-preview-v2__item ct-media-preview-v2__placeholder"
                                    :src="assetFilter(placeholderIconPath)"
                                    :alt="mimeType"
                                />
                                <mt-icon
                                    v-if="mediaIsPrivate && lockIsVisible"
                                    class="ct-media-preview-v2__locked-icon"
                                    name="regular-lock"
                                />
                            </ct-block>
                        </template>

                        <template v-else-if="isPlayable && mimeTypeGroup === 'video'">
                            <ct-block name="sw_media_preview_v2_file_type_video">
                                <img
                                    v-if="mediaIsPrivate"
                                    class="ct-media-preview-v2__item ct-media-preview-v2__placeholder"
                                    :src="assetFilter(placeholderIconPath)"
                                    :alt="mimeType"
                                />
                                <video
                                    v-else
                                    ref="mediaElement"
                                    :controls="showControls"
                                    :autoplay="autoplay"
                                    :poster="hasVideoCover ? videoCoverPoster : null"
                                    :preload="videoPreloadValue"
                                    controlsList="nodownload"
                                    class="ct-media-preview-v2__item"
                                >
                                    <source :src="previewUrl" :type="mimeType" />
                                    {{ translate('global.ct-media-preview-v2.fallbackVideoTagSupport') }}
                                </video>
                                <button
                                    v-if="!showControls && !mediaIsPrivate"
                                    class="ct-media-preview-v2__play-button"
                                    @click="onPlayClick"
                                >
                                    <mt-icon class="ct-media-preview-v2__play-icon" name="regular-play" />
                                </button>
                                <mt-icon
                                    v-if="mediaIsPrivate && lockIsVisible"
                                    class="ct-media-preview-v2__locked-icon"
                                    name="regular-lock"
                                />
                            </ct-block>
                        </template>

                        <template v-else-if="isPlayable && mimeTypeGroup === 'audio'">
                            <ct-block name="sw_media_preview_v2_file_type_audio">
                                <img
                                    class="ct-media-preview-v2__item ct-media-preview-v2__placeholder"
                                    :src="assetFilter(placeholderIconPath)"
                                    :alt="mimeType"
                                />
                                <!-- eslint-disable-next-line vuejs-accessibility/media-has-caption -->
                                <audio
                                    ref="mediaElement"
                                    :controls="showControls"
                                    :autoplay="autoplay"
                                    controlsList="nodownload"
                                    class="ct-media-preview-v2__item ct-media-preview-v2__item--audio"
                                >
                                    <source :src="previewUrl" :type="mimeType" />
                                    {{ translate('global.ct-media-preview-v2.fallbackAudioTagSupport') }}
                                </audio>
                                <button v-if="!showControls" class="ct-media-preview-v2__play-button" @click="onPlayClick">
                                    <mt-icon class="ct-media-preview-v2__play-icon" name="regular-play" />
                                </button>
                                <mt-icon
                                    v-if="mediaIsPrivate && lockIsVisible"
                                    class="ct-media-preview-v2__locked-icon"
                                    name="regular-lock"
                                />
                            </ct-block>
                        </template>

                        <template v-else-if="(isUrl || isRelativePath) && !urlPreviewFailed">
                            <img
                                :src="previewUrl"
                                class="ct-media-preview-v2__item"
                                :draggable="false"
                                :alt="mimeType"
                                @error="removeUrlPreview"
                            />
                        </template>

                        <template v-else>
                            <ct-block name="sw_media_preview_v2_file_type_placeholder">
                                <img
                                    class="ct-media-preview-v2__item ct-media-preview-v2__placeholder"
                                    :src="assetFilter(placeholderIconPath)"
                                    :alt="mimeType"
                                />
                            </ct-block>
                            <ct-block name="sw_media_preview_v2_unsupported_format_warning">
                                <mt-icon
                                    v-if="showUnsupportedFormatWarning"
                                    v-tooltip.bottom="{
                                        message: translate('global.ct-media-preview-v2.warningUnsupportedFormat', {
                                            format: mimeType,
                                        }),
                                    }"
                                    name="regular-exclamation-triangle"
                                    size="16px"
                                    color="var(--color-warning-500)"
                                    class="ct-media-preview-v2__warning-icon"
                                />
                            </ct-block>
                            <mt-icon
                                v-if="mediaIsPrivate && lockIsVisible"
                                class="ct-media-preview-v2__locked-icon"
                                name="regular-lock"
                            />
                        </template>
                    </ct-block>
                </template>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import { isPlayableMediaFormat, shouldShowUnsupportedFormatWarning } from 'src/app/service/media-format.service';
import './ct-media-preview-v2.scss';
const { Context, Filter } = Contena;
const { fileReader, EventBus } = Contena.Utils;

const props = defineProps({
    source: {
        required: true,
    },

    showControls: {
        type: Boolean,
        required: false,
        default: false,
    },

    autoplay: {
        type: Boolean,
        required: false,
        default: false,
    },

    transparency: {
        type: Boolean,
        required: false,
        default: true,
    },

    useThumbnails: {
        type: Boolean,
        required: false,
        default: true,
    },

    hideTooltip: {
        type: Boolean,
        required: false,
        default: true,
    },

    mediaIsPrivate: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'click',
    'media-preview-play',
]);

import { ref, computed, inject, watch, nextTick, onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const translate = t;
const mediaElement = ref(null);
const previewRoot = ref(null);
const placeholderThumbnailsBasePath = '/administration/administration/static/img/media-preview/';
const placeHolderThumbnails = {
    application: {
        'adobe.illustrator': 'icons-multicolor-file-thumbnail-ai',
        illustrator: 'icons-multicolor-file-thumbnail-ai',
        postscript: 'icons-multicolor-file-thumbnail-ai',
        msword: 'icons-multicolor-file-thumbnail-doc',
        'vnd.openxmlformats-officedocument.wordprocessingml.document': 'icons-multicolor-file-thumbnail-doc',
        pdf: 'icons-multicolor-file-thumbnail-pdf',
        'vnd.ms-excel': 'icons-multicolor-file-thumbnail-xls',
        'vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'icons-multicolor-file-thumbnail-xls',
        'vnd.ms-powerpoint': 'icons-multicolor-file-thumbnail-ppt',
        'vnd.openxmlformats-officedocument.presentationml.presentation': 'icons-multicolor-file-thumbnail-ppt',
        glb: 'icons-multicolor-file-thumbnail-glb',
        'octet-stream': 'icons-multicolor-file-thumbnail-glb',
    },
    video: {
        'x-msvideo': 'icons-multicolor-file-thumbnail-avi',
        quicktime: 'icons-multicolor-file-thumbnail-mov',
        mp4: 'icons-multicolor-file-thumbnail-mp4',
    },
    text: {
        csv: 'icons-multicolor-file-thumbnail-csv',
        plain: 'icons-multicolor-file-thumbnail-txt',
    },
    image: {
        gif: 'icons-multicolor-file-thumbnail-gif',
        jpeg: 'icons-multicolor-file-thumbnail-jpg',
        'svg+xml': 'icons-multicolor-file-thumbnail-svg',
    },
    model: {
        'gltf-binary': 'icons-multicolor-file-thumbnail-glb',
    },
};

const repositoryFactory = inject('repositoryFactory');
const feature = inject('feature');

const trueSource = ref(null);
const width = ref(0);
const dataUrl = ref('');
const urlPreviewFailed = ref(false);
const imagePreviewFailed = ref(false);

const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const mediaPreviewClasses = computed(() => {
    return {
        'is--icon': isIcon.value,
        'is--no-media': !props.source,
    };
});
const transparencyClass = computed(() => {
    return {
        'shows--transparency': canBeTransparent.value,
    };
});
const canBeTransparent = computed(() => {
    if (!props.transparency) {
        return false;
    }

    return isIcon.value || mimeTypeGroup.value === 'image';
});
const mimeType = computed(() => {
    if (!trueSource.value) {
        return '';
    }

    if (trueSource.value instanceof File) {
        return trueSource.value.type;
    }

    if (trueSource.value instanceof URL) {
        return 'application/octet-stream';
    }

    return trueSource.value.mimeType;
});
const mimeTypeGroup = computed(() => {
    if (!mimeType.value) {
        return '';
    }

    return mimeType.value.split('/')[0];
});
const isPlayable = computed(() => {
    return isPlayableMediaFormat(mimeType.value);
});
const showUnsupportedFormatWarning = computed(() => {
    return shouldShowUnsupportedFormatWarning(mimeType.value);
});
const isIcon = computed(() => {
    return /.*svg.*/.test(mimeType.value);
});
const placeholderIcon = computed(() => {
    if (!mimeType.value) {
        return 'icons-multicolor-file-thumbnail-broken';
    }

    const mediaTypeIconGroup = placeHolderThumbnails[mimeTypeGroup.value];
    if (mediaTypeIconGroup) {
        const mediaTypeIcon = mediaTypeIconGroup[`${mimeType.value.split('/')[1]}`];
        if (mediaTypeIcon) {
            return mediaTypeIcon;
        }
    }

    return 'icons-multicolor-file-thumbnail-normal';
});
const placeholderIconPath = computed(() => {
    return `${placeholderThumbnailsBasePath}${placeholderIcon.value}.svg`;
});
const lockIsVisible = computed(() => {
    return width.value > 40;
});
const previewUrl = computed(() => {
    if (!trueSource.value) {
        return '';
    }

    if (isFile.value) {
        void getDataUrlFromFile();
        return dataUrl.value;
    }

    if (isUrl.value) {
        return trueSource.value.href;
    }

    if (isRelativePath.value) {
        return trueSource.value;
    }

    return trueSource.value.url;
});
const isUrl = computed(() => {
    return trueSource.value instanceof URL;
});
const isFile = computed(() => {
    return trueSource.value instanceof File;
});
const isRelativePath = computed(() => {
    return typeof trueSource.value === 'string';
});
const alt = computed(() => {
    if (!trueSource.value || typeof trueSource.value !== 'object') {
        return '';
    }

    if (trueSource.value.alt) {
        return trueSource.value.alt;
    }
    return trueSource.value.fileName;
});
const mediaName = computed(() => {
    if (!trueSource.value) {
        return t('global.ct-media-preview-v2.textNoMedia');
    }

    return mediaNameFilter.value(trueSource.value, trueSource.value.fileName);
});
const mediaNameFilter = computed(() => {
    return Filter.getByName('mediaName');
});
const assetFilter = computed(() => {
    return Filter.getByName('asset');
});
const sourceSet = computed(() => {
    if (isFile.value || isUrl.value || !trueSource.value) {
        return '';
    }

    return buildSourceSet(trueSource.value);
});
const videoCoverMedia = computed(() => {
    if (!trueSource.value || typeof trueSource.value !== 'object') {
        return null;
    }

    return trueSource.value.extensions?.videoCoverMedia ?? null;
});
const videoCoverPoster = computed(() => {
    return videoCoverMedia.value?.url ?? null;
});
const hasVideoCover = computed(() => {
    return Boolean(videoCoverPoster.value) && !props.mediaIsPrivate;
});
const videoPreloadValue = computed(() => {
    return hasVideoCover.value ? 'none' : 'metadata';
});

const createdComponent = () => {
    void fetchSourceIfNecessary();
    EventBus.on('ct-media-library-item-updated', onMediaLibraryItemUpdated);
};
const beforeUnmountedComponent = () => {
    EventBus.off('ct-media-library-item-updated', onMediaLibraryItemUpdated);
};
const mountedComponent = () => {
    width.value = previewRoot.value?.offsetWidth ?? 0;
};
async function fetchSourceIfNecessary() {
    if (!props.source) {
        return;
    }
    if (typeof props.source !== 'string') {
        trueSource.value = props.source[0] ?? props.source;
        await ensureVideoCoverMedia();
        return;
    }
    try {
        trueSource.value = await mediaRepository.value.get(props.source, Context.api);
        await ensureVideoCoverMedia();
    } catch {
        trueSource.value = props.source;
    }
}
const onPlayClick = (originalDomEvent) => {
    if (!(originalDomEvent.shiftKey || originalDomEvent.ctrlKey)) {
        originalDomEvent.stopPropagation();
        emit('media-preview-play', {
            originalDomEvent,
            item: trueSource.value,
        });
    }
};
async function getDataUrlFromFile() {
    if (mimeTypeGroup.value !== 'image') {
        return;
    }
    dataUrl.value = await fileReader.readAsDataURL(trueSource.value);
}
const reloadMediaElement = () => {
    if (!isPlayable.value || (mimeTypeGroup.value !== 'video' && mimeTypeGroup.value !== 'audio')) {
        return;
    }

    void nextTick(() => {
        const element = mediaElement.value;

        if (typeof element?.load === 'function') {
            element.load();
        }
    });
};
const removeUrlPreview = () => {
    urlPreviewFailed.value = true;
};
const showEvent = () => {
    if (!isFile.value) {
        imagePreviewFailed.value = true;
    }
};
function onMediaLibraryItemUpdated(mediaId) {
    const currentMediaId = getCurrentMediaId();
    if (!currentMediaId || currentMediaId !== mediaId) {
        return;
    }
    void fetchSourceIfNecessary();
}
function getCurrentMediaId() {
    if (typeof props.source === 'string') {
        return props.source;
    }
    const entity = Array.isArray(props.source) ? props.source[0] : props.source;
    return entity?.id ?? trueSource.value?.id ?? null;
}
async function ensureVideoCoverMedia() {
    if (!trueSource.value || typeof trueSource.value !== 'object') {
        return;
    }
    const coverMediaId = getVideoCoverMediaId(trueSource.value);
    if (!coverMediaId) {
        return;
    }
    const existingCover = trueSource.value.extensions?.videoCoverMedia;
    if (existingCover && existingCover.id === coverMediaId) {
        return;
    }
    try {
        const coverMedia = await mediaRepository.value.get(coverMediaId, Context.api);
        trueSource.value.extensions = {
            ...(trueSource.value.extensions ?? {}),
            videoCoverMedia: coverMedia,
        };
    } catch {
        // ignore fetch errors for cover preview
    }
}
function getVideoCoverMediaId(mediaEntity) {
    const metaData = mediaEntity?.metaData;
    if (!metaData || typeof metaData !== 'object') {
        return null;
    }
    const videoMeta = metaData.video;
    if (!videoMeta || typeof videoMeta !== 'object') {
        return null;
    }
    const coverMediaId = videoMeta.coverMediaId;
    return typeof coverMediaId === 'string' ? coverMediaId : null;
}
function buildSourceSet(media) {
    if (!media || media instanceof File || media instanceof URL || typeof media === 'string') {
        return '';
    }
    const thumbnails = Array.isArray(media.thumbnails) ? media.thumbnails : [];
    if (thumbnails.length === 0) {
        return '';
    }
    const sources = thumbnails.map((thumbnail) => {
        return `${thumbnail.url} ${thumbnail.width}w`;
    });
    return sources.join(', ');
}

watch(
    () => props.source,
    () => {
        urlPreviewFailed.value = false;
        imagePreviewFailed.value = false;
        void fetchSourceIfNecessary();
    },
);
watch(
    () => previewUrl.value,
    (newUrl, oldUrl) => {
        if (!newUrl || newUrl === oldUrl) {
            return;
        }

        reloadMediaElement();
    },
);

createdComponent();

onBeforeUnmount(() => {
    beforeUnmountedComponent();
});
onMounted(() => {
    mountedComponent();
});

swDefinePublic({
    repositoryFactory,
    feature,
    trueSource,
    width,
    dataUrl,
    urlPreviewFailed,
    imagePreviewFailed,
    mediaRepository,
    mediaPreviewClasses,
    transparencyClass,
    canBeTransparent,
    mimeType,
    mimeTypeGroup,
    isPlayable,
    showUnsupportedFormatWarning,
    isIcon,
    placeholderIcon,
    placeholderIconPath,
    lockIsVisible,
    previewUrl,
    isUrl,
    isFile,
    isRelativePath,
    alt,
    mediaName,
    mediaNameFilter,
    assetFilter,
    sourceSet,
    videoCoverMedia,
    videoCoverPoster,
    hasVideoCover,
    videoPreloadValue,
    createdComponent,
    beforeUnmountedComponent,
    mountedComponent,
    fetchSourceIfNecessary,
    onPlayClick,
    getDataUrlFromFile,
    reloadMediaElement,
    removeUrlPreview,
    showEvent,
    onMediaLibraryItemUpdated,
    getCurrentMediaId,
    ensureVideoCoverMedia,
    getVideoCoverMediaId,
    buildSourceSet,
});

defineExpose({
    repositoryFactory,
    feature,
    trueSource,
    width,
    dataUrl,
    urlPreviewFailed,
    imagePreviewFailed,
    mediaRepository,
    mediaPreviewClasses,
    transparencyClass,
    canBeTransparent,
    mimeType,
    mimeTypeGroup,
    isPlayable,
    showUnsupportedFormatWarning,
    isIcon,
    placeholderIcon,
    placeholderIconPath,
    lockIsVisible,
    previewUrl,
    isUrl,
    isFile,
    isRelativePath,
    alt,
    mediaName,
    mediaNameFilter,
    assetFilter,
    sourceSet,
    videoCoverMedia,
    videoCoverPoster,
    hasVideoCover,
    videoPreloadValue,
    createdComponent,
    beforeUnmountedComponent,
    mountedComponent,
    fetchSourceIfNecessary,
    onPlayClick,
    getDataUrlFromFile,
    reloadMediaElement,
    removeUrlPreview,
    showEvent,
    onMediaLibraryItemUpdated,
    getCurrentMediaId,
    ensureVideoCoverMedia,
    getVideoCoverMediaId,
    buildSourceSet,
});
</script>
