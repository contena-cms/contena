<template>
    <ct-block name="sw_experience_studio_preview">
        <div class="ct-experience-studio-preview">
            <div class="ct-experience-studio-preview__frame-wrapper">
                <div class="ct-experience-studio-preview__frame" :class="viewportClass">
                    <mt-loader v-if="showInitialLoader" class="ct-experience-studio-preview__loader" />

                    <mt-empty-state
                        v-else-if="!layout"
                        icon="regular-eye"
                        :description="$t('ct-experience-studio.detail.preview.placeholder')"
                    />

                    <mt-empty-state
                        v-else-if="!channelId"
                        icon="regular-desktop"
                        :description="$t('ct-experience-studio.detail.preview.missingChannel')"
                    />

                    <mt-empty-state
                        v-else-if="!hasPreviewContext"
                        icon="regular-info-circle"
                        :description="$t('ct-experience-studio.detail.preview.missingEntityContext')"
                    />

                    <mt-empty-state
                        v-else-if="previewLoadError && !hasAnyPreviewFrame"
                        icon="regular-times-circle"
                        :description="previewLoadError"
                    />

                    <div v-else class="ct-experience-studio-preview__iframe-stack">
                        <iframe
                            v-if="getFrameUrl('a')"
                            ref="iframeA"
                            :class="getFrameClass('a')"
                            :src="getFrameUrl('a')"
                            :title="$t('ct-experience-studio.detail.preview.title')"
                            @load="onPreviewFrameLoad('a')"
                        ></iframe>
                        <iframe
                            v-if="getFrameUrl('b')"
                            ref="iframeB"
                            :class="getFrameClass('b')"
                            :src="getFrameUrl('b')"
                            :title="$t('ct-experience-studio.detail.preview.title')"
                            @load="onPreviewFrameLoad('b')"
                        ></iframe>
                    </div>
                </div>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import type ContentSystemPreviewApiService from 'src/core/service/api/content-system-preview.api.service';
import type { ContentElementNode } from 'src/module/ct-experience-studio/types/content-element.types';
import { sanitizeContentElementLayoutForWrite } from 'src/module/ct-experience-studio/util/content-element.util';
import './ct-experience-studio-preview.scss';
type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';
type PreviewMessagePayload = {
    source?: string;
    type?: string;
    elementId?: string | null;
    value?: string | null;
    requestId?: number;
    top?: number;
    left?: number;
} | null;
type PreviewScrollPosition = {
    top: number;
    left: number;
};

const props = defineProps({
    layout: {
        type: Object,
        required: false,
        default: null,
    },
    viewport: {
        type: String,
        required: false,
        default: 'desktop',
    },
    channelId: {
        type: String,
        required: false,
        default: null,
    },
    entityType: {
        type: String,
        required: false,
        default: null,
    },
    entityId: {
        type: String,
        required: false,
        default: null,
    },
    suspendAutoReload: {
        type: Boolean,
        required: false,
        default: false,
    },
    styleOptions: {
        type: Object as PropType<Record<string, ContentSystemStyleOptionSpecification>>,
        required: false,
        default: () => ({}),
    },
});
const emit = defineEmits([
    'select-element',
    'inline-edit-start',
    'inline-edit-change',
    'inline-edit-commit',
    'inline-edit-cancel',
]);

