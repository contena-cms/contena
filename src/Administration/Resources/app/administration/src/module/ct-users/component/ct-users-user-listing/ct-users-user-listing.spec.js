import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/mixin/translate-with-fallback.mixin';

const users = [
    {
        id: 'user-1',
        username: 'maxmuster',
        name: 'Max Mustermann',
        userCode: '10001',
        phoneNumber: '13800138000',
        email: 'max@mustermann.com',
        active: false,
        aclRoles: [{ name: 'Editors' }],
    },
    {
        id: 'user-2',
        username: 'admin',
        name: 'admin',
        userCode: '10002',
        phoneNumber: null,
        email: 'info@contena.cn',
        active: true,
        aclRoles: [
            { name: 'Administrators' },
            { name: 'Editors' },
        ],
    },
];

async function createWrapper(privileges = []) {
    const router = { push: jest.fn(), replace: jest.fn() };
    const deleteUser = jest.fn(() => Promise.resolve());

    const repositoryFactory = {
        create: (entityName) => ({
            delete: deleteUser,
            search: () => {
                if (entityName === 'acl_role') {
                    return Promise.resolve([
                        { id: 'role-1', name: 'Administrators' },
                        { id: 'role-2', name: 'Editors' },
                    ]);
                }

                return Promise.resolve(
                    new EntityCollection('user', 'user', Contena.Context.api, new Criteria(1), users, users.length),
                );
            },
        }),
    };

    const wrapper = mount(await wrapTestComponent('ct-users-user-listing', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            provide: {
                [routeLocationKey]: { name: 'user-listing', query: {}, params: {} },
                [routerKey]: router,
                acl: { can: (identifier) => privileges.includes(identifier) },
                userService: {},
                repositoryFactory,
                searchRankingService: { isValidTerm: () => true },
            },
            stubs: {
                'mt-data-table': {
                    name: 'MtDataTable',
                    inheritAttrs: false,
                    props: [
                        'dataSource',
                        'columns',
                        'isLoading',
                        'paginationTotalItems',
                        'currentPage',
                        'paginationLimit',
                        'sortBy',
                        'sortDirection',
                        'layout',
                        'disableSearch',
                        'allowRowSelection',
                        'allowBulkDelete',
                        'selectedRows',
                        'disableDelete',
                        'additionalContextButtons',
                        'filters',
                        'appliedFilters',
                    ],
                    template: `
                            <div class="mt-data-table">
                                <div v-for="item in dataSource" :key="item.id" class="mt-data-table__row">
                                    <slot name="column-username" :data="item" />
                                    <slot name="column-aclRoles" :data="item" />
                                    <slot name="column-active" :data="item" />
                                </div>
                                <slot name="toolbar" />
                                <slot name="empty-state" />
                            </div>
                        `,
                },
                'mt-link': { template: '<a><slot /></a>' },
                'mt-avatar': true,
                'mt-badge': { template: '<span><slot /></span>' },
                'mt-empty-state': true,
                'mt-modal-root': { template: '<div><slot /></div>' },
                'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                'mt-button': { template: '<button><slot /></button>' },
            },
        },
    });

    return { wrapper, router, deleteUser };
}

