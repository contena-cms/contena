import createMenuService from 'src/app/service/menu.service';

/** fixtures */
import adminModules from './_mocks/adminModules.json';

describe('src/app/service/menu.service', () => {
    const menuService = createMenuService(Contena.Module);

    function clearModules() {
        Contena.Module.getModuleRegistry().clear();
    }

    beforeEach(async () => {
        clearModules();
    });

    describe('adminModuleNavigation', () => {
        it('returns an unordered list of all navigation entries', async () => {
            adminModules.forEach((module) => {
                Contena.Module.register(module.name, module);
            });

            const navigationEntries = menuService.getNavigationFromAdminModules();

            expect(navigationEntries).toHaveLength(13);
            expect(navigationEntries).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({ id: 'ct-content' }),
                    expect.objectContaining({ id: 'ct-system', icon: 'regular-desktop', position: 70 }),
                    expect.objectContaining({ id: 'ct.second.top.level' }),
                    expect.objectContaining({ id: 'ct.second.level.last' }),
                    expect.objectContaining({ id: 'ct.second.level.first' }),
                    expect.objectContaining({ id: 'ct.second.level.second' }),
                    expect.objectContaining({ id: 'ct.first.top.level' }),
                    expect.objectContaining({ id: 'children.with.privilege' }),
                    expect.objectContaining({
                        id: 'children.with.privilege.first',
                    }),
                    expect.objectContaining({
                        id: 'children.with.privilege.second',
                    }),
                ]),
            );
        });

        it('ignores modules with empty navigation', async () => {
            Contena.Module.register('empty-navigation', {
                name: 'empty-navigation',
                routes: { index: { path: '/', component: 'ct-index' } },
                navigation: [],
            });

            expect(menuService.getNavigationFromAdminModules()).toHaveLength(2);
        });

        it('ignores modules if navigation is null', async () => {
            Contena.Module.register('null-navigation', {
                name: 'null-navigation',
                routes: { index: { path: '/', component: 'ct-index' } },
                navigation: null,
            });

            expect(menuService.getNavigationFromAdminModules()).toHaveLength(2);
        });

        it('returns fresh core navigation entries', () => {
            const firstResult = menuService.getNavigationFromAdminModules();
            firstResult[0].label = 'changed';

            expect(menuService.getNavigationFromAdminModules()[0].label).toBe(
                'global.ct-admin-menu.navigation.mainMenuItemContent',
            );
        });
    });
});
