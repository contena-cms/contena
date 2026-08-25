import { ADMIN_MENU_ROOTS } from 'src/core/constant/admin-menu.constant';

const coreNavigationEntries = Object.freeze([
    {
        id: ADMIN_MENU_ROOTS.content,
        label: 'global.ct-admin-menu.navigation.mainMenuItemContent',
        icon: 'regular-folder',
        position: 20,
        moduleType: 'core',
    },
    {
        id: ADMIN_MENU_ROOTS.system,
        label: 'global.ct-admin-menu.navigation.mainMenuItemSystem',
        icon: 'regular-desktop',
        position: 70,
        moduleType: 'core',
    },
]);

/**
 *
 * @private
 * @module app/service/menu
 * @method createMenuService
 * @memberOf module:app/service/menu
 * @param moduleFactory
 * @returns {{getMainMenu: getMainMenu, addItem: FlatTree.add, removeItem: FlatTree.remove}}
 * @constructor
 */
export default function createMenuService(moduleFactory) {
    return {
        getNavigationFromAdminModules,
    };

    /**
     * Iterates the module registry from the {@link ModuleFactory} and returns all navigation entries as a flat array
     *
     * @memberOf module:app/service/menu
     * @returns {Array} Navigation entries of all registered admin modules
     */
    function getNavigationFromAdminModules() {
        const modules = moduleFactory.getModuleRegistry();
        const navigationEntries = coreNavigationEntries.map((entry) => ({ ...entry }));

        modules.forEach((module) => {
            const moduleNavigation = Array.isArray(module.navigation) ? module.navigation : [];

            navigationEntries.push(...moduleNavigation);
        });

        return navigationEntries;
    }
}
