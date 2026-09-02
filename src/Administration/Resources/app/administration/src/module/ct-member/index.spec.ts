/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-member', () => {
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

    it('registers Member as an independent top-level module', () => {
        const registry = Contena.Component.getComponentRegistry();

        expect(registry.has('ct-member-list')).toBe(true);
        expect(registry.has('ct-member-detail')).toBe(true);
        expect(registry.has('ct-member-create')).toBe(true);
        expect(registry.has('ct-member-detail-base')).toBe(true);
        expect(registry.has('ct-member-detail-addresses')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-member');
        expect(module?.manifest.entity).toBe('member');
        const snippets = module?.manifest.snippets as Record<string, unknown> | undefined;
        expect(snippets?.['en-GB']).toBeDefined();
        expect(snippets?.['zh-CN']).toBeDefined();
        expect(module?.manifest.navigation).toContainEqual(
            expect.objectContaining({
                id: 'ct-member',
                privilege: 'member.viewer',
            }),
        );
    });

    it('protects Member routes with their mapped privileges', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-member');

        expect(module?.routes.get('ct.member.index')?.meta).toMatchObject({ privilege: 'member.viewer' });
        expect(module?.routes.get('ct.member.create')?.meta).toMatchObject({ privilege: 'member.creator' });
        expect(module?.routes.get('ct.member.detail.base')?.meta).toMatchObject({ privilege: 'member.viewer' });
        expect(module?.routes.get('ct.member.detail.addresses')?.meta).toMatchObject({ privilege: 'member.viewer' });
    });

    it('maps generic Member dependencies without Commerce privileges', () => {
        const privileges = JSON.stringify(registeredMapping);

        expect(privileges).toContain('member_address:read');
        expect(privileges).toContain('member_group:read');
        expect(privileges).toContain('channel:read');
        expect(privileges).toContain('region:read');
        expect(privileges).not.toMatch(/order|currency|payment|shipping/);
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