describe('module/ct-users/component/ct-users-user-listing', () => {
    it('configures an mt data table with users and native controls', async () => {
        const { wrapper } = await createWrapper([
            'users_and_permissions.viewer',
            'users_and_permissions.deleter',
        ]);
        await flushPromises();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        expect(table.props('dataSource')).toHaveLength(2);
        expect(table.props('allowRowSelection')).toBe(true);
        expect(table.props('allowBulkDelete')).toBe(true);
        expect(table.props('selectedRows')).toEqual([]);
        expect(table.props('disableDelete')).toBe(false);
        expect(table.props('disableSearch')).toBe('');
        expect(table.props('layout')).toBe('full');
        expect(table.props('sortBy')).toBeNull();
        expect(table.props('columns')).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ property: 'username', renderer: 'text', position: 100 }),
                expect.objectContaining({ property: 'active', renderer: 'text', position: 700 }),
            ]),
        );
    });

    it('opens the edit drawer from a username without navigating', async () => {
        const { wrapper, router } = await createWrapper(['users_and_permissions.viewer']);
        await flushPromises();

        await wrapper.find('.ct-users-user-listing__columns').trigger('click');

        expect(wrapper.emitted('edit')).toEqual([[users[0]]]);
        expect(router.push).not.toHaveBeenCalled();
    });

    it('places the create action in the table toolbar', async () => {
        const { wrapper } = await createWrapper(['users_and_permissions.creator']);
        await flushPromises();

        const createButton = wrapper.find('.ct-users__create-user');

        expect(createButton.exists()).toBe(true);
        expect(createButton.attributes('disabled')).toBeUndefined();

        await createButton.trigger('click');
        expect(wrapper.emitted('create')).toEqual([[]]);
    });

    it('exposes status and role filters to the mt data table', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        const table = wrapper.getComponent({ name: 'MtDataTable' });
        expect(table.props('filters')).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: 'status' }),
                expect.objectContaining({ id: 'role' }),
            ]),
        );

        await table.vm.$emit('update:applied-filters', [
            { id: 'status', type: { options: [{ id: 'active', label: 'Active' }] } },
        ]);
        await flushPromises();

        expect(wrapper.vm.statusFilter).toBe('active');
        expect(wrapper.vm.userCriteria.filters).toEqual([Criteria.equals('active', true)]);
    });

    it('keeps row selection controlled by the listing', async () => {
        const { wrapper } = await createWrapper(['users_and_permissions.deleter']);
        await flushPromises();
        const table = wrapper.getComponent({ name: 'MtDataTable' });

        await table.vm.$emit('selection-change', { id: 'user-1', value: true });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.selectedUserIds).toEqual(['user-1']);
        expect(table.props('selectedRows')).toEqual(['user-1']);

        await table.vm.$emit('selection-change', { id: 'user-1', value: false });
        expect(wrapper.vm.selectedUserIds).toEqual([]);
    });

    it('handles table pagination and sorting events', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        const table = wrapper.getComponent({ name: 'MtDataTable' });
        const search = jest.spyOn(wrapper.vm.userRepository, 'search');

        await table.vm.$emit('pagination-limit-change', 50);
        await table.vm.$emit('pagination-current-page-change', 2);
        await table.vm.$emit('sort-change', { sortBy: 'email', sortDirection: 'DESC' });
        await flushPromises();

        expect(wrapper.vm.limit).toBe(50);
        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.sortBy).toBe('email');
        expect(wrapper.vm.sortDirection).toBe('DESC');
        expect(search).toHaveBeenCalled();
    });

    it('emits the table context edit action according to ACL', async () => {
        const { wrapper, router } = await createWrapper(['users_and_permissions.viewer']);
        await wrapper.vm.onContextSelect({ key: 'edit', data: users[0] });

        expect(wrapper.emitted('edit')).toEqual([[users[0]]]);
        expect(router.push).not.toHaveBeenCalled();
    });

    it('confirms and executes single and bulk deletes', async () => {
        const { wrapper, deleteUser } = await createWrapper(['users_and_permissions.deleter']);
        await flushPromises();

        wrapper.vm.onDelete(users[0]);
        expect(wrapper.vm.itemsToDelete).toEqual([users[0]]);
        await wrapper.vm.onConfirmDelete(users[0]);
        await flushPromises();
        expect(deleteUser).toHaveBeenCalledWith('user-1', Contena.Context.api);

        wrapper.vm.onMultipleSelectionChange({
            selections: [
                'user-1',
                'user-2',
            ],
            value: true,
        });
        wrapper.vm.onBulkDelete();
        expect(wrapper.vm.itemsToDelete).toHaveLength(2);
        await wrapper.vm.onConfirmDelete(users[0]);
        await flushPromises();
        expect(deleteUser).toHaveBeenCalledWith('user-2', Contena.Context.api);
        expect(wrapper.vm.itemToDelete).toBeNull();
    });

    it('does not allow deletion without the deleter privilege', async () => {
        const { wrapper, deleteUser } = await createWrapper();
        wrapper.vm.onDelete(users[0]);
        await wrapper.vm.onConfirmDelete(users[0]);

        expect(wrapper.vm.itemToDelete).toBeNull();
        expect(deleteUser).not.toHaveBeenCalled();
    });
});
