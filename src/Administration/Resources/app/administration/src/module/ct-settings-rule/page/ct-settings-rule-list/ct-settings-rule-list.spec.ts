import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import component from './index';

type RuleListVm = {
    term: string;
    page: number;
    columns: Array<{ property: string }>;
    additionalContextButtons: Array<{ key: string }>;
    onSearch: (value: string) => void;
    onSort: (value: { sortBy: string; sortDirection: 'ASC' | 'DESC' }) => void;
};

describe('ct-settings-rule-list', () => {
    function createWrapper() {
        const result = Object.assign(
            [
                {
                    id: 'rule-id',
                    name: 'Working hours',
                    description: 'Business hours',
                    priority: 100,
                    conditions: [],
                    createdAt: '2026-08-21T10:00:00.000Z',
                },
            ],
            { total: 1 },
        );
        const search = jest.fn(() => Promise.resolve(result));
        const repository = {
            search,
            delete: jest.fn(() => Promise.resolve()),
            create: jest.fn(() => ({ id: 'duplicate-id' })),
            save: jest.fn(() => Promise.resolve()),
        };
        const wrapper = shallowMount(component, {
            global: {
                provide: {
                    repositoryFactory: { create: () => repository },
                    acl: { can: () => true },
                    [routerKey]: { push: jest.fn() },
                },
                stubs: {
                    'ct-block': true,
                    'ct-page': true,
                    'mt-button': true,
                    'mt-data-table': true,
                    'mt-modal-root': true,
                    'mt-modal': true,
                },
            },
        }) as unknown as VueWrapper<RuleListVm>;

        return { wrapper, search };
    }

    it('uses the Meteor data table context actions and loads rules', async () => {
        const { wrapper, search } = createWrapper();
        await flushPromises();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.columns).not.toContainEqual(expect.objectContaining({ property: 'actions' }));
        expect(wrapper.vm.additionalContextButtons).toEqual([
            { key: 'edit', label: 'global.default.edit' },
            { key: 'duplicate', label: 'global.default.duplicate' },
        ]);
    });

    it('resets pagination when searching or sorting', async () => {
        const { wrapper, search } = createWrapper();
        await flushPromises();

        wrapper.vm.page = 3;
        wrapper.vm.onSearch('hours');
        await flushPromises();
        expect(wrapper.vm.page).toBe(1);

        wrapper.vm.onSort({ sortBy: 'name', sortDirection: 'ASC' });
        await flushPromises();
        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.page).toBe(1);
    });
});
