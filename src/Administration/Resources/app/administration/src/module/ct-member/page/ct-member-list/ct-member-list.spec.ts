import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import component from './index';

type MemberListVm = {
    columns: Array<{ property: string }>;
    additionalContextButtons: Array<{ key: string; label: string }>;
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
                    'ct-block': true,
                    'ct-page': true,
                    'mt-data-table': true,
                },
            },
        }) as unknown as VueWrapper<MemberListVm>;

        await flushPromises();

        expect(wrapper.vm.columns).not.toContainEqual(expect.objectContaining({ property: 'actions' }));
        expect(wrapper.vm.additionalContextButtons).toEqual([{ key: 'edit', label: 'global.default.edit' }]);

        wrapper.unmount();
    });
});
