<template>
    <ct-block name="sw_collapse_panel">
        <div class="ct-collapse">
            <ct-block name="sw_collapse_panel_header">
                <ct-block name="sw_collapse_panel_header_slot">
                    <div class="ct-sidebar-collapse__header">
                        <ct-block name="sw_sidebar_collapse_title">
                            <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
                            <h4 class="ct-sidebar-collapse__title" aria-level="4" @click="collapseItem">
                                <slot name="header" :expanded="expanded">
                                    <ct-block name="sw_sidebar_collapse_panel_header_slot"></ct-block>
                                </slot>
                            </h4>
                        </ct-block>

                        <ct-block name="sw_sidebar_collapse_buttons">
                            <div class="ct-sidebar-collapse__actions">
                                <slot name="actions"></slot>
                            </div>

                            <button
                                class="ct-sidebar-collapse__indicator"
                                tabindex="0"
                                :aria-label="$t('ct-sidebar-collapse.collapseIndicator')"
                                @click="collapseItem"
                                @keydown.enter="collapseItem"
                            >
                                <mt-icon
                                    size="22px"
                                    :name="`regular-chevron-${expandChevronDirection}-xs`"
                                    class="ct-sidebar-collapse__expand-button"
                                    :class="expandButtonClass"
                                />

                                <mt-icon
                                    size="14px"
                                    name="regular-chevron-down-xs"
                                    class="ct-sidebar-collapse__collapse-button"
                                    :class="collapseButtonClass"
                                />
                            </button>
                        </ct-block>
                    </div>
                </ct-block>
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

<script setup>
import './ct-sidebar-collapse.scss';

defineProps({
    expandChevronDirection: {
        type: String,
        required: false,
        default: 'right',
        validator: (value) =>
            [
                'up',
                'left',
                'right',
                'down',
            ].includes(value),
    },
});
const emit = defineEmits(['change-expanded']);

import { computed } from 'vue';

const { expanded, collapseItem: parentCollapseItem } = Contena.Component.getExtensionParentSetup();
const expandButtonClass = computed(() => ({
    'is--hidden': expanded.value,
}));
const collapseButtonClass = computed(() => ({
    'is--hidden': !expanded.value,
}));
const collapseItem = () => {
    parentCollapseItem.value();
    emit('change-expanded', { isExpanded: expanded.value });
};

swDefinePublic({
    expandButtonClass,
    collapseButtonClass,
    collapseItem,
});

defineExpose({
    expandButtonClass,
    collapseButtonClass,
    collapseItem,
});
</script>
