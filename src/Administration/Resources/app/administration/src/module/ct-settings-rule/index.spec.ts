/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-settings-rule', () => {
    beforeAll(async () => {
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                }) as unknown as PrivilegesService,
        );
        await import('./index');
    });

    it('registers Rule below the Automation navigation entry', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-settings-rule');

        expect(module?.manifest.navigation).toContainEqual(
            expect.objectContaining({
                id: 'ct-settings-rule',
                path: 'ct.settings.rule.index',
                parent: 'ct-automation',
                privilege: 'rule.viewer',
                position: 10,
            }),
        );
        expect(module?.manifest.navigation).toContainEqual(
            expect.objectContaining({
                id: 'ct-automation',
                label: 'global.ct-admin-menu.navigation.mainMenuItemAutomation',
                position: 70,
            }),
        );
    });

    it('does not attach the Rule index route to Settings', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-settings-rule');

        expect(module?.routes.get('ct.settings.rule.index')?.meta).toMatchObject({ privilege: 'rule.viewer' });
        expect(module?.routes.get('ct.settings.rule.index')?.meta).not.toHaveProperty('parentPath');
        expect(module?.manifest.settingsItem).toBeUndefined();
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
