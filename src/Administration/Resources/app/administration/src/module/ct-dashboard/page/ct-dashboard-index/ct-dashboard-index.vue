<template>
    <ct-block name="sw_dashboard_index">
        <ct-page class="ct-dashboard-index" :show-smart-bar="false">
            <template #content>
                <ct-block name="sw_dashboard_index_content">
                    <ct-block name="sw_dashboard_index_order_trend" />
                    <ct-block name="sw_dashboard_index_orders" />
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
defineOptions({
    metaInfo() {
        return { title: this.$createTitle() };
    },
});

defineProps({});

swDefinePublic({});
</script>
