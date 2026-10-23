/* eslint-disable @typescript-eslint/prefer-promise-reject-errors */
/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import Criteria from 'src/core/data/criteria.data';

interface AdminUserSettingsEntity {
    id?: string;
    key?: string;
    admin_userId?: string | null;
    value?: unknown;
    [key: string]: unknown;
}

interface AdminUserConfigRepository {
    search(criteria: unknown, context: unknown): Promise<AdminUserSettingsEntity[]>;
    create(context: unknown): AdminUserSettingsEntity;
    save(entity: AdminUserSettingsEntity, context: unknown): Promise<unknown>;
}

interface CurrentUser {
    id?: EntityKey<'admin_user'> | null;
}

/**
 * Composable alternative to the `admin_user-settings` mixin: reads and writes per-admin_user config.
 *
 * The mixin injected `acl`, read the component's injected `repositoryFactory` and exposed
 * `currentUser` / `admin_userConfigRepository` as computeds. This composable resolves all of them itself,
 * through `Contena.Service` and the session store, so it needs nothing from the component. The mixin
 * stays in place for Options API components.
 *
 * Keep this and `src/app/mixin/admin_user-settings.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useUserSettings(): {
    getUserSettingsEntity: (identifier: string, admin_userId?: string | null) => Promise<AdminUserSettingsEntity | null>;
    getUserSettings: (identifier: string, admin_userId?: string | null) => Promise<unknown>;
    saveUserSettings: (
        identifier: string,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        entityValue: { [key: string]: any },
        admin_userId?: string | null,
    ) => Promise<unknown>;
    admin_userGridSettingsCriteria: (identifier: string, admin_userId?: string | null) => Criteria;
} {
    function acl(): { can: (privilege: string) => boolean } {
        return Contena.Service('acl');
    }

    function admin_userConfigRepository(): AdminUserConfigRepository {
        return Contena.Service('repositoryFactory').create('admin_user_config') as unknown as AdminUserConfigRepository;
    }

    function currentUser(): CurrentUser | null {
        return Contena.Store.get('session').currentUser as CurrentUser | null;
    }

    /**
     * Receives the whole settings entity via identifier key.
     */
    function getUserSettingsEntity(identifier: string, admin_userId: string | null = null): Promise<AdminUserSettingsEntity | null> {
        if (!acl().can('admin_user_config:read')) {
            return Promise.reject();
        }

        return admin_userConfigRepository()
            .search(admin_userGridSettingsCriteria(identifier, admin_userId), Contena.Context.api)
            .then((response) => {
                if (!response.length) {
                    return null;
                }

                return response[0];
            });
    }

    /**
     * Receives settings values via identifier key.
     */
    async function getUserSettings(identifier: string, admin_userId: string | null = null): Promise<unknown> {
        if (!acl().can('admin_user_config:read')) {
            return Promise.reject();
        }

        if (!admin_userId || admin_userId === currentUser()?.id) {
            const response = await Contena.Service('admin_userConfigService').search([identifier]);

            return response?.data?.[identifier] ?? null;
        }

        const entity = await getUserSettingsEntity(identifier, admin_userId);

        if (!entity) {
            return null;
        }

        return entity.value;
    }

    /**
     * Saves settings to the target admin_user, defaulting to the currently logged in one.
     */
    async function saveUserSettings(
        identifier: string,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        entityValue: { [key: string]: any },
        admin_userId: string | null = null,
    ): Promise<unknown> {
        if (!acl().can('admin_user_config:create') || !acl().can('admin_user_config:update')) {
            return Promise.reject();
        }

        if (!identifier) {
            return Promise.reject();
        }

        if (!identifier.includes('.')) {
            identifier = `custom.${identifier}`;
        }

        if (!admin_userId) {
            admin_userId = currentUser()?.id ?? null;
        }

        if (!admin_userId || admin_userId === currentUser()?.id) {
            return Contena.Service('admin_userConfigService').upsert({
                [identifier]: entityValue,
            });
        }

        let admin_userSettings: AdminUserSettingsEntity | null = await getUserSettingsEntity(identifier, admin_userId);

        if (!admin_userSettings) {
            admin_userSettings = admin_userConfigRepository().create(Contena.Context.api);
        }

        const entityData = Object.assign(admin_userSettings, {
            admin_userId,
            key: identifier,
            value: entityValue,
        });

        return admin_userConfigRepository().save(entityData, Contena.Context.api);
    }

    /**
     * Provides the admin_userSettings criteria used for the queries.
     */
    function admin_userGridSettingsCriteria(identifier: string, admin_userId: string | null = null): Criteria {
        if (!admin_userId) {
            admin_userId = currentUser()?.id ?? '';
        }

        const criteria = new Criteria(1, 25);

        criteria.addFilter(Criteria.equals('key', identifier));
        criteria.addFilter(Criteria.equals('admin_userId', admin_userId));

        return criteria;
    }

    return {
        getUserSettingsEntity,
        getUserSettings,
        saveUserSettings,
        admin_userGridSettingsCriteria,
    };
}
