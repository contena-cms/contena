<template>
    <ct-block name="ct_settings_country_general">
        <div>
            <ct-block name="ct_settings_country_general_card">
                <mt-card
                    position-identifier="ct-settings-country-detail-general"
                    :title="$t('ct-settings-country.detail.titleCard')"
                    :is-loading="isLoading"
                >
                    <ct-container columns="repeat(auto-fit, minmax(250px, 1fr))" gap="0px 30px">
                        <!-- eslint-disable ct-deprecation-rules/no-twigjs-blocks, vue/attributes-order, vue/no-mutating-props -->
                        <ct-block name="ct_settings_country_general_content_field_name">
                            <mt-text-field
                                v-model="country.name"
                                name="ct-field--country-name"
                                required
                                :disabled="!acl.can('country.editor') || undefined"
                                :label="$t('ct-settings-country.detail.labelName')"
                                :placeholder="placeholder(country, 'name', $t('ct-settings-country.detail.placeholderName'))"
                                :error="countryNameError"
                            />
                        </ct-block>

                        <ct-block name="ct_settings_country_general_content_field_position">
                            <mt-number-field
                                v-model="country.position"
                                name="ct-field--country-position"
                                number-type="int"
                                :disabled="!acl.can('country.editor') || undefined"
                                :label="$t('ct-settings-country.detail.labelPosition')"
                                :placeholder="
                                    placeholder(country, 'position', $t('ct-settings-country.detail.placeholderPosition'))
                                "
                            />
                        </ct-block>

                        <ct-block name="ct_settings_country_general_content_field_iso">
                            <mt-text-field
                                v-model="country.iso"
                                name="ct-field--country-iso"
                                :disabled="!acl.can('country.editor') || undefined"
                                :label="$t('ct-settings-country.detail.labelIso')"
                                :placeholder="placeholder(country, 'iso', $t('ct-settings-country.detail.placeholderIso'))"
                            />
                        </ct-block>

                        <ct-block name="ct_settings_country_general_content_field_iso3">
                            <mt-text-field
                                v-model="country.iso3"
                                name="ct-field--country-iso3"
                                :disabled="!acl.can('country.editor') || undefined"
                                :label="$t('ct-settings-country.detail.labelIso3')"
                                :placeholder="placeholder(country, 'iso3', $t('ct-settings-country.detail.placeholderIso3'))"
                            />
                        </ct-block>
                    </ct-container>
                </mt-card>
            </ct-block>

            <ct-block name="ct_settings_country_general_options_card">
                <mt-card
                    position-identifier="ct-settings-country-general"
                    :title="$t('ct-settings-country.detail.titleOptions')"
                    :is-loading="isLoading"
                >
                    <ct-block name="ct_settings_country_general_content_field_active">
                        <mt-switch
                            v-model="country.active"
                            name="ct-field--country-active"
                            class="ct-settings-country-general__option-items"
                            bordered
                            :disabled="!acl.can('country.editor') || undefined"
                            :label="$t('ct-settings-country.detail.labelActive')"
                        />
                    </ct-block>
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-settings-country-general.scss';
const props = defineProps({
    country: {
        type: Object,
        required: true,
    },
    isLoading: {
        type: Boolean,
        required: true,
    },
});

import { computed, inject } from 'vue';
import { usePlaceholder } from 'src/app/composables/use-placeholder';

const { placeholder } = usePlaceholder();

const acl = inject('acl');

const countryNameError = computed(() => {
    const entity = props.country;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});

ctDefinePublic({
    acl,
    countryNameError,
});
defineExpose({
    acl,
    countryNameError,
});
</script>
