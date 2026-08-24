import { mount, RouterLinkStub } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/mixin/translate-with-fallback.mixin';

async function createWrapper(privileges = [], props = {}) {
    const router = {
        push: jest.fn(),
        replace: jest.fn(),
    };

    return mount(
        await wrapTestComponent('ct-users-user-listing', {
            sync: true,
        }),
        {
            props,
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    [routeLocationKey]: {
                        name: 'user-listing',
                        query: {},
                        params: {},
                    },
                    [routerKey]: router,
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    userService: {},
                    repositoryFactory: {
                        create: (entityName) => ({
                            delete: () => Promise.resolve(),
                            search: () => {
                                if (entityName === 'acl_role') {
                                    return Promise.resolve([
                                        { id: 'role-1', name: 'Administrators' },
                                        { id: 'role-2', name: 'Editors' },
                                    ]);
                                }

                                return Promise.resolve(
                                    new EntityCollection(
                                        'user',
                                        'user',
                                        Contena.Context.api,
                                        new Criteria(1),
                                        [
                                            {
                                                id: '019bff8c86e773e79ec5538c7b1edabc',
                                                username: 'maxmuster',
                                                name: 'Max Mustermann',
                                                phoneNumber: '13800138000',
                                                email: 'max@mustermann.com',
                                                active: false,
                                                aclRoles: [
                                                    { name: 'testRole' },
                                                ],
                                            },
                                            {
                                                id: '019bff8c86e773e79ec5538c7b1ed571',
                                                username: 'admin',
                                                name: 'admin',
                                                phoneNumber: null,
                                                email: 'info@contena.cn',
                                                active: true,
                                                aclRoles: [
                                                    { name: 'adminRole' },
                                                    { name: 'superUser' },
                                                ],
                                            },
                                            {
                                                id: '019bff8c86e773e79ec5538c7b1ed572',
                                                username: 'supperadmin',
                                                name: 'supperadmin',
                                                phoneNumber: null,
                                                email: 'user@example.com',
                                                active: true,
                                                admin: true,
                                                aclRoles: [],
                                            },
                                        ],
                                        1,
                                    ),
                                );
                            },
                        }),
                    },
                    loginService: {},
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                },
                mocks: {
                    $route: { query: '' },
                    $router: router,
                },
                stubs: {
                    'ct-entity-listing': await wrapTestComponent('ct-entity-listing'),
                    'router-link': RouterLinkStub,
                    'ct-context-menu-item': {
                        template:
                            '<div class="ct-context-menu-item-stub" :disabled="disabled ? \'true\' : undefined"><slot /></div>',
                        props: [
                            'disabled',
                            'routerLink',
                            'variant',
                        ],
                    },
                    'ct-container': true,
                    'mt-avatar': true,
                    'ct-pagination': true,
                    'ct-context-button': true,
                    'ct-data-grid-settings': true,
                    'ct-data-grid-column-boolean': true,
                    'ct-data-grid-inline-edit': true,
                    'ct-provide': true,
                    'ct-data-grid-skeleton': true,
                    'ct-color-badge': true,
                },
            },
        },
    );
}

