import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import type PrivilegesService from 'src/app/service/privileges.service';

const mockCreateNotificationError = jest.fn();
jest.mock('src/app/composables/use-notification', () => ({
    useNotification: () => ({
        createNotificationError: mockCreateNotificationError,
        createNotificationSuccess: jest.fn(),
    }),
}));

const rule = {
    id: 'rule-id',
    name: 'Nested rule',
    priority: 1,
    description: '',
    isNew: () => false,
    conditions: [
        { id: 'root', parentId: null, type: 'orContainer', value: null, position: 0 },
        { id: 'group', parentId: 'root', type: 'andContainer', value: null, position: 1 },
        {
            id: 'weekday',
            parentId: 'group',
            type: 'dayOfWeek',
            value: { operator: '=', dayOfWeek: 1 },
            position: 1,
        },
    ],
};

const savedConditions: Array<Record<string, unknown>> = [];
const ruleRepository = {
    get: jest.fn(() => Promise.resolve(rule)) as jest.Mock,
    save: jest.fn(() => Promise.resolve()),
    create: jest.fn(),
};
const conditionRepository = {
    search: jest.fn(() => Promise.resolve([])) as jest.Mock,
    create: jest.fn((_context, id: string) => ({ id })),
    save: jest.fn((condition: Record<string, unknown>) => {
        savedConditions.push({ ...condition });
        return Promise.resolve();
    }),
    delete: jest.fn(() => Promise.resolve()),
};
const repositoryFactory = {
    create: jest.fn((entity: string) => (entity === 'rule' ? ruleRepository : conditionRepository)),
};

describe('module/ct-settings-rule/page/ct-settings-rule-detail', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    beforeEach(() => {
        jest.clearAllMocks();
        savedConditions.length = 0;
    });

    it('hydrates and saves the complete nested condition tree', async () => {
        const wrapper = mount(await wrapTestComponent('ct-settings-rule-detail', { sync: true }), {
            props: { ruleId: rule.id },
            global: {
                provide: {
                    repositoryFactory,
                    acl: { can: jest.fn(() => true) },
                    [routerKey as symbol]: { push: jest.fn(), replace: jest.fn() },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': defineComponent({ template: '<div><slot name="content" /></div>' }),
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-card': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-textarea': true,
                    'mt-button': true,
                    'ct-button-process': true,
                    'ct-container': true,
                    'ct-rule-condition-editor': true,
                },
            },
        });
        await flushPromises();

        const page = wrapper.vm as unknown as {
            matchMode: 'all' | 'any';
            conditions: Array<{ type: string; children?: Array<{ type: string }> }>;
            onSave: () => Promise<void>;
        };
        expect(page.matchMode).toBe('any');
        expect(page.conditions).toEqual([
            expect.objectContaining({
                type: 'andContainer',
                children: [expect.objectContaining({ type: 'dayOfWeek' })],
            }),
        ]);
        await page.onSave();

        expect(mockCreateNotificationError).not.toHaveBeenCalled();
        expect(savedConditions).toHaveLength(3);
        expect(savedConditions[0]).toEqual(expect.objectContaining({ parentId: null, type: 'orContainer' }));
        expect(savedConditions[1]).toEqual(
            expect.objectContaining({ parentId: savedConditions[0].id, type: 'andContainer' }),
        );
        expect(savedConditions[2]).toEqual(expect.objectContaining({ parentId: savedConditions[1].id, type: 'dayOfWeek' }));
        wrapper.unmount();
    });

    it('loads rule conditions with stable pagination and sorting', async () => {
        const wrapper = mount(await wrapTestComponent('ct-settings-rule-detail', { sync: true }), {
            props: { ruleId: rule.id },
            global: {
                provide: {
                    repositoryFactory,
                    acl: { can: jest.fn(() => true) },
                    [routerKey as symbol]: { push: jest.fn(), replace: jest.fn() },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': defineComponent({ template: '<div><slot name="content" /></div>' }),
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-card': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-textarea': true,
                    'mt-button': true,
                    'ct-button-process': true,
                    'ct-container': true,
                    'ct-rule-condition-editor': true,
                },
            },
        });
        await flushPromises();

        const firstCall = ruleRepository.get.mock.calls[0] as unknown[];
        const criteria = firstCall[2] as {
            getAssociation: (name: string) => {
                getLimit: () => number | null;
                sortings: unknown[];
            };
        };
        const conditionCriteria = criteria.getAssociation('conditions');

        expect(conditionCriteria.getLimit()).toBe(500);
        expect(conditionCriteria.sortings).toEqual([
            { field: 'parentId', naturalSorting: false, order: 'ASC' },
            { field: 'position', naturalSorting: false, order: 'ASC' },
            { field: 'id', naturalSorting: false, order: 'ASC' },
        ]);
        wrapper.unmount();
    });

    it('loads condition pages after the association limit', async () => {
        const firstPage = Array.from({ length: 500 }, (_, index) => ({
            id: `condition-${index}`,
            parentId: null,
            type: 'dayOfWeek',
            value: { operator: '=', dayOfWeek: 1 },
            position: index,
        }));
        const storedConditions = Object.assign(firstPage, {
            total: 501,
            criteria: { page: 1 },
            context: Contena.Context.api,
        });
        const nextPage = Object.assign(
            [
                {
                    id: 'condition-500',
                    parentId: null,
                    type: 'dayOfWeek',
                    value: { operator: '=', dayOfWeek: 1 },
                    position: 500,
                },
            ],
            { total: 501, criteria: { page: 2 } },
        );
        ruleRepository.get.mockResolvedValueOnce({ ...rule, conditions: storedConditions });
        conditionRepository.search.mockResolvedValueOnce(nextPage);

        const wrapper = mount(await wrapTestComponent('ct-settings-rule-detail', { sync: true }), {
            props: { ruleId: rule.id },
            global: {
                provide: {
                    repositoryFactory,
                    acl: { can: jest.fn(() => true) },
                    [routerKey as symbol]: { push: jest.fn(), replace: jest.fn() },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': defineComponent({ template: '<div><slot name="content" /></div>' }),
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-card': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-textarea': true,
                    'mt-button': true,
                    'ct-button-process': true,
                    'ct-container': true,
                    'ct-rule-condition-editor': true,
                },
            },
        });
        await flushPromises();

        expect(conditionRepository.search).toHaveBeenCalledWith(
            expect.objectContaining({ page: 2, limit: 500 }),
            Contena.Context.api,
        );
        expect((wrapper.vm as unknown as { conditions: unknown[] }).conditions).toHaveLength(501);
        wrapper.unmount();
    });
});
