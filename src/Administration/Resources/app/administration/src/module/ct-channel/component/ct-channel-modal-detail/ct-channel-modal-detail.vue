<template>
    <ct-block name="sw_channel_modal_detail">
        <div class="ct-channel-modal-detail">
            <div class="ct-channel-modal-detail__header">
                <span class="ct-channel-modal-detail__icon">
                    <mt-icon :name="detailType.iconName || 'regular-server'" size="28px" />
                </span>
                <div>
                    <h3>{{ detailType.translated?.name || detailType.name }}</h3>
                    <span>{{ detailType.translated?.manufacturer || detailType.manufacturer }}</span>
                </div>
            </div>
            <p>{{ detailType.translated?.descriptionLong || detailType.translated?.description }}</p>
            <img
                v-if="detailType.coverUrl"
                class="ct-channel-modal-detail__cover"
                :src="detailType.coverUrl"
                :alt="detailType.translated?.name || detailType.name"
            />
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';

import './ct-channel-modal-detail.scss';

defineProps({
    detailType: { type: Object as PropType<Entity<'channel_type'>>, required: true },
});

swDefinePublic({});
</script>
