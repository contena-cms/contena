import AclService from 'src/app/service/acl.service';

describe('src/app/service/acl.service.ts', () => {
    beforeEach(() => {
        Contena.Application.view = {};
        Contena.Application.view.root = {};
        Contena.Application.view.root.$router = {};
        Contena.Application.view.root.$router.resolve = () => ({});
        Contena.Store.get('settingsItems').settingsGroups.general = [];
        Contena.Store.get('settingsItems').settingsGroups.system = [];
    });

    it('should be an admin', async () => {
        Contena.Store.get('session').setCurrentUser({ admin: true });
        const aclService = new AclService();

        expect(aclService.isAdmin()).toBe(true);
    });

    it('should not be an admin', async () => {
        Contena.Store.get('session').setCurrentUser({ admin: false });
        const aclService = new AclService();

        expect(aclService.isAdmin()).toBe(false);
    });

    it('should not be an admin if the store is empty', async () => {
        Contena.Store.get('session').removeCurrentUser();
        const aclService = new AclService();

        expect(aclService.isAdmin()).toBe(false);
    });

    it('should allow every privilege as an admin', async () => {
        Contena.Store.get('session').setCurrentUser({ admin: true });
        const aclService = new AclService();

        expect(aclService.can('system.clear_cache')).toBe(true);
    });

    it('should disallow when privilege does not exist', async () => {
        Contena.Store.get('session').setCurrentUser({ admin: false });
        const aclService = new AclService();

        expect(aclService.can('system.clear_cache')).toBeFalsy();
    });

    it('should allow when privilege exists', async () => {
        const aclService = new AclService();
        Contena.Store.get('session').setCurrentUser({ admin: false, aclRoles: [{ privileges: ['system.clear_cache'] }] });

        expect(aclService.can('system.clear_cache')).toBe(true);
    });

    it('should return all privileges', async () => {
        Contena.Store.get('session').setCurrentUser({
            admin: false,
            aclRoles: [
                {
                    privileges: [
                        'system.clear_cache',
                        'orders.create_discounts',
                    ],
                },
            ],
        });
        const aclService = new AclService();

        expect(aclService.privileges).toContain('system.clear_cache');
        expect(aclService.privileges).toContain('orders.create_discounts');
    });

    it('should return true if router is undefined', async () => {
        Contena.Application.view.root.$router = null;
        Contena.Store.get('session').setCurrentUser({ admin: false, aclRoles: [{ privileges: ['product.viewer'] }] });
        const aclService = new AclService();

        expect(aclService.hasAccessToRoute('ct.product.index')).toBe(true);
    });

    it('should have access to the route when no privilege exists', async () => {
        Contena.Application.view.root.$router.resolve = () => ({});
        Contena.Store.get('session').setCurrentUser({ admin: false, aclRoles: [{ privileges: ['product.viewer'] }] });
        const aclService = new AclService();

        expect(aclService.hasAccessToRoute('ct.product.index')).toBe(true);
    });

    it('should not have access to the route when privilege not matches', async () => {
        Contena.Application.view.root.$router.resolve = () => ({
            meta: {
                privilege: 'category.viewer',
            },
        });
        Contena.Store.get('session').setCurrentUser({ admin: false, aclRoles: [{ privileges: ['product.viewer'] }] });
        const aclService = new AclService();

        expect(aclService.hasAccessToRoute('ct.product.index')).toBeFalsy();
    });

    it('should have access to the route when privilege matches', async () => {
        Contena.Application.view.root.$router.resolve = () => ({
            meta: {
                privilege: 'product.viewer',
            },
        });
        Contena.Store.get('session').setCurrentUser({ admin: false, aclRoles: [{ privileges: ['product.viewer'] }] });
        const aclService = new AclService();

        expect(aclService.hasAccessToRoute('ct.product.index')).toBe(true);
    });

    it('should have access to the settings route when user has any access to settings', async () => {
        Contena.Store.get('settingsItems').settingsGroups.general = [
            {
                group: 'general',
                icon: 'default-chart-pie',
                id: 'ct-settings-tax',
                label: 'ct-settings-tax.general.mainMenuItemGeneral',
                name: 'settings-tax',
                privilege: 'tax.viewer',
                to: 'ct.settings.tax.index',
            },
        ];
        Contena.Store.get('session').setCurrentUser({ admin: false, aclRoles: [{ privileges: ['tax.viewer'] }] });
        const aclService = new AclService();

        expect(aclService.hasAccessToRoute('.ct.settings.index')).toBe(true);
        expect(aclService.hasAccessToRoute('/sw/settings/index')).toBe(true);
    });

    it('should have access to the settings route when user has no access to settings', async () => {
        Contena.Store.get('settingsItems').settingsGroups.general = [
            {
                group: 'general',
                icon: 'default-chart-pie',
                id: 'ct-settings-tax',
                label: 'ct-settings-tax.general.mainMenuItemGeneral',
                name: 'settings-tax',
                privilege: 'tax.viewer',
                to: 'ct.settings.tax.index',
            },
        ];
        Contena.Store.get('settingsItems').settingsGroups.system = [];
        Contena.Store.get('session').setCurrentUser({ admin: false });
        const aclService = new AclService();

        expect(aclService.hasAccessToRoute('.ct.settings.index')).toBe(false);
        expect(aclService.hasAccessToRoute('/sw/settings/index')).toBe(false);
    });
});
