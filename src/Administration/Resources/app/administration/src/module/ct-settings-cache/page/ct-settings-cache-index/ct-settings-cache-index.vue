<template>
    <ct-block name="ct_settings_cache_index">
        <ct-page class="ct-settings-cache">
            <template #smart-bar-header>
                <ct-block name="ct_settings_cache_smart_bar_header">
                    <ct-block name="ct_settings_cache_smart_bar_header_title">
                        <h2>
                            <ct-block name="ct_settings_cache_smart_bar_header_title_text">
                                <span>{{ $t('ct-settings.index.title') }}</span>

                                <mt-icon name="regular-chevron-right-xs" size="12px" />

                                <span>{{ $t('ct-settings-cache.general.mainMenuItemGeneral') }}</span>
                            </ct-block>
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_cache_content">
                    <ct-card-view>
                        <mt-card
                            class="ct-settings-cache__card"
                            position-identifier="ct-settings-cache-content"
                            :title="$t('ct-settings-cache.general.mainMenuItemGeneral')"
                            :is-loading="isLoading || componentIsBuilding"
                        >
                            <template #toolbar>
                                <ct-block name="ct_settings_cache_content_toolbar">
                                    <div class="ct-settings-cache__card-toolbar">
                                        <ct-block name="ct_settings_cache_content_toolbar_environment">
                                            <div>
                                                <ct-block name="ct_settings_cache_content_toolbar_environment_heading">
                                                    <p class="ct-settings-cache__card-toolbar-heading">
                                                        {{ $t('ct-settings-cache.toolbar.environment') }}
                                                    </p>
                                                </ct-block>

                                                <ct-block name="ct_settings_cache_content_toolbar_environment_text">
                                                    <p>{{ environmentValue }}</p>
                                                </ct-block>
                                            </div>
                                        </ct-block>

                                        <ct-block name="ct_settings_cache_content_toolbar_http_cache">
                                            <div>
                                                <ct-block name="ct_settings_cache_content_toolbar_http_cache_heading">
                                                    <p class="ct-settings-cache__card-toolbar-heading">
                                                        {{ $t('ct-settings-cache.toolbar.httpCache') }}
                                                    </p>
                                                </ct-block>

                                                <ct-block name="ct_settings_cache_content_toolbar_http_cache_text">
                                                    <p>{{ httpCacheValue }}</p>
                                                </ct-block>
                                            </div>
                                        </ct-block>

                                        <ct-block name="ct_settings_cache_content_toolbar_cache_adapter">
                                            <div>
                                                <ct-block name="ct_settings_cache_content_toolbar_cache_adapter_heading">
                                                    <p class="ct-settings-cache__card-toolbar-heading">
                                                        {{ $t('ct-settings-cache.toolbar.cacheAdapter') }}
                                                    </p>
                                                </ct-block>

                                                <ct-block name="ct_settings_cache_content_toolbar_cache_adapter_text">
                                                    <p>{{ cacheAdapterValue }}</p>
                                                </ct-block>
                                            </div>
                                        </ct-block>
                                    </div>
                                </ct-block>
                            </template>

                            <ct-block name="ct_settings_cache_content_clear_data_cache_row">
                                <ct-card-section divider="bottom">
                                    <ct-container align="center" columns="1fr auto" gap="20px">
                                        <div>
                                            <ct-block name="ct_settings_cache_content_clear_data_cache_row_heading">
                                                <p class="ct-settings-cache__card-section-heading">
                                                    {{ $t('ct-settings-cache.section.clearDataCachesHeadline') }}
                                                </p>
                                            </ct-block>

                                            <ct-block name="ct_settings_cache_content_clear_data_cache_row_text">
                                                <p>{{ $t('ct-settings-cache.section.clearDataCachesText') }}</p>
                                            </ct-block>
                                        </div>

                                        <ct-block name="ct_settings_cache_content_clear_data_cache_row_button">
                                            <mt-button
                                                :is-loading="processes.refreshCache"
                                                :disabled="processes.normalClearCache"
                                                variant="secondary"
                                                @click="clearDataCache"
                                            >
                                                {{ $t('ct-settings-cache.section.clearDataCachesButton') }}
                                            </mt-button>
                                        </ct-block>
                                    </ct-container>
                                </ct-card-section>
                            </ct-block>

                            <ct-block name="ct_settings_cache_content_clear_cache_row">
                                <ct-card-section divider="bottom">
                                    <ct-container align="center" columns="1fr auto" gap="20px">
                                        <div>
                                            <ct-block name="ct_settings_cache_content_clear_cache_row_heading">
                                                <p class="ct-settings-cache__card-section-heading">
                                                    {{ $t('ct-settings-cache.section.clearCachesHeadline') }}
                                                </p>
                                            </ct-block>

                                            <ct-block name="ct_settings_cache_content_clear_cache_row_text">
                                                <p>{{ $t('ct-settings-cache.section.clearCachesText') }}</p>
                                            </ct-block>
                                        </div>

                                        <ct-block name="ct_settings_cache_content_clear_cache_row_button">
                                            <mt-button
                                                variant="secondary"
                                                :is-loading="processes.normalClearCache"
                                                :disabled="processes.refreshCache"
                                                @click="clearCache"
                                            >
                                                {{ $t('ct-settings-cache.section.clearCachesButton') }}
                                            </mt-button>
                                        </ct-block>
                                    </ct-container>
                                </ct-card-section>
                            </ct-block>

                            <ct-block name="ct_settings_cache_content_indexes_row">
                                <ct-card-section class="ct-settings-cache__card-indexes">
                                    <ct-container>
                                        <ct-block name="ct_settings_cache_content_indexes_row_heading">
                                            <p class="ct-settings-cache__card-section-heading">
                                                {{ $t('ct-settings-cache.section.indexesHeadline') }}
                                            </p>
                                        </ct-block>

                                        <ct-block name="ct_settings_cache_content_indexes_row_text">
                                            <p>{{ $t('ct-settings-cache.section.indexesText') }}</p>
                                        </ct-block>
                                    </ct-container>

                                    <ct-container columns="2fr 4fr" gap="10px" justify="end" align="end">
                                        <mt-select
                                            v-model="indexingMethod"
                                            name="indexingMethod"
                                            class="ct-settings-cache__skip-indexers-select"
                                            :label="$t('ct-settings-cache.section.indexingModeLabel')"
                                            :disabled="processes.updateIndexes"
                                            :options="indexingMethodOptions"
                                        />

                                        <ct-block name="ct_settings_cache_content_indexes_row_skip_select">
                                            <mt-select
                                                v-model="indexerSelection"
                                                class="ct-settings-cache__indexers-select"
                                                name="indexerSelection"
                                                enable-multi-selection
                                                :label="
                                                    indexingMethod === 'skip'
                                                        ? $t('ct-settings-cache.section.indexesSkipSelectLabel')
                                                        : $t('ct-settings-cache.section.indexesOnlySelectLabel')
                                                "
                                                :placeholder="
                                                    indexingMethod === 'skip'
                                                        ? $t('ct-settings-cache.section.indexesSkipSelectPlaceholder')
                                                        : $t('ct-settings-cache.section.indexesOnlySelectPlaceholder')
                                                "
                                                :disabled="processes.updateIndexes"
                                                :options="indexerOptions"
                                            >
                                                <template #result-label-property="{ item }">
                                                    <span
                                                        :class="{
                                                            'ct-settings-cache__indexers-option--updater': item.parent,
                                                        }"
                                                    >
                                                        {{ item.label }}
                                                    </span>
                                                </template>
                                            </mt-select>
                                        </ct-block>
                                    </ct-container>

                                    <ct-block name="ct_settings_cache_content_indexes_row_button">
                                        <mt-button
                                            name="updateIndexesButton"
                                            variant="primary"
                                            :is-loading="processes.updateIndexes"
                                            @click="updateIndexes"
                                        >
                                            {{ $t('ct-settings-cache.section.indexesButton') }}
                                        </mt-button>
                                    </ct-block>
                                </ct-card-section>
                            </ct-block>
                        </mt-card>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-settings-cache-index.scss';

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