describe('module/ct-users/component/ct-users-user-listing', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('provides the expected Ant table columns', async () => {
        await flushPromises();

        expect(wrapper.vm.userColumns).toEqual([
            expect.objectContaining({
                key: 'user',
                title: 'ct-users.user-grid.labelName',
                fixed: 'left',
            }),
            expect.objectContaining({
                key: 'userCode',
                title: 'ct-users.user-grid.labelUserCode',
            }),
            expect.objectContaining({
                key: 'contact',
                title: 'ct-users.user-grid.labelContact',
            }),
            expect.objectContaining({
                key: 'aclRoles',
                title: 'ct-users.user-grid.labelRoles',
            }),
            expect.objectContaining({
                key: 'active',
                title: 'ct-users.user-grid.status',
            }),
            expect.objectContaining({
                key: 'action',
                fixed: 'right',
            }),
        ]);
    });

    it('applies column visibility, ordering and fixed positions', async () => {
        wrapper = await createWrapper([], {
            columnSettings: [
                { key: 'active', title: 'Status', checked: true, fixed: 'left' },
                { key: 'contact', title: 'Contact', checked: false },
                { key: 'user', title: 'User', checked: false, required: true },
                { key: 'action', title: '', checked: true, required: true, fixed: 'right' },
            ],
        });

        expect(wrapper.vm.userColumns.map(({ key }) => key)).toEqual([
            'active',
            'user',
            'action',
        ]);
        expect(wrapper.vm.userColumns[0].fixed).toBe('left');
        expect(wrapper.vm.userColumns[2].fixed).toBe('right');
    });

    it('should use the display name', () => {
        expect(
            wrapper.vm.getUserDisplayName({
                name: 'Max Mustermann',
                username: 'maxmuster',
            }),
        ).toBe('Max Mustermann');

        expect(
            wrapper.vm.getUserDisplayName({
                name: '',
                username: 'maxmuster',
            }),
        ).toBe('maxmuster');
    });

    it('does not render a duplicate search field or card title', () => {
        expect(wrapper.findComponent({ name: 'ct-simple-search-field' }).exists()).toBe(false);
        expect(wrapper.text()).not.toContain('ct-users.general.cardLabel');
    });

    it('deletes a user after the standard confirmation without a password', async () => {
        const user = {
            id: 'user-to-delete',
            username: 'editor',
        };
        const deleteSpy = jest.spyOn(wrapper.vm.userRepository, 'delete').mockResolvedValue();

        wrapper.vm.onDelete(user);
        await wrapper.vm.onConfirmDelete(user);
        await flushPromises();

        expect(deleteSpy).toHaveBeenCalledWith(user.id, Contena.Context.api);
        expect(wrapper.vm.itemToDelete).toBeNull();
    });

    it('should add avatar media as association', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);
        await flushPromises();

        expect(wrapper.vm.userCriteria.associations[1].association).toBe('avatarMedia');
    });

    it('applies status and role filters to the Criteria', async () => {
        wrapper.vm.statusFilter = 'active';
        wrapper.vm.roleFilter = ['role-2'];
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.filterCount).toBe(2);
        expect(wrapper.vm.userCriteria.filters).toEqual([
            Criteria.equals('active', true),
            Criteria.equalsAny('aclRoles.id', ['role-2']),
        ]);
    });

    it('reloads users with the selected status filter', async () => {
        const search = jest.spyOn(wrapper.vm.userRepository, 'search');

        await wrapper.vm.setStatusFilter('inactive');

        expect(search).toHaveBeenCalled();
        expect(search.mock.calls[search.mock.calls.length - 1][0].filters).toEqual([
            Criteria.equals('active', false),
        ]);
    });

    it('resets all user filters', async () => {
        wrapper.vm.statusFilter = 'inactive';
        wrapper.vm.roleFilter = ['role-1'];

        wrapper.vm.resetFilters();
        await flushPromises();

        expect(wrapper.vm.statusFilter).toBe('all');
        expect(wrapper.vm.roleFilter).toEqual([]);
        expect(wrapper.vm.filterCount).toBe(0);
    });

    it('loads roles for the role filter', async () => {
        await flushPromises();

        expect(wrapper.vm.roleFilterOptions).toEqual([
            { value: 'role-1', label: 'Administrators' },
            { value: 'role-2', label: 'Editors' },
        ]);
    });

    it('opens the selected user for editors', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);
        wrapper.vm.onEdit({ id: '019bff8c86e773e79ec5538c7b1ed571' });

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'ct.users.user.detail',
            params: { id: '019bff8c86e773e79ec5538c7b1ed571' },
        });
    });

    it('does not open user editing without editor privilege', () => {
        wrapper.vm.onEdit({ id: 'user-id' });

        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
    });
});
