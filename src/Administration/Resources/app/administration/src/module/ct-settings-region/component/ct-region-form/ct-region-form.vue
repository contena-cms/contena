<template>
    <ct-block name="sw_region_form">
        <ct-block name="sw_region_form_fields">
            <div class="ct-region-form__grid">
                <mt-text-field
                    :model-value="region.name"
                    name="ct-field--region-name"
                    required
                    :disabled="disabled || undefined"
                    :label="translate('ct-settings-region.detail.labelName')"
                    :placeholder="placeholder(region, 'name', '')"
                    @update:model-value="onUpdateRegion('name', $event)"
                />

                <mt-text-field
                    :model-value="region.shortName"
                    name="ct-field--region-short-name"
                    :disabled="disabled || undefined"
                    :label="translate('ct-settings-region.detail.labelShortName')"
                    @update:model-value="onUpdateRegion('shortName', $event)"
                />

                <mt-text-field
                    :model-value="region.code"
                    name="ct-field--region-code"
                    :disabled="disabled || undefined"
                    :label="translate('ct-settings-region.detail.labelCode')"
                    @update:model-value="onUpdateRegion('code', $event)"
                />

                <ct-data-dictionary-select
                    :model-value="region.type"
                    technical-name="core.region.type"
                    name="ct-field--region-type"
                    required
                    :disabled="disabled || undefined"
                    :label="translate('ct-settings-region.detail.labelType')"
                    :placeholder="translate('ct-settings-region.detail.placeholderType')"
                    @update:model-value="onUpdateRegion('type', $event)"
                />

                <mt-number-field
                    :model-value="region.position"
                    name="ct-field--region-position"
                    number-type="int"
                    :disabled="disabled || undefined"
                    :label="translate('ct-settings-region.detail.labelPosition')"
                    @update:model-value="onUpdateRegion('position', $event)"
                />

                <mt-switch
                    :model-value="region.active"
                    name="ct-field--region-active"
                    :disabled="disabled || undefined"
                    :label="translate('ct-settings-region.detail.labelActive')"
                    @update:model-value="onUpdateRegion('active', $event)"
                />
            </div>
        </ct-block>

        <ct-block name="sw_region_form_custom_fields">
            <div v-if="customFieldSets.length > 0" class="ct-region-form__custom-fields">
                <h3>{{ translate('ct-settings-region.detail.customFieldsTitle') }}</h3>
                <ct-custom-field-set-renderer :entity="region" :sets="customFieldSets" :disabled="disabled" />
            </div>
        </ct-block>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePlaceholder } from 'src/app/composables/use-placeholder';

type Region = Entity<'region'>;

defineProps({
    region: {
        type: Object as PropType<Region>,
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
    'update:region': [path: string, value: unknown];
}>();
const { t } = useI18n();
const translate = t;

const { placeholder } = usePlaceholder();
const onUpdateRegion = (path: string, value: unknown): void => emit('update:region', path, value);

swDefinePublic({
    placeholder,
    onUpdateRegion,
});

defineExpose({ placeholder, onUpdateRegion });
</script>

<style scoped lang="scss">
.ct-region-form {
    &__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--scale-size-16);
    }

    &__custom-fields {
        margin-top: var(--scale-size-24);

        h3 {
            margin-bottom: var(--scale-size-16);
        }
    }
}
</style>