defineProps({});

import { ref, computed, inject, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationInfo, createNotificationError } = useNotification();

const cacheApiService = inject('cacheApiService');

const componentIsBuilding = ref(true);
const isLoading = ref(true);
const cacheInfo = ref(null);
const processes = ref({
    refreshCache: false,
    normalClearCache: false,
    updateIndexes: false,
});
const indexingMethod = ref('skip');
const indexerSelection = ref([]);

const httpCacheValue = computed(() => {
    // adding validation to prevent the console to throw an error.
    if (cacheInfo.value === null) {
        return '';
    }

    return cacheInfo.value.httpCache
        ? t('ct-settings-cache.toolbar.httpCacheOn')
        : t('ct-settings-cache.toolbar.httpCacheOff');
});
const environmentValue = computed(() => {
    // adding validation to prevent the console to throw an error.
    if (cacheInfo.value === null) {
        return '';
    }

    return cacheInfo.value.environment === 'dev'
        ? t('ct-settings-cache.toolbar.environmentDev')
        : t('ct-settings-cache.toolbar.environmentProd');
});
const cacheAdapterValue = computed(() => {
    // adding validation to prevent the console to throw an error.
    if (cacheInfo.value === null) {
        return '';
    }

    return cacheInfo.value.cacheAdapter;
});
const indexingMethodOptions = computed(() => {
    return [
        {
            label: t('ct-settings-cache.section.indexingModeOptionSkipLabel'),
            value: 'skip',
        },
        {
            label: t('ct-settings-cache.section.indexingModeOptionOnlyLabel'),
            value: 'only',
        },
    ];
});
const indexers = computed(() => cacheInfo.value?.indexers ?? {});
const indexerOptions = computed(() => {
    return Object.entries(indexers.value).flatMap(
        ([
            indexer,
            updaters,
        ]) => [
            {
                label: indexer,
                value: indexer,
                parent: null,
                disabled: false,
            },
            ...updaters.map((updater) => ({
                label: updater,
                value: updater,
                parent: indexer,
                disabled: indexingMethod.value === 'only' || indexerSelection.value.includes(indexer),
            })),
        ],
    );
});

