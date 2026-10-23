import template from './sw-help-center.html.twig';
import './sw-help-center.scss';

type ShortcutModal = {
    onOpenShortcutOverviewModal: () => void;
};

/**
 * @description Displays an icon and an action menu with the help center content
 *
 * @sw-package framework
 *
 * @private
 */
export default Contena.Component.wrapComponentConfig({
    template,

    computed: {
        showHelpSidebar(): boolean {
            return Contena.Store.get('adminHelpCenter').showHelpSidebar;
        },

        showShortcutModal(): boolean {
            return Contena.Store.get('adminHelpCenter').showShortcutModal;
        },
    },

    watch: {
        showShortcutModal(value: boolean): void {
            if (!value) {
                return;
            }

            const shortcutModal = this.$refs.shortcutModal as ShortcutModal | undefined;

            shortcutModal?.onOpenShortcutOverviewModal();
        },
    },

    methods: {
        onVisibilityChange(isOpened: boolean): void {
            Contena.Store.get('adminHelpCenter').showHelpSidebar = isOpened;
        },

        openShortcutModal(): void {
            Contena.Store.get('adminHelpCenter').showShortcutModal = true;
        },

        closeShortcutModal(): void {
            Contena.Store.get('adminHelpCenter').showShortcutModal = false;
        },
    },
});
