describe('src/module/ct-settings-basic-information', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the complete basic information settings module', () => {
        const registry = Contena.Component.getComponentRegistry();
        const module = Contena.Module.getModuleRegistry().get('ct-settings-basic-information');

        expect(registry.has('ct-settings-basic-information')).toBe(true);
        expect(registry.has('ct-settings-captcha-select-v2')).toBe(true);
        expect(module?.routes.get('ct.settings.basic.information.index')?.meta).toMatchObject({
            parentPath: 'ct.settings.index',
            privilege: 'system.system_config',
        });
        expect(module?.manifest.settingsItem).toContainEqual(
            expect.objectContaining({
                group: 'general',
                to: 'ct.settings.basic.information.index',
                privilege: 'system.system_config',
            }),
        );
    });
});
