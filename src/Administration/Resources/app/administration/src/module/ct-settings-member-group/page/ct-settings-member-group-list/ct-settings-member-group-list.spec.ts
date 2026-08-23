import { shallowMount, type VueWrapper } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import component from './index';

interface MemberGroupListVm {
    memberGroupToDelete: Entity<'member_group'> | null;
    deleteMemberGroup: () => Promise<void>;
    columns: Array<{ property: string }>;
    additionalContextButtons: Array<{ key: string; label: string }>;
}

describe('module/ct-settings-member-group/page/ct-settings-member-group-list', () => {
    let wrapper: VueWrapper;
    let repositoryDelete: jest.Mock;

    beforeEach(async () => {
        const result = Object.assign([], { total: 0 });
        repositoryDelete = jest.fn().mockResolvedValue(undefined);

        wrapper = shallowMount(component, {
            global: {
                provide: {
                    [routerKey]: { push: jest.fn() },
                    repositoryFactory: {
                        create: jest.fn(() => ({
                            search: jest.fn().mockResolvedValue(result),
                            delete: repositoryDelete,
                        })),
                    },
                    acl: { can: jest.fn(() => true) },
                },
            },
        });

        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it.each([
        [
            'a Member',
            [{ id: 'member-1' }],
            [],
        ],
        [
            'a Channel',
            [],
            [{ id: 'channel-1' }],
        ],
    ])('does not delete a MemberGroup assigned to %s', async (_assignment, members, channels) => {
        const vm = wrapper.vm as unknown as MemberGroupListVm;
        vm.memberGroupToDelete = { id: 'group-1', members, channels } as unknown as Entity<'member_group'>;

        await vm.deleteMemberGroup();

        expect(repositoryDelete).not.toHaveBeenCalled();
        expect(vm.memberGroupToDelete).toBeNull();
    });

    it('deletes an unassigned MemberGroup', async () => {
        const vm = wrapper.vm as unknown as MemberGroupListVm;
        vm.memberGroupToDelete = {
            id: 'group-1',
            members: [],
            channels: [],
        } as unknown as Entity<'member_group'>;

        await vm.deleteMemberGroup();

        expect(repositoryDelete).toHaveBeenCalledWith('group-1', Contena.Context.api);
        expect(vm.memberGroupToDelete).toBeNull();
    });

    it('uses the shared table context column for row actions', () => {
        const vm = wrapper.vm as unknown as MemberGroupListVm;

        expect(vm.columns).not.toContainEqual(expect.objectContaining({ property: 'actions' }));
        expect(vm.additionalContextButtons).toEqual([{ key: 'edit', label: 'global.default.edit' }]);
    });
});
