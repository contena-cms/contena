describe('src/module/ct-mail-template', () => {
    beforeAll(async () => {
        Contena.Service().register('privileges', () => ({
            addPrivilegeMappingEntry: jest.fn(),
            getPrivileges: jest.fn(() => []),
        }));
        await import('./index');
    });

    it('registers SFC pages and protected routes', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-mail-template-index')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-mail-template-detail')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-mail-header-footer-detail')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-mail-template-create')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-mail-header-footer-create')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-mail-template-view-templates')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-mail-template-view-header-footer')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-mail-template');
        expect(module.routes.get('ct.mail.template.index')).toMatchObject({
            meta: { privilege: 'mail_templates.viewer' },
            redirect: { name: 'ct.mail.template.index.templates' },
        });
        expect(module.routes.get('ct.mail.template.index.templates')).toMatchObject({
            path: '/sw/mail/template/index/templates',
            meta: { privilege: 'mail_templates.viewer' },
        });
        expect(module.routes.get('ct.mail.template.index.header_footer')).toMatchObject({
            path: '/sw/mail/template/index/header-footer',
            meta: { privilege: 'mail_templates.viewer' },
        });
        expect(module.routes.get('ct.mail.template.create')).toMatchObject({
            meta: { privilege: 'mail_templates.creator' },
        });
        expect(module.routes.get('ct.mail.template.create_head_foot')).toMatchObject({
            meta: { privilege: 'mail_templates.creator' },
        });
        expect(module.routes.get('ct.mail.template.detail_head_foot')).toMatchObject({
            meta: { privilege: 'mail_templates.viewer' },
        });
    });
});
