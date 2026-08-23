import { mount, type VueWrapper } from '@vue/test-utils';
import RolePermissionsModal from './index';

interface RolePermissionsVm {
    role: { privileges: string[] };
    detailedPrivileges: string[];
    tabItems: Array<{ name: string }>;
    activeTab: 'permissions' | 'advanced';
    requestSave(): Promise<void>;
    savePermissions(context: unknown): Promise<void>;
}

async function createWrapper({ canEdit = true } = {}) {
    const role = {
        id: 'role-id',
        name: 'Managers',
        privileges: [
            'entity.read',
            'route.special',
            'user_config:read',
        ],
    };
    const savedPrivileges: string[][] = [];
    const repository = {
        get: jest.fn().mockResolvedValue(role),
        save: jest.fn((savedRole: { privileges: string[] }) => {
            savedPrivileges.push([...savedRole.privileges]);

            return Promise.resolve();
        }),
    };
    const privileges = {
        filterPrivilegesRoles: jest.fn(() => ['content.viewer']),
        getPrivilegesForAdminPrivilegeKeys: jest.fn((keys: string[]) =>
            keys.includes('content.viewer') ? ['entity.read'] : [],
        ),
        getDefaultUserPrivileges: jest.fn(() => ['user_config:read']),
    };
    const userService = {
        getUser: jest.fn().mockResolvedValue({ data: { id: 'current-user', password: 'secret' } }),
    };
    const wrapper = mount(RolePermissionsModal, {
        props: { roleId: 'role-id' },
        global: {
            provide: {
                repositoryFactory: { create: () => repository },
                privileges,
                userService,
                acl: { can: (privilege: string) => privilege === 'users_and_permissions.editor' && canEdit },
            },
            stubs: {
                'mt-modal-root': { template: '<div><slot /></div>' },
                'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                'mt-tabs': true,
                'mt-banner': { template: '<div class="advanced-banner"><slot /></div>' },
                'ct-permissions-role-access': { template: '<div class="role-access" />' },
                'ct-permissions-additional-permissions': { template: '<div class="additional-permissions" />' },
                'ct-permissions-detailed-additional-permissions': {
                    template: '<div class="route-permissions" />',
                },
                'ct-permissions-detailed-permissions-grid': { template: '<div class="advanced-permissions" />' },
            },
        },
    }) as unknown as VueWrapper<RolePermissionsVm>;

    await flushPromises();

    return { wrapper, repository, privileges, userService, savedPrivileges };
}

describe('module/ct-permissions/component/ct-permissions-role-permissions-modal', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('provides only Permissions and Advanced tabs', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.tabItems.map((tab: { name: string }) => tab.name)).toEqual([
            'permissions',
            'advanced',
        ]);
        expect(wrapper.find('.ct-permissions-role-permissions-modal__footer-actions').exists()).toBe(true);
    });

    it('places functional and former additional permissions on the Permissions tab', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.role-access').exists()).toBe(true);
        expect(wrapper.find('.additional-permissions').exists()).toBe(true);
        expect(wrapper.find('.route-permissions').exists()).toBe(false);
        expect(wrapper.find('.advanced-permissions').exists()).toBe(false);
    });

    it('places API route and low-level permissions on the Advanced tab', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.activeTab = 'advanced';
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.route-permissions').exists()).toBe(true);
        expect(wrapper.find('.advanced-permissions').exists()).toBe(true);
        expect(wrapper.find('.role-access').exists()).toBe(false);
        expect(wrapper.find('.additional-permissions').exists()).toBe(false);
    });

    it('merges mapped and detailed privileges when saving directly', async () => {
        const { wrapper, repository, privileges, userService, savedPrivileges } = await createWrapper();
        const sessionStore = Contena.Store.get('session');
        const setCurrentUser = jest.spyOn(sessionStore, 'setCurrentUser').mockImplementation(() => undefined);

        expect(wrapper.vm.role.privileges).toEqual(['content.viewer']);
        expect(wrapper.vm.detailedPrivileges).toEqual(['route.special']);

        await wrapper.vm.requestSave();

        expect(privileges.getPrivilegesForAdminPrivilegeKeys.mock.calls).toEqual([
            [['content.viewer']],
            [['content.viewer']],
        ]);
        expect(repository.save).toHaveBeenCalledWith(expect.any(Object), Contena.Context.api);
        expect(savedPrivileges).toEqual([
            [
                'entity.read',
                'route.special',
            ],
        ]);
        expect(userService.getUser).toHaveBeenCalled();
        expect(setCurrentUser).toHaveBeenCalledWith({ id: 'current-user' });
        expect(wrapper.emitted('saved')).toHaveLength(1);
    });

    it('does not allow a viewer to request a save', async () => {
        const { wrapper, repository } = await createWrapper({ canEdit: false });

        await wrapper.vm.requestSave();
        await wrapper.vm.savePermissions({ authToken: 'verified' });

        expect(repository.save).not.toHaveBeenCalled();
    });
});
