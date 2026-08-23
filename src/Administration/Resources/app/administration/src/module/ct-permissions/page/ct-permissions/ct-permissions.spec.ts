import { mount, type VueWrapper } from '@vue/test-utils';

interface PermissionsPageVm {
    reloadRoleListing(): void;
    onRoleSearch(term: string): void;
    openCreateRole(): void;
    $refs: {
        roleListing: {
            getList(): void;
            onSearch(term: string): void;
            openCreateRole(): void;
        };
    };
}

describe('modules/ct-permissions/page/ct-permissions', () => {
    let wrapper: VueWrapper<PermissionsPageVm>;

    async function createWrapper(privileges: string[] = []) {
        wrapper = mount(
            await wrapTestComponent('ct-permissions', {
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
                        'ct-permissions-role-listing': {
                            template: '<div class="role-listing"></div>',
                            methods: {
                                getList: jest.fn(),
                                onSearch: jest.fn(),
                                openCreateRole: jest.fn(),
                            },
                        },
                        'ct-search-bar': {
                            emits: ['search'],
                            template: '<input class="role-search" @input="$emit(\'search\', $event.target.value)" />',
                        },
                    },
                },
            },
        ) as unknown as VueWrapper<PermissionsPageVm>;

        await flushPromises();
    }

    beforeEach(async () => {
        await createWrapper();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('renders only role management content', () => {
        expect(wrapper.find('.role-listing').exists()).toBe(true);
        expect(wrapper.find('.permissions-configuration').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('ct-users.general.cardLabel');
    });

    it('reloads the role listing', () => {
        const getList = jest.spyOn(wrapper.vm.$refs.roleListing, 'getList');

        wrapper.vm.reloadRoleListing();

        expect(getList).toHaveBeenCalled();
    });

    it('places search in the page search bar and forwards it to the listing', async () => {
        const onSearch = jest.spyOn(wrapper.vm.$refs.roleListing, 'onSearch');

        await wrapper.find('.role-search').setValue('manager');

        expect(onSearch).toHaveBeenCalledWith('manager');
    });

    it('opens role creation from the smart-bar action', async () => {
        await createWrapper(['users_and_permissions.creator']);
        const openCreateRole = jest.spyOn(wrapper.vm.$refs.roleListing, 'openCreateRole');

        await wrapper.find('.ct-permissions__create-role').trigger('click');

        expect(openCreateRole).toHaveBeenCalled();
    });
});
