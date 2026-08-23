<template>
    <ct-block name="sw_settings_message_stats">
        <ct-page class="ct-settings-message-stats">
            <template #smart-bar-header>
                <h2>{{ $t('ct-settings-message-stats.general.mainMenuItemGeneral') }}</h2>
            </template>

            <template #content>
                <ct-card-view>
                    <template v-if="isLoading">
                        <ct-skeleton />
                        <ct-skeleton />
                    </template>

                    <template v-else>
                        <div v-if="isStatsDisabled" class="ct-settings-message-stats__banner-container">
                            <mt-banner
                                variant="attention"
                                :title="$t('ct-settings-message-stats.general.statsDisabledTitle')"
                            >
                                {{ $t('ct-settings-message-stats.general.statsDisabledContent') }}
                            </mt-banner>
                        </div>

                        <mt-card
                            position-identifier="ct-settings-message-stats-overview"
                            :title="$t('ct-settings-message-stats.general.statsCardTitle')"
                            :is-loading="isLoading"
                        >
                            <template #headerRight>
                                <mt-button
                                    :is-loading="isLoading"
                                    :disabled="isLoading"
                                    variant="secondary"
                                    size="small"
                                    @click="loadStats"
                                >
                                    <mt-icon name="regular-sync" size="12px" />
                                    {{ $t('ct-settings-message-stats.general.refreshButton') }}
                                </mt-button>
                            </template>

                            <p class="ct-settings-message-stats__description">
                                {{ $t('ct-settings-message-stats.general.description') }}
                            </p>

                            <div class="ct-settings-message-stats__stats-grid">
                                <div
                                    v-for="stat in statBlocks"
                                    :key="stat.key"
                                    v-tooltip="{ message: stat.tooltip, width: 280 }"
                                    class="ct-settings-message-stats__stat-item"
                                >
                                    <div class="ct-settings-message-stats__stat-item-accent"></div>
                                    <div class="ct-settings-message-stats__stat-content">
                                        <div class="ct-settings-message-stats__stat-header">{{ stat.label }}</div>
                                        <div class="ct-settings-message-stats__stat-value">{{ stat.value }}</div>
                                    </div>
                                    <mt-icon
                                        class="ct-settings-message-stats__stat-icon"
                                        name="solid-question-circle"
                                        size="16"
                                        color="#189eff"
                                    />
                                </div>
                            </div>

                            <hr class="ct-settings-message-stats__divider" />

                            <div v-if="hasStats" class="ct-settings-message-stats__message-types">
                                <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
                                <ct-data-grid
                                    :data-source="sortedMessageTypeStats"
                                    :columns="columns"
                                    :show-selection="false"
                                    :show-actions="false"
                                    :plain-appearance="true"
                                >
                                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                                    <template #column-type="slotProps">
                                        <div>{{ slotProps.item.type }}</div>
                                    </template>
                                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                                    <template #column-count="slotProps">
                                        <div>{{ slotProps.item.count }}</div>
                                    </template>
                                </ct-data-grid>
                            </div>

                            <div v-else>
                                <mt-empty-state
                                    :headline="$t('ct-settings-message-stats.general.emptyStateTitle')"
                                    :description="$t('ct-settings-message-stats.general.emptyStateSubline')"
                                    icon="regular-bars-square"
                                />
                                <!-- mt-empty-state has no button slot yet. -->
                                <mt-button
                                    variant="primary"
                                    class="ct-settings-message-stats__empty-state-button"
                                    @click="loadStats"
                                >
                                    <mt-icon name="regular-sync" size="12px" />
                                    {{ $t('ct-settings-message-stats.general.refreshButton') }}
                                </mt-button>
                            </div>
                        </mt-card>
                    </template>
                </ct-card-view>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { MessageStatsResponse } from 'src/core/service/api/message-stats.api.service';
import type MessageStatsApiService from 'src/core/service/api/message-stats.api.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-settings-message-stats.scss';

interface Column {
    property: string;
    label: string;
    align?: string;
}

defineProps({});
const { createNotificationError } = useNotification();

const messageStatsService = inject<MessageStatsApiService>('messageStatsService');
const i18n = useI18n();

if (!messageStatsService) {
    throw new Error('The message stats service is unavailable.');
}

const isLoading = ref(false);
const statsResponse = ref<MessageStatsResponse | null>(null);
const columns: Column[] = [
    { property: 'count', label: 'ct-settings-message-stats.general.count', align: 'right' },
    { property: 'type', label: 'ct-settings-message-stats.general.type' },
];
const statsData = computed(() => statsResponse.value?.stats ?? null);
const hasStats = computed(
    () => statsResponse.value?.enabled === true && statsData.value !== null && statsData.value.totalMessagesProcessed > 0,
);
const isStatsDisabled = computed(() => statsResponse.value?.enabled === false);
const formattedProcessedSince = computed(() => {
    if (!statsData.value?.processedSince) {
        return '';
    }

    return Contena.Utils.format.date(statsData.value.processedSince, {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        second: 'numeric',
    });
});
const formattedAverageTime = computed(() => {
    if (!statsData.value?.averageTimeInQueue) {
        return '';
    }

    return `${statsData.value.averageTimeInQueue.toFixed(2)}${i18n.t('ct-settings-message-stats.general.seconds')}`;
});
const statBlocks = computed(() => {
    const emptyValue = '—';

    return [
        {
            key: 'totalMessages',
            label: i18n.t('ct-settings-message-stats.general.totalMessages'),
            value: hasStats.value ? statsData.value?.totalMessagesProcessed : emptyValue,
            tooltip: i18n.t('ct-settings-message-stats.general.totalMessagesHelp'),
        },
        {
            key: 'averageTime',
            label: i18n.t('ct-settings-message-stats.general.averageTime'),
            value: hasStats.value ? formattedAverageTime.value : emptyValue,
            tooltip: i18n.t('ct-settings-message-stats.general.averageTimeHelp'),
        },
        {
            key: 'processingWindow',
            label: i18n.t('ct-settings-message-stats.general.processingWindow'),
            value: hasStats.value ? formattedProcessedSince.value : emptyValue,
            tooltip: i18n.t('ct-settings-message-stats.general.processingWindowHelp'),
        },
    ];
});
const sortedMessageTypeStats = computed(() => {
    if (!statsData.value?.messageTypeStats) {
        return [];
    }

    return [...statsData.value.messageTypeStats].sort((a, b) => b.count - a.count);
});

function createdComponent(): void {
    void loadStats();
}

async function loadStats(): Promise<void> {
    isLoading.value = true;

    try {
        statsResponse.value = await messageStatsService.getStats();
    } catch (error) {
        createNotificationError({
            title: i18n.t('ct-settings-message-stats.general.errorTitle'),
            message:
                error instanceof Error ? error.message : i18n.t('global.notification.notificationLoadingDataErrorMessage'),
        });
    } finally {
        isLoading.value = false;
    }
}

createdComponent();

swDefinePublic({
    messageStatsService,
    isLoading,
    statsResponse,
    columns,
    statsData,
    hasStats,
    isStatsDisabled,
    formattedProcessedSince,
    formattedAverageTime,
    statBlocks,
    sortedMessageTypeStats,
    createdComponent,
    loadStats,
});

defineExpose({
    messageStatsService,
    isLoading,
    statsResponse,
    columns,
    statsData,
    hasStats,
    isStatsDisabled,
    formattedProcessedSince,
    formattedAverageTime,
    statBlocks,
    sortedMessageTypeStats,
    createdComponent,
    loadStats,
});
</script>
