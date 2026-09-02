<template>
    <ct-block name="ct_settings_country_detail">
        <ct-page class="ct-settings-country-detail">
            <template #smart-bar-header>
                <ct-block name="ct_settings_country_detail_header">
                    <h2>{{ placeholder(country, 'name', translate('ct-settings-country.detail.textHeadline')) }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_country_detail_actions">
                    <ct-block name="ct_settings_country_detail_actions_abort">
                        <mt-button :disabled="isLoading" variant="secondary" size="default" @click="onCancel">
                            {{ translate('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_settings_country_detail_actions_save">
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
                            {{ translate('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="ct_settings_country_detail_language_switch">
                    <ct-language-switch
                        :save-changes-function="saveOnLanguageChange"
                        :abort-change-function="abortOnLanguageChange"
                        @on-change="onChangeLanguage"
                    />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_country_detail_content">
                    <ct-card-view>
                        <div class="ct-settings-country-detail__content-container">
                            <ct-block name="ct_settings_country_detail_content_language_info">
                                <ct-language-info
                                    :entity-description="
                                        placeholder(country, 'name', translate('ct-settings-country.detail.textHeadline'))
                                    "
                                />
                            </ct-block>

                            <ct-block name="ct_settings_country_tabs_header">
                                <mt-tabs
                                    :items="countryTabItems"
                                    :default-item="$route.name"
                                    @new-item-active="onTabChange"
                                />
                                <ct-block name="ct_setting_country_tabs_extension"></ct-block>
                            </ct-block>

                            <div class="ct-settings-country-detail__tab-content">
                                <template v-if="isLoading">
                                    <ct-skeleton />
                                    <ct-skeleton />
                                </template>

                                <template v-else>
                                    <ct-block name="ct_settings_country_tabs_content">
                                        <router-view v-slot="{ Component }">
                                            <component
                                                :is="Component"
                                                :country="country"
                                                :is-loading="isLoading"
                                                @update:country="onUpdateCountry"
                                            />
                                        </router-view>
                                    </ct-block>

                                    <ct-block name="ct_settings_country_detail_custom_field_sets">
                                        <mt-card
                                            v-if="showCustomFields"
                                            position-identifier="ct-settings-country-detail-custom-field-sets"
                                            :title="translate('ct-settings-custom-field.general.mainMenuItemGeneral')"
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
import './ct-settings-country-detail.scss';

defineProps({});

import { ref, computed, inject } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { usePlaceholder } from 'src/app/composables/use-placeholder';
import { usePageTitle } from 'src/app/composables/use-page-title';
import { useDiscardDetailPageChanges } from 'src/app/composables/use-discard-detail-page-changes';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const { placeholder } = usePlaceholder();

const $route = route;
const translate = t;

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');
const customFieldDataProviderService = inject('customFieldDataProviderService');

const country = ref({});
const countryId = ref(null);
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const customFieldSets = ref(null);

const countryRepository = computed(() => {
    return repositoryFactory.create('country');
});
const identifier = computed(() => {
    return placeholder(country.value, 'name');
});
const isNewCountry = computed(() => {
    return typeof country.value.isNew === 'function' ? country.value.isNew() : false;
});
const countryTabItems = computed(() => {
    return [
        {
            label: t('ct-settings-country.page.generalTab'),
            name: isNewCountry.value ? 'ct.settings.country.create.general' : 'ct.settings.country.detail.general',
        },
    ];
});
const allowSave = computed(() => {
    return isNewCountry.value ? acl.can('country.creator') : acl.can('country.editor');
});
const countryNameError = computed(() => {
    const entity = country.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});
const showCustomFields = computed(() => {
    return customFieldSets.value && customFieldSets.value.length > 0;
});

const onTabChange = (routeName) => {
    void router.push({
        name: routeName,
        params: route.params,
    });
};
const createdComponent = () => {
    if (!route.params.id) {
        return;
    }

    countryId.value = route.params.id.toLowerCase();

    void Promise.all([
        loadEntityData(),
        Promise.resolve(loadCustomFieldSets()),
    ]);
};
const loadEntityData = () => {
    if (typeof country.value.isNew === 'function' && country.value.isNew()) {
        return false;
    }
    isLoading.value = true;
    return countryRepository.value
        .get(countryId.value)
        .then((countryValue) => {
            country.value = countryValue;

            isLoading.value = false;
        })
        .catch(() => {
            isLoading.value = false;
        });
};
const loadCustomFieldSets = () => {
    customFieldDataProviderService.getCustomFieldSets('country').then((sets) => {
        customFieldSets.value = sets;
    });
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
};
const onSave = () => {
    isSaveSuccessful.value = false;
    isLoading.value = true;

    return countryRepository.value
        .save(country.value, Contena.Context.api)
        .then(() => {
            loadEntityData();
            isLoading.value = false;
            isSaveSuccessful.value = true;
        })
        .catch(() => {
            isLoading.value = false;
        });
};
const onCancel = () => {
    void router.push({ name: 'ct.settings.country.index' });
};
const abortOnLanguageChange = () => {
    return countryRepository.value.hasChanges(country.value);
};
const saveOnLanguageChange = () => {
    return onSave();
};
const onChangeLanguage = () => {
    loadEntityData();
};
const onUpdateCountry = (path, value) => {
    Contena.Utils.object.set(country.value, path, value);
};

createdComponent();

ctDefinePublic({
    repositoryFactory,
    acl,
    customFieldDataProviderService,
    country,
    countryId,
    isLoading,
    isSaveSuccessful,
    customFieldSets,
    countryRepository,
    identifier,
    isNewCountry,
    countryTabItems,
    allowSave,
    countryNameError,
    showCustomFields,
    onTabChange,
    createdComponent,
    loadEntityData,
    loadCustomFieldSets,
    saveFinish,
    onSave,
    onCancel,
    abortOnLanguageChange,
    saveOnLanguageChange,
    onChangeLanguage,
    onUpdateCountry,
});
usePageTitle(() => identifier.value);
const { discardChanges } = useDiscardDetailPageChanges(route, { country: () => country.value });

defineExpose({
    discardChanges,
    placeholder,
    repositoryFactory,
    acl,
    customFieldDataProviderService,
    country,
    countryId,
    isLoading,
    isSaveSuccessful,
    customFieldSets,
    countryRepository,
    identifier,
    isNewCountry,
    countryTabItems,
    allowSave,
    countryNameError,
    showCustomFields,
    onTabChange,
    createdComponent,
    loadEntityData,
    loadCustomFieldSets,
    saveFinish,
    onSave,
    onCancel,
    abortOnLanguageChange,
    saveOnLanguageChange,
    onChangeLanguage,
    onUpdateCountry,
});
</script>
