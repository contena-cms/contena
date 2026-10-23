/**
 * @sw-package framework
 */
const { Service } = Contena;

enum USER_CONFIG_PERMISSIONS {
    READ = 'admin_user_config:read',
    CREATE = 'admin_user_config:create',
    UPDATE = 'admin_user_config:update',
}

abstract class AdminUserConfigClass {
    private currentUserId = this.getCurrentUserId();

    protected admin_userConfig = this.createUserConfigEntity(this.getConfigurationKey());

    private aclService = Service('acl');

    constructor() {
        void this.readUserConfig();
    }

    /**
     * Copy admin_user configuration into the service state.
     */
    protected abstract readUserConfig(): Promise<void>;

    /**
     * Copy the service state into the admin_user configuration.
     */
    protected abstract setUserConfig(): void;

    /**
     * Returns the configuration key that is managed.
     */
    protected abstract getConfigurationKey(): string;

    public refresh(): void {
        this.admin_userConfig = this.createUserConfigEntity(this.getConfigurationKey());
        void this.readUserConfig();
    }

    protected async getUserConfig(): Promise<Entity<'admin_user_config'>> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.READ)) {
            return this.admin_userConfig;
        }

        const admin_userConfig = Object.assign(this.createUserConfigEntity(this.getConfigurationKey()), this.admin_userConfig, {
            value: (await Contena.Service('admin_userConfigService').search([this.getConfigurationKey()]))?.data?.[
                this.getConfigurationKey()
            ],
        });

        return this.handleEmptyUserConfig(admin_userConfig);
    }

    protected async saveUserConfig(): Promise<void> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.CREATE) || !this.aclService.can(USER_CONFIG_PERMISSIONS.UPDATE)) {
            return;
        }

        this.setUserConfig();

        const configurationKey = this.getConfigurationKey();
        const upsertData: Record<string, unknown> = {};
        upsertData[configurationKey] = this.admin_userConfig.value;

        await Contena.Service('admin_userConfigService').upsert(upsertData);
        await this.readUserConfig();
    }

    private createUserConfigEntity(configKey: string): Entity<'admin_user_config'> {
        return {
            admin_userId: this.currentUserId,
            key: configKey,
            value: [],
        } as Entity<'admin_user_config'>;
    }

    private handleEmptyUserConfig(admin_userConfig: Entity<'admin_user_config'>): Entity<'admin_user_config'> {
        if (!Array.isArray(admin_userConfig?.value)) {
            admin_userConfig.value = [];
        }

        return admin_userConfig;
    }

    private getCurrentUserId(): EntityKey<'admin_user'> {
        return Contena.Store.get('session').currentUser?.id ?? ('' as EntityKey<'admin_user'>);
    }
}

/**
 * @private
 */
export { AdminUserConfigClass as default };
