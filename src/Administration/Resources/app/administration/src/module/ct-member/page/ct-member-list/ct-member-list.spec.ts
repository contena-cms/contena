import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import component from './index';

type MemberListVm = {
    columns: Array<{ property: string }>;
    additionalContextButtons: Array<{ key: string; label: string }>;
    filters: Array<{ id: string }>;
    groupFilter: string | null;
    channelFilter: string | null;
    onAppliedFiltersChange(value: unknown[]): void;
};

describe('ct-member-list', () => {
    it('uses the shared table context column for row actions', async () => {
        const result = Object.assign([], { total: 0 });
        const wrapper = shallowMount(component, {
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({ search: jest.fn(() => Promise.resolve(result)) }),
                    },
                    acl: { can: jest.fn(() => true) },
                    [routerKey]: { push: jest.fn() },
                },
                stubs: {
                    'ct-block': { template: '<div><slot /></div>' },
                    'ct-page': { template: '<div><slot name="search-bar" /><slot name="content" /></div>' },
                    'ct-search-bar': {
                        name: 'CtSearchBar',
                        props: [
                            'initialSearchType',
                            'ignoreRouteTerm',
                        ],
                        template: '<div />',
                    },
                    'mt-data-table': true,
                },
            },
        }) as unknown as VueWrapper<MemberListVm>;

        await flushPromises();

        expect(wrapper.vm.columns).not.toContainEqual(expect.objectContaining({ property: 'actions' }));
        expect(wrapper.vm.additionalContextButtons).toEqual([{ key: 'edit', label: 'global.default.edit' }]);
        expect(wrapper.vm.filters.map((filter) => filter.id)).toEqual([
            'group',
            'channel',
        ]);
        expect(wrapper.findComponent({ name: 'CtSearchBar' }).props('initialSearchType')).toBe('member');
        expect(wrapper.findComponent({ name: 'CtSearchBar' }).props('ignoreRouteTerm')).toBe(true);

        wrapper.vm.onAppliedFiltersChange([
            { id: 'group', type: { options: [{ id: 'group-1', label: 'Group 1' }] } },
        ]);
        expect(wrapper.vm.groupFilter).toBe('group-1');
        expect(wrapper.vm.channelFilter).toBeNull();

        wrapper.unmount();
    });
});
