describe('src/modules/ct-settings-frontend', () => {
    beforeAll(async () => {
        await import('./index');
    });

    it('registers the frontend settings module', () => {
        const registry = Contena.Component.getComponentRegistry();
        const module = Contena.Module.getModuleRegistry().get('ct-settings-frontend');

        expect(registry.has('ct-settings-frontend-index')).toBe(true);
        expect(module?.routes.get('ct.settings.frontend.index')?.meta).toMatchObject({
            parentPath: 'ct.settings.index.system',
            privilege: 'system.system_config',
        });
        expect(module?.manifest.settingsItem).toContainEqual(
            expect.objectContaining({
                group: 'system',
                to: 'ct.settings.frontend.index',
                privilege: 'system.system_config',
            }),
        );
    });
});
