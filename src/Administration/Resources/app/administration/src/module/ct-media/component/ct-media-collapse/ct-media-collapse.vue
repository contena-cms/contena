<template>
    <ct-block name="sw_collapse_panel">
        <div class="ct-collapse ct-media-collapse">
            <ct-block name="sw_collapse_panel_header">
                <div
                    class="ct-collapse__header"
                    role="button"
                    tabindex="0"
                    @click="collapseItem"
                    @keydown.enter="collapseItem"
                >
                    <slot name="header" v-bind="{ expanded }">
                        <ct-block name="sw_collapse_panel_header_slot">
                            <div class="ct-media-collapse__header">
                                <ct-block name="sw_media_collapse_title">
                                    <h4 class="ct-media-collapse__title">{{ title }}</h4>
                                </ct-block>

                                <ct-block name="sw_media_collapse_buttons">
                                    <div class="ct-media-collapse__indicator">
                                        <mt-icon
                                            name="regular-chevron-right-xs"
                                            class="ct-media-collapse__button"
                                            :class="expandButtonClass"
                                            size="10px"
                                        />
                                        <mt-icon
                                            name="regular-chevron-down-xs"
                                            class="ct-media-collapse__button"
                                            :class="collapseButtonClass"
                                            size="10px"
                                        />
                                    </div>
                                </ct-block>
                            </div>
                        </ct-block>
                    </slot>
                </div>
            </ct-block>

            <ct-block name="sw_collapse_panel_content">
                <div v-if="expanded" class="ct-collapse__content">
                    <slot name="content">
                        <ct-block name="sw_collapse_panel_content_slot"></ct-block>
                    </slot>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

import './ct-media-collapse.scss';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    expandOnLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const expanded = ref(props.expandOnLoading);
const expandButtonClass = computed(() => ({ 'is--hidden': expanded.value }));
const collapseButtonClass = computed(() => ({ 'is--hidden': !expanded.value }));
const collapseItem = (): void => {
    expanded.value = !expanded.value;
};

swDefinePublic({
    expanded,
    expandButtonClass,
    collapseButtonClass,
    collapseItem,
});

defineExpose({
    expanded,
    expandButtonClass,
    collapseButtonClass,
    collapseItem,
});
</script>
