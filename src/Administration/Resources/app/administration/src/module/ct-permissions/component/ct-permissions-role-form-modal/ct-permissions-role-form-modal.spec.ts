import { mount, type VueWrapper } from '@vue/test-utils';
import RoleFormModal from './index';

interface Role {
    id?: string;
    code: string;
    name: string;
    description: string;
    privileges: string[];
}
interface RoleFormVm {
    role: Role;
    canSave: boolean;
    requestSave(): Promise<void>;
    saveRole(context: unknown): Promise<void>;
}

async function createWrapper({ roleId = null, privileges = [] }: { roleId?: string | null; privileges?: string[] } = {}) {
    const role: Role = {
        id: roleId ?? undefined,
        code: roleId ? 'existing_role' : '',
        name: roleId ? 'Existing role' : '',
        description: '',
        privileges: [],
    };
    const repository = {
        create: jest.fn(() => role),
        get: jest.fn().mockResolvedValue(role),
        save: jest.fn().mockResolvedValue(undefined),
    };
    const wrapper = mount(RoleFormModal, {
        props: { roleId },
        global: {
            provide: {
                repositoryFactory: { create: () => repository },
                acl: { can: (privilege: string) => privileges.includes(privilege) },
            },
            stubs: {
                'mt-modal-root': { template: '<div><slot /></div>' },
                'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                'mt-text-field': true,
                'mt-textarea': true,
            },
        },
    }) as unknown as VueWrapper<RoleFormVm>;

    await flushPromises();

    return { wrapper, repository, role };
}

describe('module/ct-permissions/component/ct-permissions-role-form-modal', () => {
    it('creates a new role without navigating away from the list', async () => {
        const { wrapper, repository } = await createWrapper({ privileges: ['users_and_permissions.creator'] });

        expect(repository.create).toHaveBeenCalled();
        expect(repository.get).not.toHaveBeenCalled();

        wrapper.vm.role.name = 'Managers';
        wrapper.vm.role.code = 'managers';
        await wrapper.vm.requestSave();

        expect(repository.save).toHaveBeenCalledWith(wrapper.vm.role, Contena.Context.api);
        expect(wrapper.emitted('saved')).toHaveLength(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('loads an existing role and requires editor permission', async () => {
        const { wrapper, repository } = await createWrapper({ roleId: 'role-id' });

        expect(repository.get).toHaveBeenCalledWith('role-id');
        expect(wrapper.vm.canSave).toBe(false);

        await wrapper.vm.requestSave();

        expect(repository.save).not.toHaveBeenCalled();
    });

    it('requires a technical code and spaces footer actions consistently', async () => {
        const { wrapper } = await createWrapper({ privileges: ['users_and_permissions.creator'] });

        wrapper.vm.role.name = 'Managers';
        expect(wrapper.vm.canSave).toBe(false);

        wrapper.vm.role.code = 'managers';
        expect(wrapper.vm.canSave).toBe(true);
        expect(wrapper.find('.ct-permissions-role-form-modal__footer-actions').exists()).toBe(true);
    });
});
