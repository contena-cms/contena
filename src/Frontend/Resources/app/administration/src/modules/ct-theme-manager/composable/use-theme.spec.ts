import { mount } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import { createMemoryHistory, createRouter, routeLocationKey, routerKey } from 'vue-router';
import { useTheme } from './use-theme';

function createTheme(overrides: Partial<Entity<'theme'>> = {}): Entity<'theme'> {
    return {
        id: 'theme-id',
        name: 'Theme',
        author: 'Contena',
        active: true,
        createdAt: '2026-01-01T00:00:00+00:00',
        ...overrides,
    } as Entity<'theme'>;
}

interface WrapperOptions {
    aclCan?: boolean;
    getList?: () => unknown;
    repositoryOverrides?: Record<string, unknown>;
}

async function createWrapper({ aclCan = true, getList, repositoryOverrides = {} }: WrapperOptions = {}) {
    const themeRepository = {
        delete: jest.fn(() => Promise.resolve()),
        create: jest.fn(() => createTheme({ id: 'new-theme-id' })),
        save: jest.fn(() => Promise.resolve()),
        ...repositoryOverrides,
    };
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            {
                name: 'ct.theme.manager.index',
                path: '/themes',
                component: { template: '<div />' },
            },
            {
                name: 'ct.theme.manager.detail',
                path: '/themes/:id',
                component: { template: '<div />' },
            },
        ],
    });
    await router.push({ name: 'ct.theme.manager.index' });

    const wrapper = mount(
        defineComponent({
            template: '<div />',
            setup() {
                const isLoading = ref(false);

                return {
                    isLoading,
                    ...useTheme({ isLoading, getList }),
                };
            },
        }),
        {
            global: {
                provide: {
                    [routerKey]: router,
                    [routeLocationKey]: router.currentRoute.value,
                    repositoryFactory: {
                        create: () => themeRepository,
                    },
                    acl: {
                        can: jest.fn(() => aclCan),
                    },
                },
            },
        },
    );

    return { wrapper, router, themeRepository };
}

