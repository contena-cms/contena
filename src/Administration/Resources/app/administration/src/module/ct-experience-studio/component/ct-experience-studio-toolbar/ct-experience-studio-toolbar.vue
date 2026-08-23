<template>
    <ct-block name="sw_experience_studio_toolbar">
        <div class="ct-experience-studio-toolbar">
            <ct-block name="sw_experience_studio_toolbar_left">
                <div class="ct-experience-studio-toolbar__left">
                    <div class="ct-experience-studio-toolbar__page-info">
                        <ct-block name="sw_experience_studio_toolbar_back_button">
                            <a
                                class="ct-experience-studio-toolbar__back-btn"
                                role="link"
                                tabindex="0"
                                :aria-label="$t('global.default.back')"
                                @click="onBack"
                                @keyup.enter="onBack"
                            >
                                <mt-icon name="regular-times" />
                            </a>
                        </ct-block>

                        <ct-block name="sw_experience_studio_toolbar_page_name">
                            <h2 class="ct-experience-studio-toolbar__page-name">
                                {{ layoutName }}
                            </h2>
                        </ct-block>
                    </div>

                    <div class="ct-experience-studio-toolbar__history-actions">
                        <button
                            class="ct-experience-studio-toolbar__history-action"
                            type="button"
                            :aria-label="$t('ct-experience-studio.detail.toolbar.undo')"
                            :disabled="!allowSave || !canUndo || undefined"
                            @click="onUndo"
                        >
                            <mt-icon name="regular-undo" size="18px" />
                        </button>

                        <button
                            class="ct-experience-studio-toolbar__history-action"
                            type="button"
                            :aria-label="$t('ct-experience-studio.detail.toolbar.redo')"
                            :disabled="!allowSave || !canRedo || undefined"
                            @click="onRedo"
                        >
                            <mt-icon name="regular-redo" size="18px" />
                        </button>
                    </div>
                </div>
            </ct-block>

            <ct-block name="sw_experience_studio_toolbar_center">
                <div class="ct-experience-studio-toolbar__center">
                    <div class="ct-experience-studio-toolbar__device-actions">
                        <ct-block name="sw_experience_studio_toolbar_device_actions_mobile">
                            <mt-icon
                                name="regular-mobile"
                                :class="{ 'is--active': currentViewport === 'mobile' }"
                                @click="onViewportChange('mobile')"
                            />
                        </ct-block>

                        <ct-block name="sw_experience_studio_toolbar_device_actions_tablet_landscape">
                            <mt-icon
                                name="regular-tablet"
                                :class="{ 'is--active': currentViewport === 'tablet-landscape' }"
                                @click="onViewportChange('tablet-landscape')"
                            />
                        </ct-block>

                        <ct-block name="sw_experience_studio_toolbar_device_actions_desktop">
                            <mt-icon
                                name="regular-desktop"
                                :class="{ 'is--active': currentViewport === 'desktop' }"
                                @click="onViewportChange('desktop')"
                            />
                        </ct-block>
                    </div>
                </div>
            </ct-block>

            <ct-block name="sw_experience_studio_toolbar_right">
                <div class="ct-experience-studio-toolbar__right">
                    <ct-block name="sw_experience_studio_toolbar_entity_select">
                        <div v-if="showPreviewEntitySelect" class="ct-experience-studio-toolbar__entity-field">
                            <label class="ct-experience-studio-toolbar__field-label">
                                {{ $t('ct-experience-studio.detail.toolbar.previewEntity') }}
                            </label>

                            <mt-entity-select
                                v-if="previewEntityType"
                                class="ct-experience-studio-toolbar__entity-select"
                                :entity="previewEntityType"
                                small
                                :model-value="previewEntityId"
                                :placeholder="$t('ct-experience-studio.detail.toolbar.previewEntityPlaceholder')"
                                :disabled="isLoading || !previewEntityType || undefined"
                                @update:model-value="onPreviewEntityIdChange"
                            />
                            <mt-select
                                v-else
                                class="ct-experience-studio-toolbar__entity-select"
                                small
                                :model-value="null"
                                :options="[]"
                                :placeholder="$t('ct-experience-studio.detail.toolbar.previewEntityPlaceholder')"
                                disabled
                            />
                        </div>
                    </ct-block>

                    <ct-block name="sw_experience_studio_toolbar_channel_select">
                        <div class="ct-experience-studio-toolbar__channel-field">
                            <label class="ct-experience-studio-toolbar__field-label">
                                {{ $t('ct-experience-studio.detail.toolbar.previewChannel') }}
                            </label>

                            <mt-entity-select
                                class="ct-experience-studio-toolbar__channel-select"
                                entity="channel"
                                :repository="frontendChannelRepositoryFactory"
                                small
                                :model-value="previewChannelId"
                                :placeholder="$t('ct-experience-studio.detail.toolbar.previewChannelPlaceholder')"
                                :disabled="isLoading || undefined"
                                @update:model-value="onPreviewChannelChange"
                            />
                        </div>
                    </ct-block>

                    <ct-block name="sw_experience_studio_toolbar_assignment_actions">
                        <mt-button
                            variant="secondary"
                            :disabled="!allowAssign || isLoading || isAssignmentLoading || undefined"
                            :is-loading="isAssignmentLoading"
                            @click="onAssign"
                        >
                            {{
                                $t(
                                    isAssigned
                                        ? 'ct-experience-studio.detail.toolbar.changeAssignment'
                                        : 'ct-experience-studio.detail.toolbar.assign',
                                )
                            }}
                        </mt-button>

                        <mt-button
                            v-if="isAssigned"
                            variant="secondary"
                            square
                            :aria-label="$t('ct-experience-studio.detail.toolbar.unassign')"
                            :disabled="!allowAssign || isLoading || isAssignmentLoading || undefined"
                            @click="onUnassign"
                        >
                            <mt-icon name="regular-trash" size="16px" />
                        </mt-button>
                    </ct-block>

                    <mt-button
                        variant="primary"
                        :disabled="!allowSave || isLoading || undefined"
                        :is-loading="isLoading"
                        @click="onSave"
                    >
                        {{ $t('ct-experience-studio.detail.toolbar.save') }}
                    </mt-button>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import type CriteriaType from 'src/core/data/criteria.data';
