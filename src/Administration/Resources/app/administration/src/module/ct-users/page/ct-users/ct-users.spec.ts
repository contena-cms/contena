import { mount, type VueWrapper } from '@vue/test-utils';

interface UserListingVm {
    setStatusFilter(value: string): void;
    resetFilters(): void;
    onSearch(term: string): void;
}

interface UserPageVm {
    statusFilter: string;
    onStatusFilterChange(value: string): void;
    resetUserFilters(): void;
    onUserSearch(term: string): void;
    $refs: {
        userListing: UserListingVm;
    };
}

describe('modules/ct-users/page/ct-users', () => {
    let wrapper: VueWrapper<UserPageVm>;

    async function createWrapper(privileges: string[] = []) {
        wrapper = mount(
            await wrapTestComponent('ct-users', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        acl: {
                            can: (privilege: string) => privileges.includes(privilege),
                        },
                    },
                    stubs: {
                        'ct-page': {
                            template: `
                                <div>
                                    <slot name="search-bar"></slot>
                                    <slot name="smart-bar-header"></slot>
                                    <slot name="smart-bar-actions"></slot>
                                    <slot name="content"></slot>
                                </div>
                            `,
                        },
                        'ct-card-view': {
                            template: '<div class="ct-card-view"><slot /></div>',
                        },
                        'ct-search-bar': {
                            template: '<div class="user-search"></div>',
                        },
                        'ct-users-user-listing': {
                            template: '<div class="user-listing"></div>',
                            data() {
                                return {
                                    filterCount: 0,
                                    statusFilter: 'all',
                                    roleFilter: [],
                                    statusFilterOptions: [],
                                    roleFilterOptions: [],
                                };
                            },
                            methods: {
                                onSearch: jest.fn(),
                                getList: jest.fn(),
                                setStatusFilter: jest.fn(),
                                setRoleFilter: jest.fn(),
                                resetFilters: jest.fn(),
                            },
                        },
                        'mt-select': {
                            name: 'mt-select',
                            template: '<div class="mt-select-stub" />',
                        },
                    },
                },
            },
        ) as unknown as VueWrapper<UserPageVm>;

        await flushPromises();
    }

    beforeEach(async () => {
        await createWrapper();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('renders the user list, search and filter controls', () => {
        expect(wrapper.find('.user-listing').exists()).toBe(true);
        expect(wrapper.find('.user-search').exists()).toBe(true);
        expect(wrapper.find('.ct-users__filter-menu-trigger').exists()).toBe(true);
    });

    it('enables user creation for creators', async () => {
        wrapper.unmount();
        await createWrapper(['users_and_permissions.creator']);

        expect(wrapper.find('.ct-users__create-user').attributes('disabled')).toBeUndefined();
    });

    it('uses default sizing for the filter and create-user actions', () => {
        const filterButton = wrapper.findComponent('.ct-users__filter-menu-trigger') as VueWrapper;
        const createButton = wrapper.findComponent('.ct-users__create-user') as VueWrapper;

        expect((filterButton.props() as { size?: string }).size).toBe('default');
        expect((createButton.props() as { size?: string }).size).toBe('default');
    });

    it('forwards status filter changes and keeps the selection visible', async () => {
        const setStatusFilter = jest.spyOn(wrapper.vm.$refs.userListing, 'setStatusFilter');

        wrapper.vm.onStatusFilterChange('active');
        await wrapper.vm.$nextTick();

        expect(setStatusFilter).toHaveBeenCalledWith('active');
        expect(wrapper.vm.statusFilter).toBe('active');

        wrapper.vm.onStatusFilterChange('active');
        expect(setStatusFilter).toHaveBeenCalledTimes(1);
    });

    it('resets the status selection and listing filters together', async () => {
        const resetFilters = jest.spyOn(wrapper.vm.$refs.userListing, 'resetFilters');

        wrapper.vm.onStatusFilterChange('inactive');
        await wrapper.vm.$nextTick();
        wrapper.vm.resetUserFilters();

        expect(resetFilters).toHaveBeenCalled();
        expect(wrapper.vm.statusFilter).toBe('all');
    });

    it('forwards searches to the user listing', () => {
        const onSearch = jest.spyOn(wrapper.vm.$refs.userListing, 'onSearch');

        wrapper.vm.onUserSearch('alex');

        expect(onSearch).toHaveBeenCalledWith('alex');
    });
});
