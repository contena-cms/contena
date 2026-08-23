import { mount, type VueWrapper } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';
import type { PermissionCatalogGroup, PermissionCatalogResource, PermissionMapping } from './permission-catalog';

type RoleAccessVm = {
    catalog: PermissionCatalogGroup[];
    filteredCatalog: PermissionCatalogGroup[];
    selectedGroup: PermissionCatalogGroup | null;
    groupActions: string[];
    searchTerm: string;
    showSelectedOnly: boolean;
    permissionLevel(resource: PermissionCatalogResource): string;
    setPermissionLevel(resource: PermissionCatalogResource, level: 'none' | 'view' | 'edit' | 'manage' | 'custom'): void;
    togglePermission(resourceKey: string, action: string): void;
    isPermissionRequired(resourceKey: string, action: string): boolean;
    isPermissionDisabled(resourceKey: string, action: string): boolean;
    resourceIsFullySelected(resource: PermissionCatalogResource): boolean;
    crossResourceDependencies(resource: PermissionCatalogResource): Array<{ action: string; dependencies: string[] }>;
    requiredBy(resourceKey: string, action: string): string[];
    canGrantPermission(identifier: string): boolean;
    pendingRemoval: { identifier: string; resourceKey: string; dependents: string[] } | null;
    confirmPendingRemoval(): void;
    cancelPendingRemoval(): void;
    lastChange: { added: string[]; removed: string[]; blocked: string[] } | null;
};

const mediaMapping: PermissionMapping = {
    category: 'permissions',
    parent: 'content',
    key: 'media',
    roles: {
        viewer: { privileges: ['media:read'], dependencies: [] },
        editor: { privileges: ['media:update'], dependencies: ['media.viewer'] },
        creator: {
            privileges: ['media:create'],
            dependencies: [
                'media.viewer',
                'media.editor',
            ],
        },
        deleter: { privileges: ['media:delete'], dependencies: ['media.viewer'] },
    },
};

async function createWrapper(
    rolePrivileges: string[] = [],
    mapping: PermissionMapping = mediaMapping,
    grantedPrivileges: string[] | null = null,
) {
    const privileges = new PrivilegesService();
    privileges.addPrivilegeMappingEntry(mapping);
    const firstAction = Object.keys(mapping.roles)[0];

    const role = { privileges: rolePrivileges };
    const wrapper = mount(
        await wrapTestComponent('ct-permissions-role-access', {
            sync: true,
        }),
        {
            props: { role },
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    privileges,
                    menuService: {
                        getNavigationFromAdminModules: () => [
                            { id: 'ct-content' },
                            {
                                id: `ct-${mapping.key}`,
                                parent: 'ct-content',
                                privilege: `${mapping.key}.${firstAction}`,
                            },
                        ],
                    },
                    acl: {
                        isAdmin: () => grantedPrivileges === null,
                        can: (identifier: string) => grantedPrivileges === null || grantedPrivileges.includes(identifier),
                    },
                },
                stubs: {
                    'ct-block': { template: '<div><slot /></div>' },
                    'mt-card': { template: '<div><slot /></div>' },
                    'mt-search': true,
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-badge': true,
                    'mt-checkbox': true,
                    'mt-empty-state': true,
                },
            },
        },
    );

    await flushPromises();

    return {
        role,
        wrapper: wrapper as unknown as VueWrapper<RoleAccessVm>,
    };
}

