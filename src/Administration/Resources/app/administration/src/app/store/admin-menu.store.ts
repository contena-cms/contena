/**
 * @private
 * @description Apply for upselling service only, no public usage
 */
import type { ModuleManifest } from '../../core/factory/module.factory';

type NavigationEntry = Exclude<ModuleManifest['navigation'], undefined>[number];

const SIDEBAR_EXPANDED_STORAGE_KEY = 'ct-admin-menu-sidebar-expanded';

function menuEntryKey(entry: NavigationEntry): string | undefined {
    return entry.id ?? entry.path;
}

localStorage.removeItem('contena-admin-menu-expanded');

const adminMenuStore = Contena.Store.register({
    id: 'adminMenu',

    state: () => ({
        /**
         * The expanded state of the sidebar menu
         */
        isExpanded: localStorage.getItem(SIDEBAR_EXPANDED_STORAGE_KEY) !== 'false',
        /**
         * The entries that are currently expanded in the sidebar menu
         */
        expandedEntries: [] as NavigationEntry[],
        /**
         * The navigation entries for the sidebar menu
         */
        adminModuleNavigation: [] as NavigationEntry[],
    }),

    actions: {
        /**
         * Clears the expanded menu entries collapsing all entries
         */
        clearExpandedMenuEntries() {
            this.expandedEntries = [];
        },
        /**
         * Expands a sidebar menu entry
         * @param entry The Navigation Entry to expand
         */
        expandMenuEntry(entry: NavigationEntry) {
            const key = menuEntryKey(entry);

            if (key !== undefined && this.expandedEntries.some((expanded) => menuEntryKey(expanded) === key)) {
                return;
            }

            this.expandedEntries.push(entry);
        },
        /**
         * Collapses a sidebar menu entry
         * @param entry The Navigation Entry to collapse
         */
        collapseMenuEntry(entry: NavigationEntry) {
            const key = menuEntryKey(entry);

            this.expandedEntries =
                key === undefined
                    ? this.expandedEntries.filter((expanded) => expanded !== entry)
                    : this.expandedEntries.filter((expanded) => menuEntryKey(expanded) !== key);
        },
        /**
         * Expands the  sidebar menu
         */
        collapseSidebar() {
            this.isExpanded = false;
            localStorage.setItem(SIDEBAR_EXPANDED_STORAGE_KEY, 'false');
        },
        /**
         * Collapses the sidebar menu
         */
        expandSidebar() {
            this.isExpanded = true;
            localStorage.setItem(SIDEBAR_EXPANDED_STORAGE_KEY, 'true');
        },
    },
});

/**
 * @private
 */
export type AdminMenuStore = ReturnType<typeof adminMenuStore>;

/**
 * @private
 * @description
 * The `adminMenuStore` is responsible for managing the state of the sidebar menu.
 */
export default adminMenuStore;
