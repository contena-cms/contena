<template>
    <ct-block name="sw_experience_studio_box_spacing_field">
        <div
            class="ct-experience-studio-box-spacing-field mt-field"
            :class="{ 'is--compact': compact, 'is--disabled': disabled }"
        >
            <div v-if="label" class="ct-experience-studio-box-spacing-field__label mt-field__label label">{{ label }}</div>

            <div class="ct-experience-studio-box-spacing-field__frame">
                <div class="ct-experience-studio-box-spacing-field__diagram">
                    <svg
                        v-if="!compact"
                        class="ct-experience-studio-box-spacing-field__guides"
                        viewBox="0 0 100 60"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        <line x1="0" y1="0" x2="34" y2="20.4" />
                        <line x1="100" y1="0" x2="66" y2="20.4" />
                        <line x1="0" y1="60" x2="34" y2="39.6" />
                        <line x1="100" y1="60" x2="66" y2="39.6" />
                    </svg>

                    <svg
                        v-else
                        class="ct-experience-studio-box-spacing-field__guides"
                        viewBox="0 0 100 60"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        <line x1="0" y1="0" x2="36" y2="21.6" />
                        <line x1="100" y1="0" x2="64" y2="21.6" />
                        <line x1="0" y1="60" x2="36" y2="38.4" />
                        <line x1="100" y1="60" x2="64" y2="38.4" />
                    </svg>

                    <div
                        class="ct-experience-studio-box-spacing-field__side ct-experience-studio-box-spacing-field__side--top"
                    >
                        <div class="mt-block-field__block ct-experience-studio-box-spacing-field__input-block">
                            <input
                                :id="getSideInputId('top')"
                                type="text"
                                class="ct-experience-studio-box-spacing-field__input"
                                :value="sides.top"
                                :disabled="disabled || undefined"
                                :aria-label="getSideAriaLabel('top')"
                                @input="onSideInput('top', $event.target.value)"
                            />
                        </div>
                    </div>

                    <div
                        class="ct-experience-studio-box-spacing-field__side ct-experience-studio-box-spacing-field__side--left"
                    >
                        <div class="mt-block-field__block ct-experience-studio-box-spacing-field__input-block">
                            <input
                                :id="getSideInputId('left')"
                                type="text"
                                class="ct-experience-studio-box-spacing-field__input"
                                :value="sides.left"
                                :disabled="disabled || undefined"
                                :aria-label="getSideAriaLabel('left')"
                                @input="onSideInput('left', $event.target.value)"
                            />
                        </div>
                    </div>

                    <div class="ct-experience-studio-box-spacing-field__content">
                        <button
                            type="button"
                            class="ct-experience-studio-box-spacing-field__link-toggle"
                            :class="{ 'is--linked': isLinked }"
                            :disabled="disabled || undefined"
                            :aria-label="
                                isLinked
                                    ? $t('ct-experience-studio.detail.elementSettings.boxSpacingUnlinkSides')
                                    : $t('ct-experience-studio.detail.elementSettings.boxSpacingLinkSides')
                            "
                            :title="
                                isLinked
                                    ? $t('ct-experience-studio.detail.elementSettings.boxSpacingUnlinkSides')
                                    : $t('ct-experience-studio.detail.elementSettings.boxSpacingLinkSides')
                            "
                            @click="onLinkToggle"
                        >
                            <mt-icon
                                :name="isLinked ? 'regular-link-horizontal' : 'regular-link-horizontal-slash'"
                                size="16px"
                            />
                        </button>
                    </div>

                    <div
                        class="ct-experience-studio-box-spacing-field__side ct-experience-studio-box-spacing-field__side--right"
                    >
                        <div class="mt-block-field__block ct-experience-studio-box-spacing-field__input-block">
                            <input
                                :id="getSideInputId('right')"
                                type="text"
                                class="ct-experience-studio-box-spacing-field__input"
                                :value="sides.right"
                                :disabled="disabled || undefined"
                                :aria-label="getSideAriaLabel('right')"
                                @input="onSideInput('right', $event.target.value)"
                            />
                        </div>
                    </div>

                    <div
                        class="ct-experience-studio-box-spacing-field__side ct-experience-studio-box-spacing-field__side--bottom"
                    >
                        <div class="mt-block-field__block ct-experience-studio-box-spacing-field__input-block">
                            <input
                                :id="getSideInputId('bottom')"
                                type="text"
                                class="ct-experience-studio-box-spacing-field__input"
                                :value="sides.bottom"
                                :disabled="disabled || undefined"
                                :aria-label="getSideAriaLabel('bottom')"
                                @input="onSideInput('bottom', $event.target.value)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="helpText" class="ct-experience-studio-box-spacing-field__help-text">{{ helpText }}</p>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { parseBoxSpacing, serializeBoxSpacing, type BoxSpacingSide } from '../../util/box-spacing.util';
