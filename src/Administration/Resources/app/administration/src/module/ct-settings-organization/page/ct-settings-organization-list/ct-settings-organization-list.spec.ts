/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import { mount } from '@vue/test-utils';

function collection<T>(items: T[]) {
    return Object.assign(items, {
        first: () => items[0] ?? null,
        total: items.length,
    });
}

async function createWrapper(privileges: string[] = []) {
    const createdOrganization = {
        id: 'new-organization-id',
        name: '',
        code: '',
        organizationUnitId: null as string | null,
        parentId: null as string | null,
        position: 0,
        active: false,
        isNew: () => true,
    };
    const persistedOrganization = {
        id: 'company-id',
        name: 'Contena',
        code: 'CONTENA',
        organizationUnitId: 'company-unit-id',
        isNew: () => false,
    };
    const organizationRepository = {
        search: jest.fn(() =>
            Promise.resolve(
                collection([
                    {
                        id: 'company-id',
                        parentId: null,
                        childCount: 1,
                        position: 1,
                        name: 'Contena',
                        code: 'CONTENA',
                        translated: { name: 'Contena' },
                    },
                ]),
            ),
        ),
        create: jest.fn(() => createdOrganization),
        get: jest.fn(() => Promise.resolve(persistedOrganization)),
        save: jest.fn(),
        syncDeleted: jest.fn(),
    };
    const organizationUnitRepository = {
        search: jest.fn(() =>
            Promise.resolve(
                collection([
                    { id: 'company-unit-id', technicalName: 'company', name: 'Company' },
                    { id: 'department-unit-id', technicalName: 'department', name: 'Department' },
                ]),
            ),
        ),
    };
    const wrapper = mount(await wrapTestComponent('ct-settings-organization-list', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: (entityName: string) =>
                        entityName === 'organization_unit' ? organizationUnitRepository : organizationRepository,
                },
                acl: {
                    can: (privilege: string) => privileges.includes(privilege),
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: jest.fn(() => Promise.resolve([{ id: 'organization-fields' }])),
                },
            },
            mocks: { $t: (key: string) => key },
            stubs: {
                'ct-page': {
                    template:
                        '<div><slot name="search-bar" /><slot name="language-switch" /><slot name="smart-bar-actions" /><slot name="content" /><slot /></div>',
                },
                'ct-card-view': { template: '<div><slot /></div>' },
                'mt-card': { template: '<div><slot /><slot name="grid" /></div>' },
                'mt-button': {
                    props: ['disabled'],
                    template: '<button :disabled="disabled"><slot /></button>',
                },
                'ct-search-bar': true,
                'ct-language-switch': true,
                'mt-organization-tree': true,
                'mt-organization-form': true,
                'mt-empty-state': true,
                'mt-icon': true,
            },
        },
    });

    await flushPromises();
    return { wrapper: wrapper as any, organizationRepository, organizationUnitRepository, createdOrganization };
}

describe('module/ct-settings-organization/page/ct-settings-organization-list', () => {
    it('loads the Organization tree and independent Organization Unit aggregate', async () => {
        const { wrapper, organizationRepository, organizationUnitRepository } = await createWrapper([
            'organization.viewer',
        ]);

        expect(organizationRepository.search).toHaveBeenCalledTimes(1);
        expect(organizationUnitRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.treeItems[0].childCount).toBe(1);
        expect(wrapper.vm.organizationUnits).toHaveLength(2);
    });

    it('defaults roots to Company and children to Department units', async () => {
        const { wrapper, createdOrganization } = await createWrapper(['organization.creator']);

        wrapper.vm.onAddOrganization();
        expect(createdOrganization).toMatchObject({
            parentId: null,
            organizationUnitId: 'company-unit-id',
            active: true,
        });

        wrapper.vm.onAddChildOrganization({ id: 'company-id' });
        expect(createdOrganization).toMatchObject({
            parentId: 'company-id',
            organizationUnitId: 'department-unit-id',
        });
    });

    it('links tree selection, form updates and deletion to the repository', async () => {
        const { wrapper, organizationRepository } = await createWrapper([
            'organization.editor',
            'organization.deleter',
        ]);

        await wrapper.vm.onSelectOrganization({ id: 'company-id' });
        wrapper.vm.onUpdateOrganization('name', 'Contena Technology');
        expect(wrapper.vm.currentOrganization.name).toBe('Contena Technology');

        await wrapper.vm.onDeleteOrganization({ id: 'company-id' });
        expect(organizationRepository.syncDeleted).toHaveBeenCalledWith(['company-id'], Contena.Context.api);
        expect(wrapper.vm.currentOrganization).toBeNull();
    });

    it('keeps write actions behind Organization privileges', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.canCreate).toBe(false);
        expect(wrapper.vm.canEdit).toBe(false);
        expect(wrapper.vm.canDelete).toBe(false);
        expect(wrapper.find('.ct-settings-organization-list__add-action').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.ct-settings-organization-list__save-action').attributes('disabled')).toBeDefined();
    });
});