describe('ct-theme-manager/composable/use-theme', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('does not open delete modal when ACL blocks', async () => {
        const { wrapper } = await createWrapper({ aclCan: false });

        wrapper.vm.onDeleteTheme(createTheme());

        expect(wrapper.vm.showDeleteModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
    });

    it('opens delete modal when ACL allows', async () => {
        const { wrapper } = await createWrapper();
        const theme = createTheme();

        wrapper.vm.onDeleteTheme(theme);

        expect(wrapper.vm.showDeleteModal).toBe(true);
        expect(wrapper.vm.modalTheme).toEqual(theme);
    });

    it('closes delete modal and clears theme', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.showDeleteModal = true;
        wrapper.vm.modalTheme = createTheme();

        wrapper.vm.onCloseDeleteModal();

        expect(wrapper.vm.showDeleteModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
    });

    it('confirms delete and resets modal state', async () => {
        const { wrapper, themeRepository } = await createWrapper();
        const theme = createTheme();

        wrapper.vm.modalTheme = theme;
        wrapper.vm.showDeleteModal = true;
        wrapper.vm.onConfirmThemeDelete();

        expect(themeRepository.delete).toHaveBeenCalledWith(theme.id, Contena.Context.api);
        expect(wrapper.vm.showDeleteModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
    });

    it('deletes theme and refreshes list when getList exists', async () => {
        const getList = jest.fn();
        const { wrapper, router } = await createWrapper({ getList });
        const routerPush = jest.spyOn(router, 'push');

        wrapper.vm.deleteTheme(createTheme());
        await flushPromises();

        expect(getList).toHaveBeenCalled();
        expect(routerPush).not.toHaveBeenCalled();
    });

    it('deletes theme and redirects when getList is missing', async () => {
        const { wrapper, router } = await createWrapper();
        const routerPush = jest.spyOn(router, 'push');

        wrapper.vm.deleteTheme(createTheme());
        await flushPromises();

        expect(routerPush).toHaveBeenCalledWith({ name: 'ct.theme.manager.index' });
    });

    it('shows error notification when delete fails', async () => {
        const createNotification = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockReturnValue('notification-id');
        const { wrapper } = await createWrapper({
            repositoryOverrides: {
                delete: jest.fn(() => Promise.reject(new Error('fail'))),
            },
        });

        wrapper.vm.deleteTheme(createTheme());
        await flushPromises();

        expect(createNotification).toHaveBeenCalledWith({
            variant: 'error',
            title: 'ct-theme-manager.components.themeListItem.notificationDeleteErrorTitle',
            message: 'ct-theme-manager.components.themeListItem.notificationDeleteErrorMessage',
        });
    });

    it('opens duplicate modal when ACL allows', async () => {
        const { wrapper } = await createWrapper();
        const theme = createTheme();

        wrapper.vm.onDuplicateTheme(theme);

        expect(wrapper.vm.showDuplicateModal).toBe(true);
        expect(wrapper.vm.modalTheme).toEqual(theme);
    });

    it('does not open duplicate modal when ACL blocks', async () => {
        const { wrapper } = await createWrapper({ aclCan: false });

        wrapper.vm.onDuplicateTheme(createTheme());

        expect(wrapper.vm.showDuplicateModal).toBe(false);
    });

    it('closes duplicate modal and resets state', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.showDuplicateModal = true;
        wrapper.vm.modalTheme = createTheme();
        wrapper.vm.newThemeName = 'New name';

        wrapper.vm.onCloseDuplicateModal();

        expect(wrapper.vm.showDuplicateModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
        expect(wrapper.vm.newThemeName).toBe('');
    });

    it('confirms duplicate and resets modal state', async () => {
        const { wrapper, themeRepository } = await createWrapper();
        const theme = createTheme();

        wrapper.vm.modalTheme = theme;
        wrapper.vm.newThemeName = 'New name';
        wrapper.vm.onConfirmThemeDuplicate();

        expect(themeRepository.create).toHaveBeenCalledWith(Contena.Context.api);
        expect(wrapper.vm.showDuplicateModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
        expect(wrapper.vm.newThemeName).toBe('');
    });

    it('duplicates theme and redirects to detail', async () => {
        const { wrapper, router, themeRepository } = await createWrapper();
        const routerPush = jest.spyOn(router, 'push');
        const parentTheme = createTheme({
            id: 'parent-id',
            author: 'author',
            description: 'description',
            customFields: { custom: true },
            previewMediaId: 'media-id',
        });

        wrapper.vm.duplicateTheme(parentTheme, 'New theme');
        await flushPromises();

        expect(themeRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'New theme',
                parentThemeId: 'parent-id',
                author: 'author',
                description: 'description',
                previewMediaId: 'media-id',
                active: true,
            }),
            Contena.Context.api,
        );
        expect(routerPush).toHaveBeenCalledWith({
            name: 'ct.theme.manager.detail',
            params: { id: 'new-theme-id' },
        });
    });

    it('opens rename modal with current theme name', async () => {
        const { wrapper } = await createWrapper();
        const theme = createTheme({ name: 'Old name' });

        wrapper.vm.onRenameTheme(theme);

        expect(wrapper.vm.showRenameModal).toBe(true);
        expect(wrapper.vm.modalTheme).toEqual(theme);
        expect(wrapper.vm.newThemeName).toBe('Old name');
    });

    it('does not open rename modal when ACL blocks', async () => {
        const { wrapper } = await createWrapper({ aclCan: false });

        wrapper.vm.onRenameTheme(createTheme());

        expect(wrapper.vm.showRenameModal).toBe(false);
    });

    it('closes rename modal and resets state', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.showRenameModal = true;
        wrapper.vm.modalTheme = createTheme();
        wrapper.vm.newThemeName = 'New name';

        wrapper.vm.onCloseRenameModal();

        expect(wrapper.vm.showRenameModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
        expect(wrapper.vm.newThemeName).toBe('');
    });

    it('confirms rename and resets modal state', async () => {
        const { wrapper, themeRepository } = await createWrapper();
        const theme = createTheme({ name: 'Old name' });

        wrapper.vm.modalTheme = theme;
        wrapper.vm.newThemeName = 'New name';
        wrapper.vm.onConfirmThemeRename();

        expect(themeRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({ name: 'New name' }),
            Contena.Context.api,
        );
        expect(wrapper.vm.showRenameModal).toBe(false);
        expect(wrapper.vm.modalTheme).toBeNull();
        expect(wrapper.vm.newThemeName).toBe('');
    });

    it('renames theme and saves', async () => {
        const { wrapper, themeRepository } = await createWrapper();
        const theme = createTheme({ name: 'Old name' });

        wrapper.vm.RenameTheme(theme, 'New name');

        expect(theme.name).toBe('New name');
        expect(themeRepository.save).toHaveBeenCalledWith(theme, Contena.Context.api);
    });

    it('saves theme even if name is empty', async () => {
        const { wrapper, themeRepository } = await createWrapper();
        const theme = createTheme({ name: 'Old name' });

        wrapper.vm.RenameTheme(theme, '');

        expect(theme.name).toBe('Old name');
        expect(themeRepository.save).toHaveBeenCalledWith(theme, Contena.Context.api);
    });
});
