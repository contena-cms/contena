import './index';

const { Module } = Contena;

describe('src/module/ct-settings-media/index.js', () => {
    it('should register component', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-settings-media')).toBeTruthy();
    });

    it('should register module base information', () => {
        const module = Module.getModuleRegistry().get('ct-settings-media');
        expect(module).toBeDefined();

        expect(module.manifest).toEqual({
            type: 'core',
            name: 'settings-media',
            title: 'ct-settings-media.general.title',
            description: 'ct-settings-media.general.description',
            color: '#9AA8B5',
            icon: 'regular-cog',
            favicon: 'icon-module-settings.png',
            routes: expect.any(Object),
            settingsItem: [
                {
                    id: 'ct-settings-media',
                    group: 'content',
                    to: 'ct.settings.media.index',
                    icon: 'regular-image',
                    privilege: 'system.system_config',
                    label: 'ct-settings-media.general.title',
                    name: 'settings-media',
                },
            ],
            display: true,
        });

        const settingsItem = module.manifest.settingsItem[0];
        expect(typeof settingsItem.group).toBe('string');
        expect(settingsItem.group).toBe('content');
    });

    it('should register module routes', () => {
        const routes = {
            'ct.settings.media.index': {
                path: '/sw/settings/media/index',
                components: { default: 'ct-settings-media' },
            },
        };

        const register = Module.getModuleRegistry().get('ct-settings-media').routes;
        expect(register).toBeDefined();

        expect(register.size).toBe(Object.keys(routes).length);
        Object.keys(routes).forEach((name) => {
            const route = register.get(name);

            expect(route.path).toBe(routes[name].path);
            expect(route.component).toBe(routes[name].component);
        });
    });
});
