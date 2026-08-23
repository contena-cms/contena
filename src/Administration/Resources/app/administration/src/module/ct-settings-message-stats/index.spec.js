import './index';

const { Module } = Contena;

describe('src/module/ct-settings-message-stats/index.js', () => {
    it('should register component', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-settings-message-stats')).toBeTruthy();
    });

    it('should register module base information', () => {
        const module = Module.getModuleRegistry().get('ct-settings-message-stats');
        expect(module).toBeDefined();

        expect(module.manifest).toEqual({
            type: 'core',
            name: 'settings-message-stats',
            title: 'ct-settings-message-stats.general.mainMenuItemGeneral',
            description: 'ct-settings-message-stats.general.descriptionTextModule',
            version: '1.0.0',
            targetVersion: '1.0.0',
            color: '#9AA8B5',
            icon: 'regular-cog',
            favicon: 'icon-module-settings.png',
            routes: expect.any(Object),
            settingsItem: [
                {
                    id: 'ct-settings-message-stats',
                    group: 'system',
                    to: 'ct.settings.message.stats.index',
                    icon: 'regular-bars-square',
                    privilege: 'system.system_config',
                    label: 'ct-settings-message-stats.general.mainMenuItemGeneral',
                    name: 'settings-message-stats',
                },
            ],
            display: true,
        });

        const settingsItem = module.manifest.settingsItem[0];
        expect(typeof settingsItem.group).toBe('string');
        expect(settingsItem.group).toBe('system');
    });

    it('should register module routes', () => {
        const module = Module.getModuleRegistry().get('ct-settings-message-stats');
        expect(module.routes).toBeDefined();
        expect(module.routes.size).toBe(1);

        const route = module.routes.get('ct.settings.message.stats.index');
        expect(route !== undefined).toBe(true);
        expect(route.path).toBe('/sw/settings/message/stats/index');
        expect(route.meta).toEqual({
            parentPath: 'ct.settings.index.system',
            privilege: 'system.system_config',
        });
    });
});
