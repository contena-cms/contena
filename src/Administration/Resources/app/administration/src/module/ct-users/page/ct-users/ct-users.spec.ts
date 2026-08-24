import { mount, type VueWrapper } from '@vue/test-utils';
import type { TableColumnSetting } from 'src/app/component/base/ct-table-column-setting';

interface UserListingVm {
    setStatusFilter(value: string): void;
    resetFilters(): void;
    onSearch(term: string): void;
}

interface UserPageVm {
    statusFilter: string;
    roleFilter: string[];
    columnSettings: TableColumnSetting[];
    defaultColumnSettings: TableColumnSetting[];
    onStatusFilterChange(value: string): void;
    resetUserFilters(): void;
    onUserSearch(term: string): void;
    onColumnSettingsApply(settings: TableColumnSetting[]): void;
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
                                    <slot name="content"></slot>
                                </div>
                            `,
                        },
                        'ct-users-user-listing': {
                            props: ['columnSettings'],
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
                        'ct-table-column-setting': {
                            props: [
                                'columns',
                                'defaultColumns',
                            ],
                            template: '<button class="column-setting"></button>',
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

    it('renders one page heading with Ant search and filter controls', () => {
        expect(wrapper.find('.user-listing').exists()).toBe(true);
        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.find('.ct-users__search').exists()).toBe(true);
        expect(wrapper.find('.ct-users__filter').exists()).toBe(true);
        expect(wrapper.find('.ct-users__role-filter').exists()).toBe(true);
        expect(wrapper.find('.ct-users__table-tools').exists()).toBe(true);
    });

    it('enables user creation for creators', async () => {
        wrapper.unmount();
        await createWrapper(['users_and_permissions.creator']);

        expect(wrapper.vm.acl.can('users_and_permissions.creator')).toBe(true);
    });

    it('forwards status filter changes and keeps the selection visible', async () => {
        const setStatusFilter = jest.spyOn(wrapper.vm.$refs.userListing, 'setStatusFilter');

        wrapper.vm.onStatusFilterChange('active');
        await wrapper.vm.$nextTick();

        expect(setStatusFilter).toHaveBeenCalledWith('active');
        expect(wrapper.vm.statusFilter).toBe('active');

        wrapper.vm.onStatusFilterChange('active');
        expect(setStatusFilter).toHaveBeenCalledTimes(2);
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

    it('provides ordered Ant table column settings with required fixed columns', () => {
        expect(wrapper.vm.defaultColumnSettings).toEqual([
            expect.objectContaining({ key: 'user', checked: true, fixed: 'left', required: true }),
            expect.objectContaining({ key: 'userCode', checked: true }),
            expect.objectContaining({ key: 'contact', checked: true }),
            expect.objectContaining({ key: 'aclRoles', checked: true }),
            expect.objectContaining({ key: 'active', checked: true }),
            expect.objectContaining({ key: 'action', checked: true, fixed: 'right', required: true }),
        ]);
    });

    it('applies the complete column configuration to the user listing', async () => {
        const settings: TableColumnSetting[] = [
            { key: 'user', title: 'Name', checked: true, required: true, fixed: 'left' },
            { key: 'active', title: 'Status', checked: true },
            { key: 'contact', title: 'Contact', checked: false },
            { key: 'action', title: '', checked: true, required: true, fixed: 'right' },
        ];

        wrapper.vm.onColumnSettingsApply(settings);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.columnSettings).toEqual(settings);
        expect(wrapper.findComponent({ ref: 'userListing' }).props('columnSettings')).toEqual([
            { ...settings[0], title: 'ct-users.user-grid.labelName' },
            { ...settings[1], title: 'ct-users.user-grid.status' },
            { ...settings[2], title: 'ct-users.user-grid.labelContact' },
            settings[3],
        ]);
    });
});
