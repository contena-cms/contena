/* eslint-disable @typescript-eslint/prefer-promise-reject-errors */
/**
 * @sw-package framework
 */

/* @private */
import { defineComponent } from 'vue';

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

interface AdminUserSettingsRepositoryFactory {
    create(entityName: 'admin_user_config'): AdminUserConfigRepository;
}

interface CurrentUser {
    id?: EntityKey<'admin_user'> | null;
}

/**
 * @private
 *
 * Duplicated in `src/app/composables/use-admin_user-settings`; change both together.
 */
export default Contena.Mixin.register(
    'admin_user-settings',
    defineComponent({
        inject: ['acl'],

        computed: {
            admin_userConfigRepository(): AdminUserConfigRepository {
                const repositoryFactory = (this as unknown as { repositoryFactory: AdminUserSettingsRepositoryFactory })
                    .repositoryFactory;

                return repositoryFactory.create('admin_user_config');
            },

            currentUser(): CurrentUser | null {
                return Contena.Store.get('session').currentUser as CurrentUser | null;
            },
        },

        methods: {
            /**
             * Receives the whole settings entity via identifier key
             *
             * @param {string} identifier Used to identify its target use
             * @param {string|null} admin_userId Id of the target admin_user; `null` will use the current admin_user
             * @return {Promise<*>}
             */
            getUserSettingsEntity(
                identifier: string,
                admin_userId: EntityKey<'admin_user'> | null = null,
            ): Promise<AdminUserSettingsEntity | null> {
                if (!this.acl.can('admin_user_config:read')) {
                    return Promise.reject();
                }

                return this.admin_userConfigRepository
                    .search(this.admin_userGridSettingsCriteria(identifier, admin_userId), Contena.Context.api)
                    .then((response) => {
                        if (!response.length) {
                            return null;
                        }

                        return response[0];
                    });
            },

            /**
             * Receives settings values via identifier key
             *
             * @param {string} identifier Used to identify its target use
             * @param {string|null} admin_userId Id of the target admin_user; `null` will use the current admin_user
             * @return {Promise<*>}
             */
            async getUserSettings(identifier: string, admin_userId = null): Promise<unknown> {
                if (!this.acl.can('admin_user_config:read')) {
                    return Promise.reject();
                }

                if (!admin_userId || admin_userId === this.currentUser?.id) {
                    const response = await Contena.Service('admin_userConfigService').search([identifier]);

                    return response?.data?.[identifier] ?? null;
                }

                const entity = await this.getUserSettingsEntity(identifier, admin_userId);

                if (!entity) {
                    return null;
                }

                return entity.value;
            },

            /**
             * Saves settings to the currently logged in admin_user
             *
             * @param {string} identifier Unique key to identify its target use
             * @param {{[key: string]: any}} entityValue Values to save
             * @param {string|null} admin_userId Id of the target admin_user; `null` will use the current admin_user
             * @return {Promise<*>}
             */
            async saveUserSettings(
                identifier: string,
                entityValue: {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    [key: string]: any;
                },
                admin_userId: EntityKey<'admin_user'> | null = null,
            ): Promise<unknown> {
                if (!this.acl.can('admin_user_config:create') || !this.acl.can('admin_user_config:update')) {
                    return Promise.reject();
                }

                if (!identifier) {
                    return Promise.reject();
                }

                if (!identifier.includes('.')) {
                    identifier = `custom.${identifier}`;
                }

                if (!admin_userId) {
                    admin_userId = this.currentUser?.id ?? null;
                }

                if (!admin_userId || admin_userId === this.currentUser?.id) {
                    return Contena.Service('admin_userConfigService').upsert({
                        [identifier]: entityValue,
                    });
                }

                let admin_userSettings: AdminUserSettingsEntity | null = await this.getUserSettingsEntity(identifier, admin_userId);
                if (!admin_userSettings) {
                    admin_userSettings = this.admin_userConfigRepository.create(Contena.Context.api);
                }

                const entityData = Object.assign(admin_userSettings, {
                    admin_userId,
                    key: identifier,
                    value: entityValue,
                });

                return this.admin_userConfigRepository.save(entityData, Contena.Context.api);
            },

            /**
             * Provides the admin_userSettings criteria used for the queries
             *
             * @internal
             * @param {string} identifier Used to identify its target use
             * @param {string|null} admin_userId Id of the target admin_user; `null` will use the current admin_user
             * @return {Criteria}
             */
            admin_userGridSettingsCriteria(identifier: string, admin_userId: EntityKey<'admin_user'> | null = null) {
                if (!admin_userId) {
                    admin_userId = this.currentUser?.id ?? ('' as EntityKey<'admin_user'>);
                }

                const criteria = new Contena.Data.Criteria(1, 25);
                criteria.addFilter(Contena.Data.Criteria.equals('key', identifier));
                criteria.addFilter(Contena.Data.Criteria.equals('admin_userId', admin_userId));

                return criteria;
            },
        },
    }),
);
