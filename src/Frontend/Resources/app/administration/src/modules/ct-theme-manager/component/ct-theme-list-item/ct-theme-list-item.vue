<template>
    <ct-block name="sw_theme_list_item">
        <div class="ct-theme-list-item" :class="componentClasses">
            <ct-block name="sw_theme_list_item_options">
                <slot name="contextMenu"></slot>
            </ct-block>

            <ct-block name="sw_theme_list_item_image">
                <div
                    v-if="theme"
                    class="ct-theme-list-item__image"
                    :style="previewMedia"
                    @click="emitItemClick(theme)"
                ></div>

                <div v-else class="ct-theme-list-item__image is--empty">
                    {{ t('ct-theme-manager.themeListItem.emptyText') }}
                </div>
            </ct-block>

            <ct-block name="sw_theme_list_item_info">
                <div class="ct-theme-list-item__info">
                    <div v-if="theme" class="ct-theme-list-item__status" :class="componentClasses"></div>
                    <div v-if="theme" class="ct-theme-list-item__title" @click="onThemeClick">
                        {{ theme.name }}
                    </div>
                    <mt-icon
                        v-if="theme && theme.technicalName"
                        v-tooltip="lockToolTip"
                        class="ct-theme-list-item__locked"
                        name="regular-lock"
                        size="16"
                    />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import { computed, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import './ct-theme-list-item.scss';

type Theme = Omit<Entity<'theme'>, 'previewMediaId' | 'previewMedia'> & {
    previewMediaId?: string | null;
    previewMedia?: Entity<'media'> | null;
    save: () => unknown;
};

const props = defineProps({
    theme: {
        type: Object as PropType<Theme | null>,
        required: false,
        default: null,
    },

    active: {
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
const emit = defineEmits<{
    'preview-image-change': [theme: Theme];
    'item-click': [theme: Theme | null];
    'theme-delete': [theme: Theme];
}>();

const { t } = useI18n();

const defaultThemeAsset = computed(() => {
    const assetFilter = Contena.Filter.getByName('asset');
    const previewUrl = assetFilter('administration/static/img/theme/default_theme_preview.jpg');

    return `url(${previewUrl})`;
});
const previewMedia = computed(() => {
    if (props.theme?.previewMedia?.id && props.theme.previewMedia.url) {
        return {
            'background-image': `url('${props.theme.previewMedia.url}')`,
            'background-size': 'cover',
        };
    }

    return {
        'background-image': defaultThemeAsset.value,
    };
});
const lockToolTip = computed(() => {
    return {
        showDelay: 100,
        message: t('ct-theme-manager.general.lockedToolTip'),
    };
});
const componentClasses = computed(() => {
    return {
        'is--active': isActive(),
        'is--disabled': props.disabled,
    };
});

const isActive = (): boolean => {
    return (props.theme?.channels && props.theme.channels.length > 0) || props.active;
};
const onChangePreviewImage = (theme: Theme): void => {
    if (props.disabled) {
        return;
    }

    emit('preview-image-change', theme);
};
const onThemeClick = (): void => {
    if (props.disabled) {
        return;
    }

    emit('item-click', props.theme);
};
const onRemovePreviewImage = (theme: Theme): void => {
    theme.previewMediaId = null;
    theme.save();
    theme.previewMedia = null;
};
const onDelete = (theme: Theme): void => {
    if (props.disabled) {
        return;
    }

    emit('theme-delete', theme);
};
const emitItemClick = (item: Theme): void => {
    if (props.disabled) {
        return;
    }

    emit('item-click', item);
};

swDefinePublic({
    previewMedia,
    defaultThemeAsset,
    lockToolTip,
    componentClasses,
    isActive,
    onChangePreviewImage,
    onThemeClick,
    onRemovePreviewImage,
    onDelete,
    emitItemClick,
});

defineExpose({
    previewMedia,
    defaultThemeAsset,
    lockToolTip,
    componentClasses,
    isActive,
    onChangePreviewImage,
    onThemeClick,
    onRemovePreviewImage,
    onDelete,
    emitItemClick,
});
</script>