describe('module/ct-permissions/component/ct-permissions-role-access', () => {
    let wrapper: VueWrapper<RoleAccessVm> | undefined;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
    });

    it('organizes functional permissions by their Administration location', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.catalog).toHaveLength(1);
        expect(wrapper.vm.catalog[0].id).toBe('navigation:ct-content');
        expect(wrapper.vm.catalog[0].resources[0].key).toBe('media');
        expect(wrapper.find('.ct-permissions-role-access__matrix').exists()).toBe(true);
        expect(wrapper.vm.groupActions).toEqual([
            'viewer',
            'editor',
            'creator',
            'deleter',
        ]);
    });

    it('applies access presets without changing the stored privilege format', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;
        const resource = wrapper.vm.catalog[0].resources[0];

        wrapper.vm.setPermissionLevel(resource, 'manage');

        expect(result.role.privileges).toEqual([
            'media.viewer',
            'media.editor',
            'media.creator',
            'media.deleter',
        ]);

        wrapper.vm.setPermissionLevel(resource, 'none');

        expect(result.role.privileges).toEqual([]);
    });

    it('adds dependencies and confirms before removing a required permission', async () => {
        const result = await createWrapper();
        wrapper = result.wrapper;

        wrapper.vm.togglePermission('media', 'creator');

        expect(result.role.privileges).toEqual([
            'media.creator',
            'media.viewer',
            'media.editor',
        ]);
        expect(wrapper.vm.isPermissionRequired('media', 'editor')).toBe(true);
        expect(wrapper.vm.requiredBy('media', 'editor')).toEqual(['media.creator']);
        expect(wrapper.vm.lastChange?.added).toEqual([
            'media.creator',
            'media.viewer',
            'media.editor',
        ]);

        wrapper.vm.togglePermission('media', 'editor');
        await flushPromises();

        expect(result.role.privileges).toContain('media.editor');
        expect(wrapper.find('.ct-permissions-role-access__removal-confirmation').exists()).toBe(true);
        expect(wrapper.vm.pendingRemoval).toEqual({
            identifier: 'media.editor',
            resourceKey: 'media',
            dependents: ['media.creator'],
        });

        wrapper.vm.confirmPendingRemoval();

        expect(result.role.privileges).toEqual(['media.viewer']);
        expect(wrapper.vm.pendingRemoval).toBeNull();
    });

    it('prevents a non-admin from granting a permission or dependency they do not own', async () => {
        const result = await createWrapper([], mediaMapping, [
            'media.creator',
            'media.viewer',
        ]);
        wrapper = result.wrapper;
        const resource = wrapper.vm.catalog[0].resources[0];
        await flushPromises();

        expect(wrapper.vm.canGrantPermission('media.creator')).toBe(false);
        expect(wrapper.vm.isPermissionDisabled('media', 'editor')).toBe(true);
        expect(result.role.privileges).toEqual([]);

        wrapper.vm.setPermissionLevel(resource, 'manage');

        expect(result.role.privileges).toEqual([]);
        expect(wrapper.vm.lastChange?.blocked).toContain('media.editor');
    });

    it('keeps selected permissions removable when the current user cannot grant them', async () => {
        const result = await createWrapper(['media.editor'], mediaMapping, []);
        wrapper = result.wrapper;

        expect(wrapper.vm.isPermissionDisabled('media', 'editor')).toBe(false);

        wrapper.vm.togglePermission('media', 'editor');

        expect(result.role.privileges).toEqual([]);
        expect(wrapper.vm.isPermissionRequired('media', 'editor')).toBe(false);
        expect(wrapper.vm.pendingRemoval).toBeNull();
    });

    it('filters by translated labels and technical permission keys', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.searchTerm = 'media';
        await flushPromises();

        expect(wrapper.vm.filteredCatalog[0].resources[0].key).toBe('media');

        wrapper.vm.searchTerm = 'not-registered';
        await flushPromises();

        expect(wrapper.vm.filteredCatalog).toEqual([]);
    });

    it('can show only resources with selected permissions', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.showSelectedOnly = true;
        await flushPromises();

        expect(wrapper.vm.filteredCatalog).toEqual([]);

        wrapper.vm.togglePermission('media', 'viewer');
        await flushPromises();

        expect(wrapper.vm.filteredCatalog[0].resources[0].key).toBe('media');
    });

    it('keeps plugin-defined permission actions configurable', async () => {
        const result = await createWrapper([], {
            category: 'permissions',
            parent: null,
            key: 'documents',
            roles: {
                approve: { privileges: ['document:approve'], dependencies: [] },
            },
        });
        wrapper = result.wrapper;
        const resource = wrapper.vm.catalog[0].resources[0];
        await flushPromises();

        expect(resource.key).toBe('documents');
        expect(wrapper.vm.selectedGroup?.resources[0].key).toBe('documents');
        expect(wrapper.vm.groupActions).toEqual(['approve']);

        wrapper.vm.setPermissionLevel(resource, 'manage');

        expect(result.role.privileges).toEqual(['documents.approve']);
        expect(wrapper.vm.resourceIsFullySelected(resource)).toBe(true);
    });

    it('shows cross-resource dependencies separately from the matrix state', async () => {
        const result = await createWrapper([], {
            category: 'permissions',
            parent: null,
            key: 'documents',
            roles: {
                approve: {
                    privileges: ['document:approve'],
                    dependencies: ['media.viewer'],
                },
            },
        });
        wrapper = result.wrapper;
        const resource = wrapper.vm.catalog[0].resources[0];

        wrapper.vm.togglePermission('documents', 'approve');

        expect(result.role.privileges).toEqual([
            'documents.approve',
            'media.viewer',
        ]);
        expect(wrapper.vm.crossResourceDependencies(resource)).toEqual([
            {
                action: 'approve',
                dependencies: ['media.viewer'],
            },
        ]);
    });
});