import './ct-experience-studio-box-spacing-field.scss';

const props = defineProps({
    modelValue: {
        type: String,
        required: false,
        default: '',
    },
    label: {
        type: String,
        required: false,
        default: '',
    },
    helpText: {
        type: String,
        required: false,
        default: null,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
    compact: {
        type: Boolean,
        required: false,
        default: false,
    },
    fieldKey: {
        type: String,
        required: false,
        default: '',
    },
});
const emit = defineEmits(['update:modelValue']);

import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const $t = t;
const sides = ref(parseBoxSpacing(''));
const isLinked = ref(false);
const lastEmittedValue = ref(null as string | null);

const getSideInputId = (side: BoxSpacingSide) => {
    const normalizedKey = props.fieldKey.replace(/[^a-zA-Z0-9_-]/g, '-');

    return `ct-experience-studio-box-spacing-${normalizedKey}-${side}`;
};
const getSideAriaLabel = (side: BoxSpacingSide) => {
    const sideLabels: Record<BoxSpacingSide, string> = {
        top: t('ct-experience-studio.detail.elementSettings.boxSpacingTop'),
        right: t('ct-experience-studio.detail.elementSettings.boxSpacingRight'),
        bottom: t('ct-experience-studio.detail.elementSettings.boxSpacingBottom'),
        left: t('ct-experience-studio.detail.elementSettings.boxSpacingLeft'),
    };

    const prefix = props.label ? `${props.label} ` : '';

    return `${prefix}${sideLabels[side]}`;
};
const onSideInput = (side: BoxSpacingSide, rawValue: string) => {
    if (isLinked.value) {
        sides.value = {
            top: rawValue,
            right: rawValue,
            bottom: rawValue,
            left: rawValue,
        };
    } else {
        sides.value = {
            ...sides.value,
            [side]: rawValue,
        };
    }

    emitValue();
};
const onLinkToggle = () => {
    if (props.disabled) {
        return;
    }

    if (!isLinked.value) {
        const syncValue = sides.value.top || sides.value.right || sides.value.bottom || sides.value.left || '';

        sides.value = {
            top: syncValue,
            right: syncValue,
            bottom: syncValue,
            left: syncValue,
        };

        emitValue();
    }

    isLinked.value = !isLinked.value;
};
const emitValue = () => {
    const serialized = serializeBoxSpacing(sides.value, {
        linked: isLinked.value,
        explicit: true,
    });

    lastEmittedValue.value = serialized;
    emit('update:modelValue', serialized);
};

watch(
    () => props.modelValue,
    (value) => {
        if (value === lastEmittedValue.value) {
            return;
        }

        lastEmittedValue.value = value;
        sides.value = parseBoxSpacing(value);
    },
    { immediate: true },
);

swDefinePublic({
    sides,
    isLinked,
    lastEmittedValue,
    getSideInputId,
    getSideAriaLabel,
    onSideInput,
    onLinkToggle,
    emitValue,
});

defineExpose({
    sides,
    isLinked,
    lastEmittedValue,
    getSideInputId,
    getSideAriaLabel,
    onSideInput,
    onLinkToggle,
    emitValue,
});
</script>
