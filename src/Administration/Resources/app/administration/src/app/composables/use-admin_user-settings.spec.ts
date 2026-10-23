/**
 * @sw-package framework
 */
import useUserSettings from './use-admin_user-settings';

function stubContena(overrides: {
    can?: boolean;
    currentUserId?: string | null;
    services?: Record<string, unknown>;
    repositorySearch?: jest.Mock;
    repositoryCreate?: jest.Mock;
    repositorySave?: jest.Mock;
}): void {
    const {
        can = true,
        currentUserId = 'me',
        services = {},
        repositorySearch,
        repositoryCreate,
        repositorySave,
    } = overrides;

    const repository = {
        search: repositorySearch ?? jest.fn().mockResolvedValue([]),
        create: repositoryCreate ?? jest.fn().mockReturnValue({}),
        save: repositorySave ?? jest.fn().mockResolvedValue('saved'),
    };

    const serviceMap: Record<string, unknown> = {
        acl: { can: jest.fn().mockReturnValue(can) },
        repositoryFactory: { create: jest.fn().mockReturnValue(repository) },
        ...services,
    };

    global.window.Contena = {
        Service: jest.fn((name: string) => serviceMap[name]),
        Store: { get: jest.fn().mockReturnValue({ currentUser: { id: currentUserId } }) },
        Context: { api: {} },
    } as unknown as typeof Contena;
}

describe('src/app/composables/use-admin_user-settings', () => {
    it('rejects reads when the acl privilege is missing', async () => {
        stubContena({ can: false });
        const { getUserSettings } = useUserSettings();

        await expect(getUserSettings('foo')).rejects.toBeUndefined();
    });

    it('getUserSettings uses admin_userConfigService for the current admin_user', async () => {
        const search = jest.fn().mockResolvedValue({ data: { 'my.key': 42 } });

        stubContena({ currentUserId: 'me', services: { admin_userConfigService: { search } } });
        const { getUserSettings } = useUserSettings();

        await expect(getUserSettings('my.key')).resolves.toBe(42);
        expect(search).toHaveBeenCalledWith(['my.key']);
    });

    it('getUserSettings reads the entity value for a different admin_user', async () => {
        const repositorySearch = jest.fn().mockResolvedValue([{ value: 'other-value' }]);

        stubContena({ currentUserId: 'me', repositorySearch });
        const { getUserSettings } = useUserSettings();

        await expect(getUserSettings('my.key', 'someone-else')).resolves.toBe('other-value');
    });

    it('saveUserSettings upserts via admin_userConfigService for the current admin_user and namespaces bare keys', async () => {
        const upsert = jest.fn().mockResolvedValue('ok');

        stubContena({ currentUserId: 'me', services: { admin_userConfigService: { upsert } } });
        const { saveUserSettings } = useUserSettings();

        await saveUserSettings('bareKey', { a: 1 });

        expect(upsert).toHaveBeenCalledWith({ 'custom.bareKey': { a: 1 } });
    });

    it('saveUserSettings rejects without create and update privileges', async () => {
        stubContena({ can: false });
        const { saveUserSettings } = useUserSettings();

        await expect(saveUserSettings('a.b', {})).rejects.toBeUndefined();
    });

    it('admin_userGridSettingsCriteria filters by key and the resolved admin_user id', () => {
        stubContena({ currentUserId: 'me' });
        const { admin_userGridSettingsCriteria } = useUserSettings();

        const criteria = admin_userGridSettingsCriteria('a.b');

        expect(criteria.filters).toEqual([
            { type: 'equals', field: 'key', value: 'a.b' },
            { type: 'equals', field: 'admin_userId', value: 'me' },
        ]);
    });
});
