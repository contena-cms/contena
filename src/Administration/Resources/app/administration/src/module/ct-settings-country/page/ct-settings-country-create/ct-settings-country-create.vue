<template>
    <ct-block name="sw_settings_country_detail">
        <ct-page class="ct-settings-country-create ct-settings-country-detail">
            <template #smart-bar-header>
                <ct-block name="sw_settings_country_detail_header">
                    <h2>{{ placeholder(country, 'name', $t('ct-settings-country.detail.textHeadline')) }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_country_detail_actions">
                    <ct-block name="sw_settings_country_detail_actions_abort">
                        <mt-button :disabled="isLoading" variant="secondary" size="default" @click="onCancel">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_settings_country_detail_actions_save">
                        <ct-button-process
                            size="default"
                            class="ct-settings-country-detail__save-action"
                            :is-loading="isLoading"
                            :process-success="isSaveSuccessful"
                            :disabled="!country || !allowSave || undefined"
                            variant="primary"
                            @update:process-success="saveFinish"
                            @click.prevent="onSave"
                        >
                            {{ $t('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="sw_settings_country_detail_language_switch">
                    <ct-language-switch disabled />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_country_detail_content">
                    <ct-card-view>
                        <div class="ct-settings-country-detail__content-container">
                            <ct-block name="sw_settings_country_detail_content_language_info">
                                <ct-language-info
                                    :entity-description="
                                        placeholder(country, 'name', $t('ct-settings-country.detail.textHeadline'))
                                    "
                                    is-new-entity
                                />
                            </ct-block>

                            <ct-block name="sw_settings_country_tabs_header">
                                <mt-tabs
                                    :items="countryTabItems"
                                    :default-item="$route.name"
                                    @new-item-active="onTabChange"
                                />
                                <ct-block name="sw_setting_country_tabs_extension"></ct-block>
                            </ct-block>

                            <div class="ct-settings-country-detail__tab-content">
                                <template v-if="isLoading">
                                    <ct-skeleton />
                                    <ct-skeleton />
                                </template>

                                <template v-else>
                                    <ct-block name="sw_settings_country_tabs_content">
                                        <router-view v-slot="{ Component }">
                                            <component
                                                :is="Component"
                                                :country="country"
                                                :is-loading="isLoading"
                                                @update:country="onUpdateCountry"
                                            />
                                        </router-view>
                                    </ct-block>

                                    <ct-block name="sw_settings_country_detail_custom_field_sets">
                                        <mt-card
                                            v-if="showCustomFields"
                                            position-identifier="ct-settings-country-detail-custom-field-sets"
                                            :title="$t('ct-settings-custom-field.general.mainMenuItemGeneral')"
                                            :is-loading="isLoading"
                                        >
                                            <ct-custom-field-set-renderer
                                                :entity="country"
                                                :disabled="!acl.can('country.editor')"
                                                :sets="customFieldSets"
                                            />
                                        </mt-card>
                                    </ct-block>
                                </template>
                            </div>
                        </div>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import '../ct-settings-country-detail/ct-settings-country-detail.scss';

const utils = Contena.Utils;

defineProps({});

import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const { country, countryId, countryRepository, isSaveSuccessful } = Contena.Component.getExtensionParentSetup();
const createdComponent = () => {
    const newCountryId = route.params.id || utils.createId();

    Contena.Context.api.languageId = Contena.Context.api.systemLanguageId;
    country.value = countryRepository.value.create(Contena.Context.api, newCountryId);
    countryId.value = country.value.id;
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
    void router.push({
        name: 'ct.settings.country.detail',
        params: { id: country.value.id },
    });
};

createdComponent();

swDefinePublic({
    createdComponent,
    saveFinish,
});

defineExpose({ createdComponent, saveFinish });
</script>
