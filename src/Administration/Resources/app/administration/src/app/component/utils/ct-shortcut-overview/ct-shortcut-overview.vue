<template>
    <ct-block name="ct_shortcut_overview">
        <div class="ct-shortcut-overview">
            <ct-block name="ct_shortcut_overview_modal">
                <mt-modal-root v-if="showShortcutOverviewModal" :is-open="true" @change="onModalChange">
                    <mt-modal class="ct-shortcut-overview--modal" :title="t('ct-shortcut-overview.modalTitle')" width="full">
                        <div class="ct-shortcut-overview__sections">
                            <ct-block name="ct_shortcut_overview_modal_section_general_shortcuts">
                                <section
                                    class="ct-shortcut-overview__section ct-shortcut-overview__section-general-shortcuts"
                                >
                                    <h3>{{ t('ct-shortcut-overview.sectionGeneralShortcuts') }}</h3>

                                    <ct-shortcut-overview-item
                                        v-for="section in sections.generalShortcuts"
                                        :key="section.id"
                                        :title="section.title"
                                        :content="section.content"
                                        :privilege="section.privilege"
                                    />
                                </section>
                            </ct-block>

                            <ct-block name="ct_shortcut_overview_modal_section_adding_items">
                                <section class="ct-shortcut-overview__section ct-shortcut-overview__section-adding-items">
                                    <h3>{{ t('ct-shortcut-overview.sectionAddingItems') }}</h3>

                                    <ct-shortcut-overview-item
                                        v-for="section in sections.addingItems"
                                        :key="section.id"
                                        :title="section.title"
                                        :content="section.content"
                                        :privilege="section.privilege"
                                    />
                                </section>
                            </ct-block>

                            <ct-block name="ct_shortcut_overview_modal_section_navigation">
                                <section class="ct-shortcut-overview__section ct-shortcut-overview__section-navigation">
                                    <h3>{{ t('ct-shortcut-overview.sectionNavigation') }}</h3>

                                    <ct-shortcut-overview-item
                                        v-for="section in sections.navigation"
                                        :key="section.id"
                                        :title="section.title"
                                        :content="section.content"
                                        :privilege="section.privilege"
                                    />
                                </section>
                            </ct-block>

                            <ct-block name="ct_shortcut_overview_modal_section_accessibility">
                                <section class="ct-shortcut-overview__section ct-shortcut-overview__section-accessibility">
                                    <h3>{{ t('ct-shortcut-overview.sectionAccessibility') }}</h3>

                                    <ct-shortcut-overview-item
                                        v-for="section in sections.accessibility"
                                        :key="section.id"
                                        :title="section.title"
                                        :content="section.content"
                                        :privilege="section.privilege"
                                    />
                                </section>
                            </ct-block>
                        </div>

                        <template #footer>
                            <ct-block name="ct_shortcut_overview_modal_footer">
                                <div class="ct-shortcut-overview__footer">
                                    <ct-block name="ct_shortcut_overview_modal_footer_disable_shortcuts">
                                        <mt-switch
                                            class="ct-shortcut-overview__disable-shortcuts-toggle"
                                            :model-value="shortcutsDisabled"
                                            :label="t('ct-shortcut-overview.disableShortcuts')"
                                            @update:model-value="onToggleShortcutsDisabled"
                                        />
                                    </ct-block>

                                    <ct-block name="ct_shortcut_overview_modal_footer_close">
                                        <mt-button size="small" variant="secondary" @click="onCloseShortcutOverviewModal">
                                            {{ t('global.default.close') }}
                                        </mt-button>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </template>
                    </mt-modal>
                </mt-modal-root>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import useShortcut from 'src/app/composables/use-shortcut';
import { classifyPlatform, PLATFORM } from 'src/core/helper/shortcut-key.helper';
import type { KeyboardPlatform } from 'src/core/helper/shortcut-key.helper';

type ShortcutService = {
    isShortcutsDisabled: () => boolean;
    setShortcutsDisabled: (disabled: boolean) => void;
};

const PLATFORM_NAMES: Record<KeyboardPlatform, string> = {
    [PLATFORM.MAC]: 'Mac',
    [PLATFORM.WINDOWS]: 'Windows',
    [PLATFORM.LINUX]: 'Linux',
};

const props = withDefaults(defineProps<{ showModal?: boolean }>(), { showModal: false });
const emit = defineEmits<{ 'shortcut-open': []; 'shortcut-close': [] }>();
const shortcutService = inject<ShortcutService>('shortcutService');
const { t } = useI18n();

if (!shortcutService) {
    throw new Error('ct-shortcut-overview requires shortcutService');
}

