/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-channel', () => {
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

    it('registers the complete generic Channel administration module', () => {
        const registry = Contena.Component.getComponentRegistry();
        expect(registry.has('ct-channel-list')).toBe(true);
        expect(registry.has('ct-channel-detail')).toBe(true);
        expect(registry.has('ct-channel-create')).toBe(true);
        expect(registry.has('ct-channel-detail-base')).toBe(true);
        expect(registry.has('ct-channel-detail-domains')).toBe(true);
        expect(registry.has('ct-channel-modal')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-channel');
        expect(module?.manifest.entity).toBe('channel');
        expect(module?.manifest.navigation).toEqual([]);
        expect(registry.has('ct-channel-menu')).toBe(true);
    });

    it('protects list, create and detail routes with mapped Channel privileges', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-channel');
        const listMeta = module?.routes.get('ct.channel.list')?.meta as { privilege?: string } | undefined;
        const createMeta = module?.routes.get('ct.channel.create.base')?.meta as { privilege?: string } | undefined;
        const detailMeta = module?.routes.get('ct.channel.detail.base')?.meta as { privilege?: string } | undefined;

        expect(listMeta?.privilege).toBe('channel.viewer');
        expect(createMeta?.privilege).toBe('channel.creator');
        expect(detailMeta?.privilege).toBe('channel.viewer');
    });

    it('maps only generic Channel dependencies and excludes Commerce privileges', () => {
        const privileges = JSON.stringify(registeredMapping);

        expect(privileges).toContain('channel_domain:read');
        expect(privileges).toContain('snippet_set:read');
        expect(privileges).toContain('member_group:read');
        expect(privileges).not.toMatch(/product|order|currency|payment|shipping/);
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