watch(indexingMethod, (value) => {
    if (value !== 'only') {
        return;
    }

    indexerSelection.value = indexerSelection.value.filter((selection) =>
        Object.prototype.hasOwnProperty.call(indexers.value, selection),
    );
});

const createdComponent = () => {
    cacheApiService.info().then((result) => {
        cacheInfo.value = result.data;
        componentIsBuilding.value = false;
        isLoading.value = false;
    });
};
const clearDataCache = () => {
    createNotificationInfo({
        message: t('ct-settings-cache.notifications.clearDataCache.started'),
    });

    processes.value.refreshCache = true;
    cacheApiService
        .delayed()
        .then(() => {
            createNotificationSuccess({
                message: t('ct-settings-cache.notifications.clearDataCache.success'),
            });
        })
        .catch(() => {
            createNotificationError({
                message: t('ct-settings-cache.notifications.clearDataCache.error'),
            });
        })
        .finally(() => {
            processes.value.refreshCache = false;
        });
};
const clearCache = () => {
    createNotificationInfo({
        message: t('ct-settings-cache.notifications.clearCache.started'),
    });

    processes.value.normalClearCache = true;
    cacheApiService
        .clear()
        .then(() => {
            createNotificationSuccess({
                message: t('ct-settings-cache.notifications.clearCache.success'),
            });
        })
        .catch(() => {
            createNotificationError({
                message: t('ct-settings-cache.notifications.clearCache.error'),
            });
        })
        .finally(() => {
            processes.value.normalClearCache = false;
        });
};
const updateIndexes = () => {
    processes.value.updateIndexes = true;

    let skip = [];
    const only = [];

    if (indexingMethod.value === 'skip') {
        skip = indexerSelection.value;
    } else {
        createOnlySelection(only);
    }

    cacheApiService
        .index(skip, only)
        .then(() => {
            createNotificationInfo({
                message: t('ct-settings-cache.notifications.index.started'),
            });
        })
        .finally(() => {
            processes.value.updateIndexes = false;
        });
};
const changeSelection = (selected, name) => {
    if (selected) {
        indexerSelection.value.push(name);

        return;
    }

    const selectedIndex = indexerSelection.value.indexOf(name);
    if (selectedIndex > -1) {
        indexerSelection.value.splice(selectedIndex, 1);
    }
};
const createOnlySelection = (only) => {
    for (const indexerName of Object.keys(indexers.value)) {
        if (indexerSelection.value.indexOf(indexerName) > -1) {
            only.push(indexerName);
        }
    }
};

createdComponent();

ctDefinePublic({
    cacheApiService,
    componentIsBuilding,
    isLoading,
    cacheInfo,
    processes,
    indexingMethod,
    indexerSelection,
    indexers,
    indexerOptions,
    httpCacheValue,
    environmentValue,
    cacheAdapterValue,
    indexingMethodOptions,
    createdComponent,
    clearDataCache,
    clearCache,
    updateIndexes,
    changeSelection,
    createOnlySelection,
});

defineExpose({
    cacheApiService,
    componentIsBuilding,
    isLoading,
    cacheInfo,
    processes,
    indexingMethod,
    indexerSelection,
    indexers,
    indexerOptions,
    httpCacheValue,
    environmentValue,
    cacheAdapterValue,
    indexingMethodOptions,
    createdComponent,
    clearDataCache,
    clearCache,
    updateIndexes,
    changeSelection,
    createOnlySelection,
});
</script>
