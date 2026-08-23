import { buildPermissionCatalog, type PermissionMapping } from './permission-catalog';

const mappings: PermissionMapping[] = [
    {
        category: 'permissions',
        parent: 'settings',
        key: 'users_and_permissions',
        roles: {},
    },
    {
        category: 'permissions',
        parent: 'settings',
        key: 'language',
        roles: {},
    },
    {
        category: 'permissions',
        parent: 'content',
        key: 'media',
        roles: {},
    },
    {
        category: 'permissions',
        parent: 'settings',
        key: 'unmapped',
        roles: {},
    },
    {
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {},
    },
];

const translate = (key: string) => key;

describe('buildPermissionCatalog', () => {
    it('uses registered navigation and settings locations', () => {
        const catalog = buildPermissionCatalog(
            mappings,
            [
                { id: 'ct-content' },
                { id: 'ct-system' },
                {
                    id: 'ct-users',
                    parent: 'ct-system',
                    privilege: 'users_and_permissions.viewer',
                },
                { id: 'ct-media', parent: 'ct-content', privilege: 'media.viewer' },
            ],
            {
                general: [{ group: 'general', privilege: 'language.viewer' }],
            },
            translate,
        );

        expect(catalog.map((group) => group.id)).toEqual([
            'navigation:ct-content',
            'navigation:ct-system',
            'settings:general',
            'other',
        ]);
        expect(catalog.find((group) => group.id === 'navigation:ct-system')?.resources[0].key).toBe('users_and_permissions');
        expect(catalog.find((group) => group.id === 'settings:general')?.resources[0].key).toBe('language');
    });

    it('does not expose additional permissions in the functional catalog', () => {
        const catalog = buildPermissionCatalog(mappings, [], {}, translate);

        expect(catalog.flatMap((group) => group.resources).map((resource) => resource.key)).not.toContain('system');
    });

    it('falls back safely when a menu hierarchy contains a cycle', () => {
        const catalog = buildPermissionCatalog(
            [mappings[0]],
            [
                { id: 'first', parent: 'second' },
                { id: 'second', parent: 'first' },
                { id: 'entry', parent: 'first', privilege: 'users_and_permissions.viewer' },
            ],
            {},
            translate,
        );

        expect(catalog[0].id).toBe('other');
    });

    it('discovers plugin navigation roots and settings groups without core configuration', () => {
        const catalog = buildPermissionCatalog(
            [
                {
                    category: 'permissions',
                    parent: null,
                    key: 'documents',
                    roles: {},
                },
                {
                    category: 'permissions',
                    parent: null,
                    key: 'document_templates',
                    roles: {},
                },
            ],
            [
                { id: 'acme-workspace', label: 'acme.menu.workspace', position: 40 },
                {
                    id: 'acme-documents',
                    parent: 'acme-workspace',
                    privilege: 'documents.viewer',
                },
            ],
            {
                acme: [{ group: 'acme', privilege: 'document_templates.viewer', icon: 'regular-file' }],
            },
            translate,
        );

        expect(catalog.map((group) => group.id)).toEqual([
            'navigation:acme-workspace',
            'settings:acme',
        ]);
        expect(catalog[0].resources[0].key).toBe('documents');
        expect(catalog[1].resources[0].key).toBe('document_templates');
    });

    it('keeps a plugin-owned menu as its own permission group', () => {
        const catalog = buildPermissionCatalog(
            [
                {
                    category: 'permissions',
                    parent: null,
                    key: 'documents',
                    roles: {},
                },
            ],
            [
                { id: 'ct-content', label: 'Content', moduleType: 'core' },
                {
                    id: 'acme-documents',
                    parent: 'ct-content',
                    label: 'Acme documents',
                    privilege: 'documents.viewer',
                    moduleType: 'plugin',
                },
            ],
            {},
            translate,
        );

        expect(catalog).toHaveLength(1);
        expect(catalog[0]).toMatchObject({
            id: 'navigation:acme-documents',
            label: 'Acme documents',
        });
    });

    it('uses the registered menu label when a permission snippet is missing', () => {
        const catalog = buildPermissionCatalog(
            [
                {
                    category: 'permissions',
                    parent: null,
                    key: 'documents',
                    roles: {},
                },
            ],
            [
                { id: 'ct-content', label: 'Content' },
                {
                    id: 'documents',
                    parent: 'ct-content',
                    label: 'Documents',
                    privilege: 'documents.viewer',
                },
            ],
            {},
            translate,
        );

        expect(catalog[0].resources[0].label).toBe('Documents');
    });
});
