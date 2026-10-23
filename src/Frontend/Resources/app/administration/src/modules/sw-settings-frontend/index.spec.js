/**
 * @sw-package framework
 */
describe('sw-settings-frontend module', () => {
    it('registers frontend settings module', () => {
        const registerSpy = jest.spyOn(Contena.Module, 'register');

        jest.isolateModules(() => {
            require('./index');
        });

        expect(registerSpy).toHaveBeenCalledWith(
            'sw-settings-frontend',
            expect.objectContaining({
                routes: expect.objectContaining({
                    index: expect.objectContaining({
                        components: expect.objectContaining({ default: 'sw-settings-frontend-index' }),
                    }),
                }),
                settingsItem: expect.arrayContaining([
                    expect.objectContaining({
                        to: 'sw.settings.frontend.index',
                        privilege: 'system.system_config',
                    }),
                ]),
            }),
        );
    });
});
