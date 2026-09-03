/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-payment', () => {
    let registeredMapping: unknown;

    beforeAll(async () => {
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: (mapping: unknown) => {
                        registeredMapping = mapping;
                    },
                }) as unknown as PrivilegesService,
        );
        await import('./index');
    });

    it('registers Payment as a top-level module with order and refund navigation', () => {
        const registry = Contena.Component.getComponentRegistry();
        expect(registry.has('ct-payment-order-list')).toBe(true);
        expect(registry.has('ct-payment-refund-list')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-payment');
        expect(module?.manifest.navigation).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: 'ct-payment', privilege: 'payment.viewer' }),
                expect.objectContaining({
                    id: 'ct-payment-order',
                    path: 'ct.payment.order',
                    parent: 'ct-payment',
                    privilege: 'payment.viewer',
                }),
                expect.objectContaining({
                    id: 'ct-payment-refund',
                    path: 'ct.payment.refund',
                    parent: 'ct-payment',
                    privilege: 'payment.viewer',
                }),
            ]),
        );
    });

    it('registers Payment configuration in one settings group', () => {
        const registry = Contena.Component.getComponentRegistry();
        expect(registry.has('ct-payment-app-list')).toBe(true);
        expect(registry.has('ct-payment-channel-list')).toBe(true);
        expect(registry.has('ct-payment-method-list')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-payment');
        expect(module?.manifest.settingsItem).toEqual([
            expect.objectContaining({ group: 'payment', to: 'ct.payment.apps', privilege: 'payment.settings' }),
            expect.objectContaining({ group: 'payment', to: 'ct.payment.channels', privilege: 'payment.settings' }),
            expect.objectContaining({ group: 'payment', to: 'ct.payment.methods', privilege: 'payment.settings' }),
        ]);
        expect(Contena.Store.get('settingsItems').settingsGroups.payment).toEqual(module?.manifest.settingsItem);
    });

    it('protects business and configuration routes with their respective privileges', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-payment');
        expect(module?.routes.get('ct.payment.order')?.meta).toMatchObject({ privilege: 'payment.viewer' });
        expect(module?.routes.get('ct.payment.refund')?.meta).toMatchObject({ privilege: 'payment.viewer' });
        expect(module?.routes.get('ct.payment.apps')?.meta).toMatchObject({ privilege: 'payment.settings' });
        expect(module?.routes.get('ct.payment.channels')?.meta).toMatchObject({ privilege: 'payment.settings' });
        expect(module?.routes.get('ct.payment.methods')?.meta).toMatchObject({ privilege: 'payment.settings' });
    });

    it('maps Payment settings writes as a role depending on read access', () => {
        const mapping = registeredMapping as {
            key: string;
            roles: Record<string, { dependencies: string[]; privileges: string[] }>;
        };

        expect(mapping.key).toBe('payment');
        expect(mapping.roles.viewer.privileges).toEqual(
            expect.arrayContaining([
                'payment_order:read',
                'payment_refund:read',
                'payment_channel_config:read',
                'payment_app_channel_method:read',
            ]) as string[],
        );
        expect(mapping.roles.settings.dependencies).toEqual(['payment.viewer']);
        expect(mapping.roles.settings.privileges).toEqual(
            expect.arrayContaining([
                'payment_app:update',
                'payment_channel_config:update',
                'payment_app_channel_method:update',
            ]) as string[],
        );
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
