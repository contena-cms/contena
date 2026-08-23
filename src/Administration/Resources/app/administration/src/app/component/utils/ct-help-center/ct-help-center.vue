<template>
    <ct-block name="sw_help_center">
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
                    <ct-block name="sw_help_sidebar">
                        <mt-action-menu class="ct-help-center__menu" align="end">
                            <ct-block name="sw_help_sidebar_support_content">
                                <mt-action-menu-group>
                                    <ct-block name="sw_help_sidebar_support_documentation">
                                        <mt-action-menu-item
                                            icon="file-text"
                                            :link="$t('help-center.sidebar.support.documentation.href')"
                                        >
                                            {{ $t('help-center.sidebar.support.documentation.text') }}
                                        </mt-action-menu-item>
                                    </ct-block>

                                    <ct-block name="sw_help_sidebar_support_create_support_request">
                                        <mt-action-menu-item
                                            icon="headset"
                                            :link="$t('help-center.sidebar.support.createSupportRequest.href')"
                                        >
                                            {{ $t('help-center.sidebar.support.createSupportRequest.text') }}
                                        </mt-action-menu-item>
                                    </ct-block>

                                    <ct-block name="sw_help_sidebar_support_placeholder"> </ct-block>
                                </mt-action-menu-group>
                            </ct-block>
                        </mt-action-menu>
                    </ct-block>
                </mt-dropdown-menu-portal>
            </mt-dropdown-menu-root>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-help-center.scss';

defineProps({});

import { computed } from 'vue';

const showHelpSidebar = computed(() => {
    return Contena.Store.get('adminHelpCenter').showHelpSidebar;
});
const onVisibilityChange = (isOpened: boolean) => {
    Contena.Store.get('adminHelpCenter').showHelpSidebar = isOpened;
};

swDefinePublic({
    showHelpSidebar,
    onVisibilityChange,
});

defineExpose({
    showHelpSidebar,
    onVisibilityChange,
});
</script>