import { getFrontendChannelCriteria } from 'src/module/ct-experience-studio/util/channel-criteria.util';
import './ct-experience-studio-toolbar.scss';
type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

const props = defineProps({
    layout: {
        type: Object,
        required: false,
        default: null,
    },
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
    currentViewport: {
        type: String,
        required: false,
        default: 'desktop',
    },
    allowSave: {
        type: Boolean,
        required: false,
        default: false,
    },
    previewChannelId: {
        type: String,
        required: false,
        default: null,
    },
    previewEntityType: {
        type: String,
        required: false,
        default: null,
    },
    previewEntityId: {
        type: String,
        required: false,
        default: null,
    },
    showPreviewEntitySelect: {
        type: Boolean,
        required: false,
        default: true,
    },
    canUndo: {
        type: Boolean,
        required: false,
        default: false,
    },
    canRedo: {
        type: Boolean,
        required: false,
        default: false,
    },
    allowAssign: {
        type: Boolean,
        required: false,
        default: false,
    },
    isAssigned: {
        type: Boolean,
        required: false,
        default: false,
    },
    isAssignmentLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'back',
    'viewport-change',
    'save',
    'preview-channel-change',
    'preview-entity-id-change',
    'undo',
    'redo',
    'assign',
    'unassign',
]);

import { computed } from 'vue';

const repositoryFactory = Contena.Service('repositoryFactory');
const layoutName = computed(() => {
    const layout = props.layout as { name?: string } | null;

    return layout?.name ?? '';
});
const channelCriteria = computed(() => {
    return getFrontendChannelCriteria();
});
const frontendChannelRepositoryFactory = () => {
    const repository = repositoryFactory.create('channel');

    return new Proxy(repository, {
        get(target, property, receiver) {
            if (property === 'search') {
                return (criteria: CriteriaType, context?: typeof Contena.Context.api) => {
                    criteria.filters.push(...channelCriteria.value.filters);

                    return target.search(criteria, context);
                };
            }

            const value = Reflect.get(target, property, receiver);

            return typeof value === 'function' ? value.bind(target) : value;
        },
    });
};

const onBack = () => {
    emit('back');
};
const onViewportChange = (viewport: Viewport) => {
    emit('viewport-change', viewport);
};
const onPreviewChannelChange = (channelId: string | null) => {
    emit('preview-channel-change', channelId);
};
const onPreviewEntityIdChange = (entityId: string | null) => {
    emit('preview-entity-id-change', entityId);
};
const onSave = () => {
    emit('save');
};
const onUndo = () => {
    emit('undo');
};
const onRedo = () => {
    emit('redo');
};
const onAssign = () => {
    emit('assign');
};
const onUnassign = () => {
    emit('unassign');
};

swDefinePublic({
    layoutName,
    channelCriteria,
    frontendChannelRepositoryFactory,
    onBack,
    onViewportChange,
    onPreviewChannelChange,
    onPreviewEntityIdChange,
    onSave,
    onUndo,
    onRedo,
    onAssign,
    onUnassign,
});

defineExpose({
    layoutName,
    channelCriteria,
    frontendChannelRepositoryFactory,
    onBack,
    onViewportChange,
    onPreviewChannelChange,
    onPreviewEntityIdChange,
    onSave,
    onUndo,
    onRedo,
    onAssign,
    onUnassign,
});
</script>
