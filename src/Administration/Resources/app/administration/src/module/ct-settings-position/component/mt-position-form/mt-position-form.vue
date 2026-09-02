<template>
    <ct-block name="ct_position_form">
        <div class="mt-position-form">
            <ct-block name="ct_position_form_fields">
                <div class="mt-position-form__grid">
                    <mt-text-field
                        :model-value="positionEntity.name"
                        name="ct-field--position-name"
                        required
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-position.detail.labelName')"
                        :placeholder="placeholder(positionEntity, 'name', '')"
                        @update:model-value="onUpdatePosition('name', $event)"
                    />

                    <mt-text-field
                        :model-value="positionEntity.code"
                        name="ct-field--position-code"
                        required
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-position.detail.labelCode')"
                        @update:model-value="onUpdatePosition('code', $event)"
                    />

                    <mt-textarea
                        class="mt-position-form__description"
                        :model-value="positionEntity.description"
                        name="ct-field--position-description"
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-position.detail.labelDescription')"
                        @update:model-value="onUpdatePosition('description', $event)"
                    />

                    <div class="mt-position-form__settings">
                        <mt-number-field
                            :model-value="positionEntity.position"
                            name="ct-field--position-position"
                            number-type="int"
                            :disabled="disabled || undefined"
                            :label="translate('ct-settings-position.detail.labelPosition')"
                            @update:model-value="onUpdatePosition('position', $event)"
                        />

                        <mt-switch
                            :model-value="positionEntity.active"
                            name="ct-field--position-active"
                            :disabled="disabled || undefined"
                            :label="translate('ct-settings-position.detail.labelActive')"
                            @update:model-value="onUpdatePosition('active', $event)"
                        />
                    </div>
                </div>
            </ct-block>

            <ct-block name="ct_position_form_custom_fields">
                <div v-if="customFieldSets.length > 0" class="mt-position-form__custom-fields">
                    <h3>{{ translate('ct-settings-position.detail.customFieldsTitle') }}</h3>
                    <ct-custom-field-set-renderer :entity="positionEntity" :sets="customFieldSets" :disabled="disabled" />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import { usePlaceholder } from 'src/app/composables/use-placeholder';

type Position = Entity<'position'>;

defineProps({
    positionEntity: {
        type: Object as PropType<Position>,
        required: true,
    },
    customFieldSets: {
        type: Array as PropType<unknown[]>,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits<{
    'update:position': [path: string, value: unknown];
}>();
const { t } = useI18n();
const translate = t;

const { placeholder } = usePlaceholder();
const onUpdatePosition = (path: string, value: unknown): void => emit('update:position', path, value);

ctDefinePublic({
    placeholder,
    onUpdatePosition,
});

defineExpose({ placeholder, onUpdatePosition });
</script>

<style scoped lang="scss">
.mt-position-form {
    &__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--scale-size-16);
    }

    &__description {
        grid-column: 1 / -1;
    }

    &__settings {
        display: grid;
        grid-column: 1 / -1;
        grid-template-columns: minmax(0, 1fr) minmax(180px, 1fr);
        gap: var(--scale-size-16);
        align-items: center;
    }

    &__custom-fields {
        margin-top: var(--scale-size-24);

        h3 {
            margin-bottom: var(--scale-size-16);
        }
    }

    @media screen and (max-width: 760px) {
        &__grid,
        &__settings {
            grid-template-columns: 1fr;
        }
    }
}
</style>
