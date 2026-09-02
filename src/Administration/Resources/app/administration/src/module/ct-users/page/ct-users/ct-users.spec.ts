import { mount, type VueWrapper } from '@vue/test-utils';
import { routerKey } from 'vue-router';

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
    onCreateUser(): void;
    onEditUser(user: { id: string }): void;
    $refs: {
        userListing: UserListingVm;
    };
}

describe('modules/ct-users/page/ct-users', () => {
    let wrapper: VueWrapper<UserPageVm>;
    let routerPush: jest.Mock;

    async function createWrapper(privileges: string[] = []) {
        routerPush = jest.fn();
        wrapper = mount(
            await wrapTestComponent('ct-users', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        [routerKey]: { push: routerPush },
                        acl: {
                            can: (privilege: string) => privileges.includes(privilege),
                        },
                    },
                    stubs: {
                        'ct-page': {
                            name: 'CtPage',
                            props: ['showSmartBar'],
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
                            name: 'CtSearchBar',
                            props: [
                                'initialSearchType',
                                'ignoreRouteTerm',
                            ],
                            template: '<div class="global-search"></div>',
                        },
                        'ct-users-user-listing': {
                            emits: ['create'],
                            template:
                                '<div class="user-listing"><button class="ct-users__create-user" @click="$emit(\'create\')">新增</button></div>',
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
                        'mt-button': {
                            props: [
                                'disabled',
                                'isLoading',
                                'size',
                            ],
                            template: '<button v-bind="$attrs" :disabled="disabled"><slot /></button>',
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

    it('renders the user list and global search control', () => {
        expect(wrapper.find('.user-listing').exists()).toBe(true);
        expect(wrapper.find('.global-search').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'CtSearchBar' }).props('initialSearchType')).toBe('user');
        expect(wrapper.findComponent({ name: 'CtSearchBar' }).props('ignoreRouteTerm')).toBe(true);
        expect(wrapper.findComponent({ name: 'CtPage' }).props('showSmartBar')).toBe(false);
    });

    it('forwards global searches to the user listing', async () => {
        const onSearch = jest.spyOn(wrapper.vm.$refs.userListing, 'onSearch');
        const searchBar = wrapper.findComponent({ name: 'CtSearchBar' });

        (searchBar.vm as unknown as { $emit: (event: string, ...args: unknown[]) => void }).$emit('search', 'alex');
        await wrapper.vm.$nextTick();

        expect(onSearch).toHaveBeenCalledWith('alex');
    });

    it('navigates to the create page', async () => {
        (wrapper.vm as unknown as { onCreateUser: () => void }).onCreateUser();

        expect(routerPush).toHaveBeenCalledWith({ name: 'ct.users.create' });
    });

    it('navigates to the selected user detail page', async () => {
        wrapper.vm.onEditUser({ id: 'user-42' });

        expect(routerPush).toHaveBeenCalledWith({ name: 'ct.users.detail', params: { id: 'user-42' } });
    });

    it('navigates to the create page from the listing toolbar action', async () => {
        await wrapper.find('.ct-users__create-user').trigger('click');

        expect(routerPush).toHaveBeenCalledWith({ name: 'ct.users.create' });
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