import { type PropType, ref, computed, watch, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const $t = t;
const iframeA = ref(null);
const iframeB = ref(null);

const isPreviewLoading = ref(false);
const previewLoadError = ref(null as string | null);
const iframeAUrl = ref(null as string | null);
const iframeBUrl = ref(null as string | null);
const activeFrame = ref(null as 'a' | 'b' | null);
const loadingFrame = ref(null as 'a' | 'b' | null);
const latestRequestId = ref(0);
const pendingScrollPosition = ref(null as PreviewScrollPosition | null);
const scrollRequestSequence = ref(0);
const debouncedLoadPreview = ref(null as (() => void) | null);
const previewMessageHandler = ref(null as typeof window.onmessage);

const viewportClass = computed(() => {
    return `is--${props.viewport as Viewport}`;
});
const hasPreviewContext = computed(() => {
    return Boolean(props.layout && props.channelId && props.entityType && props.entityId);
});
const hasAnyPreviewFrame = computed(() => {
    return activeFrame.value !== null || loadingFrame.value !== null;
});
const showInitialLoader = computed(() => {
    return isPreviewLoading.value && !hasAnyPreviewFrame.value;
});

const getActiveFrameElement = () => {
    if (activeFrame.value === 'a') {
        return iframeA.value as HTMLIFrameElement | null;
    }

    if (activeFrame.value === 'b') {
        return iframeB.value as HTMLIFrameElement | null;
    }

    return null;
};
const getActiveFrameOrigin = () => {
    const activeFrame = getActiveFrameElement();
    const frameUrl = activeFrame?.getAttribute('src');

    if (!frameUrl) {
        return null;
    }

    try {
        return new URL(frameUrl, window.location.origin).origin;
    } catch {
        return null;
    }
};
const getFrameOrigin = (frame: 'a' | 'b') => {
    const frameUrl = getFrameUrl(frame);

    if (!frameUrl) {
        return null;
    }

    try {
        return new URL(frameUrl, window.location.origin).origin;
    } catch {
        return null;
    }
};
const isTrustedPreviewMessage = (event: MessageEvent) => {
    const activeFrame = getActiveFrameElement();

    if (!activeFrame?.contentWindow || event.source !== activeFrame.contentWindow) {
        return false;
    }

    const activeOrigin = getActiveFrameOrigin();

    if (!activeOrigin) {
        return false;
    }

    return event.origin === activeOrigin;
};
const schedulePreviewReload = () => {
    if (props.suspendAutoReload) {
        return;
    }

    debouncedLoadPreview.value?.();
};
const resetPreviewFrames = () => {
    iframeAUrl.value = null;
    iframeBUrl.value = null;
    activeFrame.value = null;
    loadingFrame.value = null;
    pendingScrollPosition.value = null;
};
const getFrameUrl = (frame: 'a' | 'b') => {
    return frame === 'a' ? iframeAUrl.value : iframeBUrl.value;
};
const getFrameClass = (frame: 'a' | 'b') => {
    const classes = ['ct-experience-studio-preview__iframe'];

    if (activeFrame.value === frame) {
        classes.push('ct-experience-studio-preview__iframe--active');
    } else {
        classes.push('ct-experience-studio-preview__iframe--inactive');
    }

    if (loadingFrame.value === frame) {
        classes.push('ct-experience-studio-preview__iframe--preload');
    }

    return classes;
};
const onPreviewFrameLoad = async (frame: 'a' | 'b') => {
    if (loadingFrame.value !== frame) {
        return;
    }

    const scrollPositionToRestore = pendingScrollPosition.value;

    if (scrollPositionToRestore) {
        await restoreFrameScrollPosition(frame, scrollPositionToRestore);
    }

    if (loadingFrame.value !== frame) {
        return;
    }

    activeFrame.value = frame;
    loadingFrame.value = null;
    pendingScrollPosition.value = null;
};
const captureActiveFrameScrollPosition = () => {
    const activeFrame = getActiveFrameElement();
    const activeFrameWindow = activeFrame?.contentWindow;

    if (!activeFrameWindow) {
        return null;
    }

    try {
        return {
            top: activeFrameWindow.scrollY,
            left: activeFrameWindow.scrollX,
        };
    } catch {
        return null;
    }
};
const requestActiveFrameScrollPosition = () => {
    const directScrollPosition = captureActiveFrameScrollPosition();

    if (directScrollPosition) {
        return Promise.resolve(directScrollPosition);
    }

    const activeFrame = getActiveFrameElement();
    const activeOrigin = getActiveFrameOrigin();
    const activeFrameWindow = activeFrame?.contentWindow;

    if (!activeFrameWindow || !activeOrigin) {
        return Promise.resolve(null);
    }

    const requestId = scrollRequestSequence.value + 1;
    scrollRequestSequence.value = requestId;

    return new Promise((resolve) => {
        let timeoutId: number | null = null;

        const finish = (result: PreviewScrollPosition | null): void => {
            window.removeEventListener('message', onMessage);

            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }

            resolve(result);
        };

        const onMessage = (event: MessageEvent): void => {
            if (!isTrustedPreviewMessage(event)) {
                return;
            }

            const payload = event.data as PreviewMessagePayload;

            if (
                payload?.type !== 'scroll-position' ||
                payload.requestId !== requestId ||
                typeof payload.top !== 'number' ||
                typeof payload.left !== 'number'
            ) {
                return;
            }

            finish({
                top: payload.top,
                left: payload.left,
            });
        };

        window.addEventListener('message', onMessage);
        timeoutId = window.setTimeout(() => finish(null), 250);

        activeFrameWindow.postMessage(
            {
                source: 'ct-experience-studio-admin',
                type: 'capture-scroll',
                requestId,
            },
            activeOrigin,
        );
    });
};
const restoreFrameScrollPosition = (frame: 'a' | 'b', scrollPosition: PreviewScrollPosition) => {
    const frameElement =
        frame === 'a' ? (iframeA.value as HTMLIFrameElement | null) : (iframeB.value as HTMLIFrameElement | null);

    const frameWindow = frameElement?.contentWindow;
    const frameOrigin = getFrameOrigin(frame);

    if (!frameWindow || !frameOrigin) {
        return Promise.resolve();
    }

    const requestId = scrollRequestSequence.value + 1;
    scrollRequestSequence.value = requestId;

    return new Promise((resolve) => {
        let timeoutId: number | null = null;

        const finish = (): void => {
            window.removeEventListener('message', onMessage);

            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }

            resolve();
        };

        const onMessage = (event: MessageEvent): void => {
            const payload = event.data as PreviewMessagePayload;

            if (
                event.source !== frameWindow ||
                event.origin !== frameOrigin ||
                payload?.source !== 'ct-experience-studio-preview' ||
                payload?.type !== 'scroll-restored' ||
                payload.requestId !== requestId
            ) {
                return;
            }

            finish();
        };

        window.addEventListener('message', onMessage);
        timeoutId = window.setTimeout(() => finish(), 250);

        frameWindow.postMessage(
            {
                source: 'ct-experience-studio-admin',
                type: 'restore-scroll',
                requestId,
                top: scrollPosition.top,
                left: scrollPosition.left,
            },
            frameOrigin,
        );
    });
};
const assignLoadingFrame = (url: string) => {
    const targetFrame = activeFrame.value === 'a' ? 'b' : 'a';

    if (targetFrame === 'a') {
        iframeAUrl.value = url;
    } else {
        iframeBUrl.value = url;
    }

    loadingFrame.value = targetFrame;
};
const loadPreview = async () => {
    const previewService = Contena.Service('contentSystemPreviewService') as ContentSystemPreviewApiService;
    const layout = props.layout as { layout?: unknown[] } | null;
    const channelId = props.channelId as string | null;
    const entityType = props.entityType as string | null;
    const entityId = props.entityId as string | null;
    const serializedLayout = Array.isArray(layout?.layout) ? layout.layout : null;

    if (!serializedLayout || !channelId || !entityType || !entityId) {
        resetPreviewFrames();
        previewLoadError.value = null;
        isPreviewLoading.value = false;

        return;
    }

    const requestId = latestRequestId.value + 1;
    latestRequestId.value = requestId;
    isPreviewLoading.value = true;
    previewLoadError.value = null;

    try {
        const styleOptions = props.styleOptions;
        const previewLayout = sanitizeContentElementLayoutForWrite(serializedLayout as ContentElementNode[], styleOptions);

        const previewUrl = await previewService.previewEntityUrl({
            layout: previewLayout,
            entityType,
            entityId,
            channelId,
        });

        if (requestId !== latestRequestId.value) {
            return;
        }

        previewLoadError.value = null;
        pendingScrollPosition.value = await requestActiveFrameScrollPosition();
        assignLoadingFrame(previewUrl);
    } catch {
        if (requestId !== latestRequestId.value) {
            return;
        }

        if (!hasAnyPreviewFrame.value) {
            previewLoadError.value = t('ct-experience-studio.detail.preview.errorLoad');
        }
    } finally {
        if (requestId === latestRequestId.value) {
            isPreviewLoading.value = false;
        }
    }
};

