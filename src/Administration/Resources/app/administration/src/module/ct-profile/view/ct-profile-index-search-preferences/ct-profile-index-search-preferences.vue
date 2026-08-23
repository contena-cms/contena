<template>
    <ct-block name="sw_profile_index_search_preferences">
        <div class="ct-profile-index-search-preferences">
            <ct-block name="sw_profile_index_search_preferences_search_behavior">
                <mt-card
                    class="ct-profile-index-search-preferences-search-behavior"
                    position-identifier="ct-profile-index-search-preferences-search-behavior"
                    :title="$t('ct-profile.tabSearchPreferences.cardSearchBehavior.title')"
                    :subtitle="$t('ct-profile.tabSearchPreferences.cardSearchBehavior.subtitle')"
                    :is-loading="isLoading"
                >
                    <ct-container columns="1fr 1fr" gap="0 32px">
                        <mt-number-field
                            v-model="minSearchTermLength"
                            :label="$t('ct-profile.tabSearchPreferences.cardSearchBehavior.labelMinSearchTermLength')"
                            :min="1"
                            :max="10"
                            :step="1"
                            :disabled="isLoading"
                        />
                    </ct-container>
                </mt-card>
            </ct-block>

            <ct-block name="sw_profile_index_search_preferences_searchable_elements">
                <mt-card
                    class="ct-profile-index-search-preferences-searchable-elements"
                    position-identifier="ct-profile-index-search-preferences"
                    :title="$t('ct-profile.tabSearchPreferences.cardSearchContent.title')"
                    :subtitle="$t('ct-profile.tabSearchPreferences.cardSearchContent.subtitle')"
                    :is-loading="isLoading"
                >
                    <ct-block name="sw_profile_index_search_preferences_searchable_elements_content">
                        <ct-container v-if="searchPreferences.length > 0" rows="auto auto auto" gap="24px">
                            <ct-block name="sw_profile_index_search_preferences_searchable_elements_header">
                                <ct-container columns="auto auto auto 1fr" gap="8px">
                                    <ct-block name="sw_profile_index_search_preferences_searchable_elements_button_select">
                                        <mt-button
                                            class="ct-profile-index-search-preferences-searchable-elements__button-select-all"
                                            variant="secondary"
                                            @click="onSelect(true)"
                                        >
                                            {{ $t('ct-profile.tabSearchPreferences.cardSearchContent.buttonSelect') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="sw_profile_index_search_preferences_searchable_elements_button_deselect">
                                        <mt-button
                                            class="ct-profile-index-search-preferences-searchable-elements__button-deselect-all"
                                            variant="secondary"
                                            @click="onSelect(false)"
                                        >
                                            {{ $t('ct-profile.tabSearchPreferences.cardSearchContent.buttonDeselect') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="sw_profile_index_search_preferences_searchable_elements_button_reset">
                                        <mt-button
                                            class="ct-profile-index-search-preferences-searchable-elements__button-reset-to-default"
                                            variant="secondary"
                                            @click="onReset"
                                        >
                                            {{ $t('ct-profile.tabSearchPreferences.cardSearchContent.buttonReset') }}
                                        </mt-button>
                                    </ct-block>
                                </ct-container>
                            </ct-block>

                            <ct-block name="sw_profile_index_search_preferences_searchable_elements_body">
                                <div class="ct-profile-index-search-preferences-searchable-elements__entity-container">
                                    <ul
                                        v-for="searchPreference in searchPreferences"
                                        :key="searchPreference.entityName"
                                        class="ct-profile-index-search-preferences-searchable-elements__entity"
                                    >
                                        <li class="ct-profile-index-search-preferences-searchable-elements__entity-field">
                                            <!-- eslint-disable vue/attributes-order -->
                                            <mt-checkbox
                                                v-model:checked="searchPreference._searchable"
                                                :label="getModuleTitle(searchPreference.entityName)"
                                                name="ct-field--searchPreference-_searchable"
                                                @update:checked="onChangeSearchPreference(searchPreference)"
                                            />
                                            <ul class="ct-profile-index-search-preferences-searchable-elements__entity">
                                                <li
                                                    v-for="field in searchPreference.fields"
                                                    :key="field.fieldName"
                                                    class="ct-profile-index-search-preferences-searchable-elements__entity-field"
                                                >
                                                    <mt-checkbox
                                                        v-model:checked="field._searchable"
                                                        name="ct-field--field-_searchable"
                                                        :label="
                                                            $t(
                                                                `ct-profile.tabSearchPreferences.modules.${searchPreference.entityName}.${field.fieldName}`,
                                                            )
                                                        "
                                                        :disabled="!searchPreference._searchable"
                                                    />
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>
                                </div>
                            </ct-block>
                        </ct-container>
                    </ct-block>
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
const { Module, Store } = Contena;

defineProps({});

import { ref, computed, inject, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationError } = useNotification();

const searchPreferencesService = inject('searchPreferencesService');
const searchRankingService = inject('searchRankingService');

const isLoading = ref(false);

const minSearchTermLength = computed({
    get: () => {
        return Store.get('swProfile').minSearchTermLength;
    },
    set: (minSearchTermLength) => {
        Store.get('swProfile').setMinSearchTermLength(minSearchTermLength);
    },
});
const searchPreferences = computed({
    get: () => {
        return Store.get('swProfile').searchPreferences;
    },
    set: (searchPreferences) => {
        Store.get('swProfile').searchPreferences = searchPreferences;
    },
});
const userSearchPreferences = computed({
    get: () => {
        return Store.get('swProfile').userSearchPreferences;
    },
    set: (userSearchPreferences) => {
        Store.get('swProfile').userSearchPreferences = userSearchPreferences;
    },
});
const defaultSearchPreferences = computed(() => {
    const defaultSearchPreferences = searchPreferencesService.getDefaultSearchPreferences();

    if (userSearchPreferences.value === null) {
        return defaultSearchPreferences;
    }

    const mergedPreferences = [];

    defaultSearchPreferences.forEach((defaultPref) => {
        const prefKey = Object.keys(defaultPref)[0];
        const userPref = userSearchPreferences.value.find((item) => Object.keys(item)[0] === prefKey);

        if (!userPref) {
            mergedPreferences.push(defaultPref);
            return;
        }

        const userPrefValue = userPref[prefKey];
        const defaultPrefValue = defaultPref[prefKey];

        // Merge values from default into user preferences
        Object.keys(defaultPrefValue).forEach((prop) => {
            if (!userPrefValue.hasOwnProperty(prop)) {
                userPrefValue[prop] = defaultPrefValue[prop];
            }
        });

        // Remove values from user preferences that are not in default
        Object.keys(userPrefValue).forEach((prop) => {
            if (!defaultPrefValue.hasOwnProperty(prop)) {
                delete userPrefValue[prop];
            }
        });

        mergedPreferences.push({ [prefKey]: userPrefValue });
    });

    return mergedPreferences;
});

const createdComponent = async () => {
    await Promise.all([
        getMinSearchTermLength(),
        getDataSource(),
    ]);

    addEventListeners();
};
const beforeDestroyComponent = () => {
    removeEventListeners();
};
const getMinSearchTermLength = async () => {
    isLoading.value = true;

    try {
        const minSearchTermLength = await searchRankingService.getMinSearchTermLength();
        Contena.Store.get('swProfile').setMinSearchTermLength(minSearchTermLength);
    } catch (error) {
        createNotificationError({ message: error.message });
    } finally {
        isLoading.value = false;
    }
};
const getDataSource = async () => {
    isLoading.value = true;

    try {
        userSearchPreferences.value = await searchPreferencesService.getUserSearchPreferences();
        searchPreferences.value = searchPreferencesService.processSearchPreferences(defaultSearchPreferences.value);
    } catch (error) {
        createNotificationError({ message: error.message });
        searchPreferences.value = [];
        userSearchPreferences.value = null;
    } finally {
        isLoading.value = false;
    }
};
const addEventListeners = () => {
    Contena.Utils.EventBus.on('ct-search-preferences-modal-close', onSearchPreferencesModalClose);
};
const removeEventListeners = () => {
    Contena.Utils.EventBus.off('ct-search-preferences-modal-close', onSearchPreferencesModalClose);
};
const onSearchPreferencesModalClose = () => {
    void getDataSource();
};
const getModuleTitle = (entityName) => {
    const module = Module.getModuleByEntityName(entityName);

    return t(module?.manifest.title);
};
const onChangeSearchPreference = (searchPreference) => {
    if (searchPreference._searchable && searchPreference.fields.every((field) => !field._searchable)) {
        searchPreference.fields.forEach((field) => {
            field._searchable = true;
        });
    }
};
const onSelect = (event) => {
    searchPreferences.value.forEach((searchPreference) => {
        searchPreference._searchable = event;

        searchPreference.fields.forEach((field) => {
            field._searchable = event;
        });
    });
};
const onReset = () => {
    const defaultSearchPreferences = searchPreferencesService.getDefaultSearchPreferences();
    const toReset = searchPreferencesService.processSearchPreferences(defaultSearchPreferences);

    searchPreferences.value.forEach((searchPreference, index) => {
        toReset.forEach((item) => {
            if (item.entityName === searchPreference.entityName) {
                resetSearchPreference(item, searchPreferences.value[index]);
            }
        });
    });
};
const resetSearchPreference = (toReset, searchPreference) => {
    searchPreference._searchable = toReset._searchable;

    searchPreference.fields = searchPreference.fields.map((field) => {
        return toReset.fields.find((item) => item.fieldName === field.fieldName) || field;
    });
};

void createdComponent();

onBeforeUnmount(() => {
    beforeDestroyComponent();
});

swDefinePublic({
    searchPreferencesService,
    searchRankingService,
    isLoading,
    minSearchTermLength,
    searchPreferences,
    userSearchPreferences,
    defaultSearchPreferences,
    createdComponent,
    beforeDestroyComponent,
    getMinSearchTermLength,
    getDataSource,
    addEventListeners,
    removeEventListeners,
    getModuleTitle,
    onChangeSearchPreference,
    onSelect,
    onReset,
    resetSearchPreference,
});

defineExpose({
    searchPreferencesService,
    searchRankingService,
    isLoading,
    minSearchTermLength,
    searchPreferences,
    userSearchPreferences,
    defaultSearchPreferences,
    createdComponent,
    beforeDestroyComponent,
    getMinSearchTermLength,
    getDataSource,
    addEventListeners,
    removeEventListeners,
    getModuleTitle,
    onChangeSearchPreference,
    onSelect,
    onReset,
    resetSearchPreference,
});
</script>
