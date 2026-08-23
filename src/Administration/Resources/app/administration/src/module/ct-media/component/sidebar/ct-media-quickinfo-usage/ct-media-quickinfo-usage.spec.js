import { shallowMount } from '@vue/test-utils';

const createMedia = (avatarUsers = []) => ({
    getEntityName: () => 'media',
    avatarUsers,
});

async function createWrapper(item = createMedia()) {
    return shallowMount(await wrapTestComponent('ct-media-quickinfo-usage', { sync: true }), {
        props: { item },
    });
}

describe('module/ct-media/components/ct-media-quickinfo-usage', () => {
    beforeEach(() => {
        Contena.Module.getModuleRegistry().clear();
        Contena.Module.register('ct-users', {
            type: 'core',
            name: 'users',
            color: '#189eff',
            icon: 'regular-users',
            routes: {
                index: {
                    component: 'ct-users',
                    path: 'index',
                },
            },
        });
    });

    it('shows user-avatar usage', async () => {
        const wrapper = await createWrapper(createMedia([{ id: 'user-id', username: 'admin' }]));

        expect(wrapper.vm.getUsages).toStrictEqual([
            {
                name: 'admin',
                tooltip: 'ct-media.sidebar.usage.tooltipFoundInUser',
                link: { name: 'ct.users.user.detail', id: 'user-id' },
                icon: { name: 'regular-users', color: '#189eff' },
            },
        ]);
        expect(wrapper.vm.isNotUsed).toBe(false);
    });

    it('reports media without avatar usage as unused', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.getUsages).toStrictEqual([]);
        expect(wrapper.vm.isNotUsed).toBe(true);
    });
});