const showShortcutOverviewModal = ref(props.showModal);
const shortcutsDisabled = ref(shortcutService.isShortcutsDisabled());
const platform = computed(() => classifyPlatform(window.navigator.platform));
const platformShortcutSuffix = computed(() => PLATFORM_NAMES[platform.value]);
const sections = computed(() => ({
    generalShortcuts: [
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionSpecialShortcutShortcutListing'),
            content: t('ct-shortcut-overview.keyboardShortcutSpecialShortcutShortcutListing'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionSpecialShortcutFocusSearch'),
            content: t('ct-shortcut-overview.keyboardShortcutSpecialShortcutFocusSearch'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionSpecialShortcutOpenFilters'),
            content: t('ct-shortcut-overview.keyboardShortcutSpecialShortcutOpenFilters'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionSpecialShortcutToggleNavigation'),
            content: t('ct-shortcut-overview.keyboardShortcutSpecialShortcutToggleNavigation'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionSpecialShortcutCycleTheme'),
            content: t('ct-shortcut-overview.keyboardShortcutSpecialShortcutCycleTheme'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionAccessibilityCloseDialog'),
            content: t('ct-shortcut-overview.keyboardShortcutAccessibilityCloseDialog'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionSpecialShortcutSaveDetailView'),
            content: t(`ct-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailView${platformShortcutSuffix.value}`),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionSpecialShortcutClearCache'),
            content: t(`ct-shortcut-overview.keyboardShortcutSpecialShortcutClearCache${platformShortcutSuffix.value}`),
            privilege: 'system.clear_cache',
        },
    ],
    addingItems: [
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionAddBlog'),
            content: t('ct-shortcut-overview.keyboardShortcutAddBlog'),
            privilege: 'blog.creator',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionAddCategory'),
            content: t('ct-shortcut-overview.keyboardShortcutAddCategory'),
            privilege: 'category.creator',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionAddMember'),
            content: t('ct-shortcut-overview.keyboardShortcutAddMember'),
            privilege: 'member.creator',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionAddRule'),
            content: t('ct-shortcut-overview.keyboardShortcutAddRule'),
            privilege: 'rule.creator',
        },
    ],
    navigation: [
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToDashboard'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToDashboard'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToBlogs'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToBlogs'),
            privilege: 'blog.viewer',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToCategories'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToCategories'),
            privilege: 'category.viewer',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToMembers'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToMembers'),
            privilege: 'member.viewer',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToExperienceStudio'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToExperienceStudio'),
            privilege: 'experience_studio.viewer',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToMedia'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToMedia'),
            privilege: 'media.viewer',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToSettingsListing'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToSettingsListing'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToSnippets'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToSnippets'),
            privilege: 'snippet.viewer',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToRuleBuilder'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToRuleBuilder'),
            privilege: 'rule.viewer',
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionGoToPlugins'),
            content: t('ct-shortcut-overview.keyboardShortcutGoToPlugins'),
            privilege: 'system.plugin_maintain',
        },
    ],
    accessibility: [
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionAccessibilitySkipToContent'),
            content: t('ct-shortcut-overview.keyboardShortcutAccessibilitySkipToContent'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionAccessibilityMoveFocusForward'),
            content: t('ct-shortcut-overview.keyboardShortcutAccessibilityMoveFocusForward'),
        },
        {
            id: Contena.Utils.createId(),
            title: t('ct-shortcut-overview.functionAccessibilityMoveFocusBackward'),
            content: t('ct-shortcut-overview.keyboardShortcutAccessibilityMoveFocusBackward'),
        },
    ],
}));

const onOpenShortcutOverviewModal = (): void => {
    showShortcutOverviewModal.value = true;
    emit('shortcut-open');
};
const onCloseShortcutOverviewModal = (): void => {
    showShortcutOverviewModal.value = false;
    emit('shortcut-close');
};
const onToggleShortcutsDisabled = (disabled: boolean): void => {
    shortcutsDisabled.value = disabled;
    shortcutService.setShortcutsDisabled(disabled);
};
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) {
        onCloseShortcutOverviewModal();
    }
};

watch(
    () => props.showModal,
    (showModal) => {
        showShortcutOverviewModal.value = showModal;
    },
);

useShortcut('?', onOpenShortcutOverviewModal);

ctDefinePublic({
    showShortcutOverviewModal,
    shortcutsDisabled,
    platform,
    platformShortcutSuffix,
    sections,
    onOpenShortcutOverviewModal,
    onCloseShortcutOverviewModal,
    onToggleShortcutsDisabled,
    onModalChange,
});
</script>

<style lang="scss">
.mt-modal.ct-shortcut-overview--modal {
    max-width: 75rem;
    max-height: 60rem;

    .mt-modal__content-inner {
        overflow: auto;
    }
}

.ct-shortcut-overview {
    &__sections {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        grid-template-areas: 'general-shortcuts adding-items navigation accessibility';
        align-items: start;
        gap: var(--scale-size-24);
    }

    &__section {
        min-width: 0;

        h3 {
            margin: 0 0 var(--scale-size-8);
            font-size: var(--font-size-s);
            font-weight: var(--font-weight-semibold);
            line-height: var(--font-line-height-s);
        }

        .ct-shortcut-overview-item {
            margin: 0;
            padding: var(--scale-size-4) var(--scale-size-8);
            border-radius: var(--border-radius-xs);

            &:nth-of-type(even) {
                background-color: var(--color-background-secondary-default);
            }
        }
    }

    &__section-general-shortcuts {
        grid-area: general-shortcuts;
    }

    &__section-adding-items {
        grid-area: adding-items;
    }

    &__section-navigation {
        grid-area: navigation;
    }

    &__section-accessibility {
        grid-area: accessibility;
    }

    &__footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--scale-size-16);
        width: 100%;
    }

    &__disable-shortcuts-toggle {
        margin: 0;
    }

    @media screen and (max-width: 60rem) {
        &__sections {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-template-areas:
                'general-shortcuts navigation'
                'adding-items navigation'
                'accessibility navigation';
        }
    }

    @media screen and (max-width: 31.25rem) {
        &__sections {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas:
                'general-shortcuts'
                'adding-items'
                'navigation'
                'accessibility';
        }

        &__footer {
            align-items: stretch;
            flex-direction: column;
        }
    }
}
</style>
