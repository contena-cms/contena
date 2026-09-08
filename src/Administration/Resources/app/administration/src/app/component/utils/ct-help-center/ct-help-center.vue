<template>
    <ct-block name="ct_help_center">
        <div class="ct-help-center">
            <mt-dropdown-menu-root :open="showHelpSidebar" @update:open="onVisibilityChange">
                <mt-dropdown-menu-trigger as-child>
                    <mt-button
                        class="ct-help-center__button"
                        variant="tertiary"
                        square
                        size="default"
                        :aria-label="$t('help-center.sidebar.ariaLabelButtonOpen')"
                    >
                        <template #iconFront>
                            <mt-icon name="question-circle" size="var(--scale-size-20)" />
                        </template>
                    </mt-button>
                </mt-dropdown-menu-trigger>

                <mt-dropdown-menu-portal>
                    <ct-block name="ct_help_sidebar">
                        <mt-action-menu class="ct-help-center__menu" align="end">
                            <ct-block name="ct_help_sidebar_support_content">
                                <mt-action-menu-group>
                                    <ct-block name="ct_help_sidebar_support_documentation">
                                        <mt-action-menu-item
                                            icon="file-text"
                                            :link="$t('help-center.sidebar.support.documentation.href')"
                                        >
                                            {{ $t('help-center.sidebar.support.documentation.text') }}
                                        </mt-action-menu-item>
                                    </ct-block>

                                    <ct-block name="ct_help_sidebar_support_create_support_request">
                                        <mt-action-menu-item
                                            icon="headset"
                                            :link="$t('help-center.sidebar.support.createSupportRequest.href')"
                                        >
                                            {{ $t('help-center.sidebar.support.createSupportRequest.text') }}
                                        </mt-action-menu-item>
                                    </ct-block>

                                    <ct-block name="ct_help_sidebar_support_placeholder"> </ct-block>
                                </mt-action-menu-group>
                            </ct-block>

                            <ct-block name="ct_help_sidebar_shortcuts">
                                <mt-action-menu-group>
                                    <mt-action-menu-item
                                        icon="keyboard"
                                        :shortcut="{ modifiers: ['shift'], key: '?' }"
                                        @select="openShortcutModal"
                                    >
                                        {{ $t('ct-shortcut-overview.title') }}
                                    </mt-action-menu-item>
                                </mt-action-menu-group>
                            </ct-block>
                        </mt-action-menu>
                    </ct-block>
                </mt-dropdown-menu-portal>
            </mt-dropdown-menu-root>

            <ct-shortcut-overview
                :show-modal="showShortcutModal"
                @shortcut-open="openShortcutModal"
                @shortcut-close="closeShortcutModal"
            />
        </div>
    </ct-block>
</template>

<script setup lang="ts">
defineProps({});

import { computed } from 'vue';

const showHelpSidebar = computed(() => {
    return Contena.Store.get('adminHelpCenter').showHelpSidebar;
});
const showShortcutModal = computed(() => {
    return Contena.Store.get('adminHelpCenter').showShortcutModal;
});
const onVisibilityChange = (isOpened: boolean) => {
    Contena.Store.get('adminHelpCenter').showHelpSidebar = isOpened;
};
const openShortcutModal = (): void => {
    Contena.Store.get('adminHelpCenter').showShortcutModal = true;
};
const closeShortcutModal = (): void => {
    Contena.Store.get('adminHelpCenter').showShortcutModal = false;
};

ctDefinePublic({
    showHelpSidebar,
    showShortcutModal,
    onVisibilityChange,
    openShortcutModal,
    closeShortcutModal,
});
</script>

<style lang="scss">
@import '~scss/variables';

.ct-help-center__button[data-state='open'] {
    background-color: var(--color-interaction-secondary-hover);
}

.ct-help-center__menu {
    z-index: $z-index-context-menu;
}
</style>