watch(
    () => props.layout?.layout,
    () => {
        schedulePreviewReload();
    },
    { deep: true },
);
watch(
    () => props.channelId,
    () => {
        schedulePreviewReload();
    },
);
watch(
    () => props.entityType,
    () => {
        schedulePreviewReload();
    },
);
watch(
    () => props.entityId,
    () => {
        schedulePreviewReload();
    },
);
watch(
    () => props.suspendAutoReload,
    (nextValue, previousValue) => {
        if (previousValue && !nextValue) {
            debouncedLoadPreview.value?.();
        }
    },
);

debouncedLoadPreview.value = Contena.Utils.debounce(() => {
    void loadPreview();
}, 300);

previewMessageHandler.value = (event: MessageEvent) => {
    const payload = event.data as PreviewMessagePayload;

    if (!payload || payload.source !== 'ct-experience-studio-preview') {
        return;
    }

    if (!isTrustedPreviewMessage(event)) {
        return;
    }

    if (payload.type === 'select-element') {
        emit('select-element', payload.elementId ?? null);
        return;
    }

    if (!payload.elementId) {
        return;
    }

    if (payload.type === 'inline-edit-start') {
        emit('inline-edit-start', {
            elementId: payload.elementId,
        });

        return;
    }

    if (payload.type === 'inline-edit-change' && typeof payload.value === 'string') {
        emit('inline-edit-change', {
            elementId: payload.elementId,
            value: payload.value,
        });

        return;
    }

    if (payload.type === 'inline-edit-commit' && typeof payload.value === 'string') {
        emit('inline-edit-commit', {
            elementId: payload.elementId,
            value: payload.value,
        });

        return;
    }

    if (payload.type === 'inline-edit-cancel') {
        emit('inline-edit-cancel', {
            elementId: payload.elementId,
        });
    }
};
window.addEventListener('message', previewMessageHandler.value);

