import './index';

describe('src/module/ct-settings-mailer', () => {
    it('registers the global mailer settings page', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-settings-mailer')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-settings-mailer');
        expect(module.routes.get('ct.settings.mailer.index')).toMatchObject({
            meta: { privilege: 'system.system_config' },
        });
    });
});
