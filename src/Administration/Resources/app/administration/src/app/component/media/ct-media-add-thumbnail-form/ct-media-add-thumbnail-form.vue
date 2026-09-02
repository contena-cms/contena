<template>
    <ct-block name="ct_media_add_thumbnail_form">
        <div class="ct-media-add-thumbnail-form">
            <ct-block name="ct_media_add_thumbnail_form_form_container">
                <div class="ct-media-add-thumbnail-form__form-container">
                    <ct-block name="ct_media_add_thumbnail_form_width">
                        <mt-number-field
                            v-model="width"
                            class="ct-media-add-thumbnail-form__input-width"
                            :label="$t('global.ct-media-add-thumbnail-form.labelMaximumSize')"
                            :min="1"
                            :step="1"
                            :validation="width > 0"
                            @input-change="widthInputChanged"
                        />
                    </ct-block>

                    <ct-block name="ct_media_add_thumbnail_form_lock_button">
                        <button class="ct-media-add-thumbnail-form__lock" :class="lockedButtonClass" @click="onLockSwitch">
                            <mt-icon :name="isLocked ? 'regular-lock' : 'regular-lock-open'" size="16px" />
                        </button>
                    </ct-block>

                    <ct-block name="ct_media_add_thumbnail_form_height">
                        <mt-number-field
                            v-model="height"
                            class="ct-media-add-thumbnail-form__input-height"
                            :min="1"
                            :validation="height > 0"
                            :step="1"
                            :disabled="isLocked"
                            @input-change="heightInputChanged"
                        />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_media_add_thumbnail_add_button">
                <mt-button
                    class="ct-media-folder-settings__add-thumbnail-size-action"
                    :disabled="disabled || width === null || height === null || width === 0 || height === 0"
                    variant="secondary"
                    @click="onAdd"
                >
                    {{ $t('global.default.add') }}
                </mt-button>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-add-thumbnail-form.scss';

defineProps({
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'thumbnail-form-size-add',
    'on-input',
]);

import { ref, computed } from 'vue';

const width = ref(null);
const height = ref(null);
const isLocked = ref(true);

const lockedButtonClass = computed(() => {
    return {
        'is--locked': isLocked.value,
    };
});

const onLockSwitch = () => {
    isLocked.value = !isLocked.value;
};
const onAdd = () => {
    emit('thumbnail-form-size-add', {
        width: width.value,
        height: height.value,
    });
    width.value = null;
    height.value = null;
};
const widthInputChanged = (value) => {
    if (isLocked.value) {
        height.value = value;
    }
    width.value = value;
    inputChanged();
};
const heightInputChanged = (value) => {
    height.value = value;
    inputChanged();
};
function inputChanged() {
    emit('on-input', {
        width: width.value ?? 0,
        height: height.value ?? 0,
    });
}

ctDefinePublic({
    width,
    height,
    isLocked,
    lockedButtonClass,
    onLockSwitch,
    onAdd,
    widthInputChanged,
    heightInputChanged,
    inputChanged,
});

defineExpose({
    width,
    height,
    isLocked,
    lockedButtonClass,
    onLockSwitch,
    onAdd,
    widthInputChanged,
    heightInputChanged,
    inputChanged,
});
</script>