schedulePreviewReload();

onBeforeUnmount(() => {
    if (previewMessageHandler.value) {
        window.removeEventListener('message', previewMessageHandler.value);
    }
});

swDefinePublic({
    isPreviewLoading,
    previewLoadError,
    iframeAUrl,
    iframeBUrl,
    activeFrame,
    loadingFrame,
    latestRequestId,
    pendingScrollPosition,
    scrollRequestSequence,
    debouncedLoadPreview,
    previewMessageHandler,
    viewportClass,
    hasPreviewContext,
    hasAnyPreviewFrame,
    showInitialLoader,
    getActiveFrameElement,
    getActiveFrameOrigin,
    getFrameOrigin,
    isTrustedPreviewMessage,
    schedulePreviewReload,
    resetPreviewFrames,
    getFrameUrl,
    getFrameClass,
    onPreviewFrameLoad,
    captureActiveFrameScrollPosition,
    requestActiveFrameScrollPosition,
    restoreFrameScrollPosition,
    assignLoadingFrame,
    loadPreview,
});

defineExpose({
    isPreviewLoading,
    previewLoadError,
    iframeAUrl,
    iframeBUrl,
    activeFrame,
    loadingFrame,
    latestRequestId,
    pendingScrollPosition,
    scrollRequestSequence,
    debouncedLoadPreview,
    previewMessageHandler,
    viewportClass,
    hasPreviewContext,
    hasAnyPreviewFrame,
    showInitialLoader,
    getActiveFrameElement,
    getActiveFrameOrigin,
    getFrameOrigin,
    isTrustedPreviewMessage,
    schedulePreviewReload,
    resetPreviewFrames,
    getFrameUrl,
    getFrameClass,
    onPreviewFrameLoad,
    captureActiveFrameScrollPosition,
    requestActiveFrameScrollPosition,
    restoreFrameScrollPosition,
    assignLoadingFrame,
    loadPreview,
});
</script>
