<template>
    <ct-block name="ct_channel_modal_grid">
        <mt-loader v-if="isLoading" />
        <div v-else class="ct-channel-modal-grid">
            <button
                v-for="channelType in channelTypes"
                :key="channelType.id"
                class="ct-channel-modal-grid__item"
                type="button"
                @click="onAddChannel(channelType.id)"
            >
                <span class="ct-channel-modal-grid__icon">
                    <mt-icon :name="channelType.iconName || 'regular-server'" size="24px" />
                </span>
                <span class="ct-channel-modal-grid__content">
                    <strong>{{ channelType.translated?.name || channelType.name }}</strong>
                    <span>{{ channelType.translated?.description || channelType.description }}</span>
                </span>
                <mt-button
                    v-tooltip="t('ct-channel.modal.showDetails')"
                    variant="secondary"
                    size="small"
                    square
                    @click.stop="onOpenDetail(channelType.id)"
                >
                    <mt-icon name="regular-info-circle" size="16px" />
                </mt-button>
            </button>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import './ct-channel-modal-grid.scss';

defineProps({});
const emit = defineEmits<{
    'grid-channel-add': [id: string];
    'grid-detail-open': [channelType: Entity<'channel_type'>];
}>();
const { t } = useI18n();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) {
    throw new Error('The repository factory is unavailable.');
}

const channelTypes = ref<Entity<'channel_type'>[]>([]);
const isLoading = ref(false);
const loadChannelTypes = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const criteria = new Contena.Data.Criteria(1, 100);
        criteria.addSorting(Contena.Data.Criteria.sort('name', 'ASC'));
        const result = await repositoryFactory.create('channel_type').search(criteria, Contena.Context.api);
        channelTypes.value = Array.from(result);
    } finally {
        isLoading.value = false;
    }
};
const onAddChannel = (id: string): void => emit('grid-channel-add', id);
const onOpenDetail = (id: string): void => {
    const channelType = channelTypes.value.find((item) => item.id === id);
    if (channelType) {
        emit('grid-detail-open', channelType);
    }
};

void loadChannelTypes();

ctDefinePublic({
    channelTypes,
    isLoading,
    loadChannelTypes,
    onAddChannel,
    onOpenDetail,
});

defineExpose({ channelTypes, isLoading, loadChannelTypes, onAddChannel, onOpenDetail });
</script>
