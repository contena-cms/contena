import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

function createSearchResult(items = []) {
    return Object.assign(items, { total: items.length });
}

async function createWrapper({ privileges = [], roles = [], deleteFunction = jest.fn() } = {}) {
    const search = jest.fn().mockResolvedValue(createSearchResult(roles));
    const repository = {
        search,
        delete: deleteFunction,
    };

    const wrapper = mount(
        await wrapTestComponent('ct-permissions-role-listing', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    [routeLocationKey]: {
                        name: 'role-listing',
                        query: {},
                        params: {},
                    },
                    [routerKey]: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                    repositoryFactory: {
                        create: () => repository,
                    },
                    acl: {
                        can: (identifier) => privileges.includes(identifier),
                    },
                    searchRankingService: {
                        isValidTerm: (term) => Boolean(term?.trim()),
                    },
                },
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                icon: 'solid-content',
                            },
                        },
                    },
                },
                stubs: {
                    'mt-card': {
                        props: ['title'],
                        template: '<section><h3 v-if="title" class="card-title">{{ title }}</h3><slot /></section>',
                    },
                    'ct-data-grid': {
                        props: ['dataSource'],
                        template: `
                            <div class="data-grid">
                                <div v-for="item in dataSource" :key="item.id" class="data-grid-row">
                                    <slot name="column-name" :item="item"></slot>
                                    <slot name="column-createdAt" :item="item"></slot>
                                    <slot name="column-createdBy" :item="item"></slot>
                                    <slot name="column-users" :item="item"></slot>
                                    <slot name="actions" :item="item"></slot>
                                </div>
                                <slot name="pagination"></slot>
                            </div>
                        `,
                    },
                    'ct-context-menu-item': {
                        props: ['disabled'],
                        emits: ['click'],
                        template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                    },
                    'ct-permissions-role-form-modal': {
                        props: ['roleId'],
                        template: '<div class="role-form-modal">{{ roleId }}</div>',
                    },
                    'ct-permissions-role-permissions-modal': {
                        props: ['roleId'],
                        template: '<div class="role-permissions-modal">{{ roleId }}</div>',
                    },
                    'ct-pagination': true,
                    'ct-time-ago': true,
                    'ct-modal': {
                        template: '<div class="modal"><slot /><slot name="modal-footer" /></div>',
                    },
                },
            },
        },
    );

    await flushPromises();

    return { wrapper, repository };
}

describe('module/ct-permissions/component/ct-permissions-role-listing', () => {
    it('loads creator and assigned-user associations', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.roleCriteria.getAssociation('createdBy')).toBeDefined();
        expect(wrapper.vm.roleCriteria.getAssociation('users')).toBeDefined();
        expect(wrapper.vm.rolesColumns).toContainEqual(expect.objectContaining({ property: 'code' }));
        expect(wrapper.vm.rolesColumns).not.toContainEqual(expect.objectContaining({ property: 'permissions' }));
    });

    it('renders the role grid without a separate card header', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.card-title').exists()).toBe(false);
        expect(wrapper.find('ct-simple-search-field-stub').exists()).toBe(false);
        expect(wrapper.find('.ct-permissions-role-listing__add-role-button').exists()).toBe(false);
    });

    it('opens create and edit forms in a modal', async () => {
        const { wrapper } = await createWrapper({
            privileges: ['users_and_permissions.editor'],
            roles: [{ id: 'role-id', name: 'Role' }],
        });

        wrapper.vm.openCreateRole();
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.role-form-modal').exists()).toBe(true);

        wrapper.vm.closeRoleForm();
        wrapper.vm.openEditRole('role-id');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.role-form-modal').text()).toContain('role-id');
    });

    it('opens permission assignment from the row action menu', async () => {
        const { wrapper } = await createWrapper({
            privileges: ['users_and_permissions.viewer'],
            roles: [{ id: 'role-id', name: 'Role' }],
        });

        await wrapper.find('.ct-permissions-role-listing__context-menu-permissions').trigger('click');

        expect(wrapper.find('.role-permissions-modal').text()).toContain('role-id');
    });

    it('does not open permission assignment without viewer privilege', async () => {
        const { wrapper } = await createWrapper({ roles: [{ id: 'role-id', name: 'Role' }] });

        await wrapper.vm.openPermissions('role-id');

        expect(wrapper.find('.role-permissions-modal').exists()).toBe(false);
    });

    it('searches immediately and emits the updated total and loading state', async () => {
        const { wrapper, repository } = await createWrapper();
        const callsBeforeSearch = repository.search.mock.calls.length;

        await wrapper.vm.onSearch('manager');
        await flushPromises();

        expect(wrapper.vm.roleCriteria.term).toBe('manager');
        expect(repository.search.mock.calls.length).toBeGreaterThan(callsBeforeSearch);
        expect(wrapper.emitted('total-change')).toBeTruthy();
        expect(wrapper.emitted('loading-change')).toEqual(
            expect.arrayContaining([
                [true],
                [false],
            ]),
        );
    });

    it('shows creation metadata and only the assigned-user count', async () => {
        const { wrapper } = await createWrapper({
            privileges: ['users_and_permissions.viewer'],
            roles: [
                {
                    id: 'role-id',
                    name: 'Role',
                    createdAt: '2026-08-05T00:00:00.000+00:00',
                    createdBy: { firstName: 'Ada', lastName: 'Lovelace', username: 'ada' },
                    users: [
                        { username: 'first' },
                        { username: 'second' },
                        { username: 'third' },
                    ],
                },
            ],
        });

        expect(wrapper.text()).toContain('Ada Lovelace');
        expect(wrapper.find('.ct-permissions-role-listing__user-count').text()).toBe('3');
        expect(wrapper.text()).not.toContain('first');
        expect(wrapper.text()).not.toContain('second');
        expect(wrapper.text()).not.toContain('third');
    });

    it('leaves an empty creator cell blank', async () => {
        const { wrapper } = await createWrapper({ roles: [{ id: 'role-id', name: 'Role' }] });

        expect(wrapper.find('.ct-permissions-role-listing__created-by').text()).toBe('');
        expect(wrapper.find('.ct-permissions-role-listing__empty-users').exists()).toBe(false);
    });

    it('deletes after the standard confirmation and refreshes the list', async () => {
        const deleteFunction = jest.fn().mockResolvedValue(undefined);
        const { wrapper, repository } = await createWrapper({
            privileges: ['users_and_permissions.deleter'],
            roles: [{ id: 'role-id', name: 'Role' }],
            deleteFunction,
        });
        const callsBeforeDelete = repository.search.mock.calls.length;

        await wrapper.find('.ct-permissions-role-listing__context-menu-delete').trigger('click');
        await wrapper.find('.ct-permissions-role-listing__confirm-delete-button').trigger('click');
        await flushPromises();

        expect(deleteFunction).toHaveBeenCalledWith('role-id', Contena.Context.api);
        expect(repository.search.mock.calls.length).toBeGreaterThan(callsBeforeDelete);
    });
});
