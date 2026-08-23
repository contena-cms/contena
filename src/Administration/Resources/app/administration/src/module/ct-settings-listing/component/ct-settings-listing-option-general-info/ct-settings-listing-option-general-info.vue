<!-- eslint-disable vue/no-mutating-props -->
<template>
    <ct-block name="sw_settings_listing_option_general_info">
        <mt-card
            class="ct-settings-listing-option-general-info"
            :title="$t('ct-settings-listing.base.general.title')"
            position-identifier="ct-settings-listing-option-general-info"
        >
            <div class="ct-settings-listing-option-general-info__fields">
                <ct-block name="sw_settings_listing_option_general_info_name">
                    <mt-text-field
                        v-model="props.sortingOption.label"
                        :label="$t('ct-settings-listing.base.general.labelName')"
                        :placeholder="$t('ct-settings-listing.base.general.placeholderName')"
                        :disabled="false"
                        :error="labelError"
                        required
                    />
                </ct-block>

                <ct-block name="sw_settings_listing_option_general_info_technical_name">
                    <mt-text-field
                        v-model="props.sortingOption.key"
                        :label="$t('ct-settings-listing.base.general.labelTechnicalName')"
                        :placeholder="$t('ct-settings-listing.base.general.placeholderTechnicalName')"
                        :help-text="$t('ct-settings-listing.base.general.helpTextTechnicalName')"
                        :disabled="props.sortingOption.locked || undefined"
                        :error="technicalNameError"
                        required
                    />
                </ct-block>

                <ct-block name="sw_settings_listing_option_general_info_priority">
                    <mt-number-field
                        v-model="props.sortingOption.priority"
                        :label="$t('ct-settings-listing.general.blogSortingCriteriaGrid.header.priority')"
                        :disabled="props.sortingOption.locked || undefined"
                        number-type="int"
                        :min="0"
                    />
                </ct-block>

                <ct-block name="sw_settings_listing_option_general_info_active">
                    <mt-switch
                        v-model="props.sortingOption.active"
                        :label="$t('ct-settings-listing.base.general.labelActive')"
                        :disabled="isDefaultSorting || props.sortingOption.locked || undefined"
                    />
                </ct-block>
            </div>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';

interface BlogSortingOption {
    id: string;
    label: string;
    key: string;
    priority: number;
    active: boolean;
    locked: boolean;
}

const props = defineProps({
    sortingOption: { type: Object as PropType<BlogSortingOption>, required: true },
    isDefaultSorting: { type: Boolean, default: false },
    labelError: { type: Object as PropType<ContenaError | null>, default: null },
    technicalNameError: { type: Object as PropType<ContenaError | null>, default: null },
});

swDefinePublic({});
</script>

<style scoped>
.ct-settings-listing-option-general-info__fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--scale-size-16, 16px) var(--scale-size-24, 24px);
}

@media (max-width: 720px) {
    .ct-settings-listing-option-general-info__fields {
        grid-template-columns: 1fr;
    }
}
</style>
