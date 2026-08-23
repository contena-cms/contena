// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default class AclService {
    isAdmin(): boolean {
        return !!Contena.Store.get('session').currentUser?.admin;
    }

    can(privilegeKey: string): boolean {
        if (this.isAdmin() || !privilegeKey) {
            return true;
        }

        return (Contena.Store.get('session').userPrivileges as string[]).includes(privilegeKey);
    }

    hasAccessToRoute(path: string): boolean {
        const route = path.replace(/\./g, '/');
        if (route === '/sw/settings/index') {
            return this.hasActiveSettingModules();
        }

        if (!Contena?.Application?.view?.root?.$router) {
            return true;
        }

        const router = Contena.Application.view.root.$router;
        // @ts-expect-error - meta is not defined in the type
        const match = router.resolve(route) as { meta?: { privilege: string } };

        if (!match.meta) {
            return true;
        }

        return this.can(match.meta.privilege);
    }

    hasActiveSettingModules(): boolean {
        // @ts-expect-error
        const groups = Object.values(Contena.Store.get('settingsItems').settingsGroups) as [[{ privilege?: string }]];

        let hasActive = false;

        groups.forEach((modules) => {
            modules.forEach((module) => {
                if (!module.privilege) {
                    hasActive = true;
                } else if (this.can(module.privilege)) {
                    hasActive = true;
                }
            });
        });

        return hasActive;
    }

    get privileges(): string[] {
        return Contena.Store.get('session').userPrivileges as string[];
    }
}
