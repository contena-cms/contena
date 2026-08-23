/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-settings-member-group', () => {
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

    it('registers MemberGroup as an independent settings module', () => {
        const registry = Contena.Component.getComponentRegistry();

        expect(registry.has('ct-settings-member-group-list')).toBe(true);
        expect(registry.has('ct-settings-member-group-detail')).toBe(true);
        expect(registry.has('ct-settings-member-group-create')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-settings-member-group');
        expect(module?.manifest.entity).toBe('member_group');
        expect(module?.manifest.settingsItem).toContainEqual(
            expect.objectContaining({
                group: 'member',
                to: 'ct.settings.member.group.index',
                privilege: 'member_groups.viewer',
            }),
        );
        expect(module?.manifest.navigation).toBeUndefined();
    });

    it('protects list, create and detail routes with MemberGroup privileges', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-settings-member-group');

        expect(module?.routes.get('ct.settings.member.group.index')?.meta).toMatchObject({
            privilege: 'member_groups.viewer',
        });
        expect(module?.routes.get('ct.settings.member.group.create')?.meta).toMatchObject({
            privilege: 'member_groups.creator',
        });
        expect(module?.routes.get('ct.settings.member.group.detail')?.meta).toMatchObject({
            privilege: 'member_groups.viewer',
        });
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
