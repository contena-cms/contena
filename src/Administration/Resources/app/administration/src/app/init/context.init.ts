/**
 * @sw-package framework
 */
import { watch } from 'vue';
/* Is covered by E2E tests */
import { publish } from '@contena-ag/meteor-admin-sdk/es/channel';
import '../store/context.store';
import useSession from '../composables/use-session';
import useTheme from '../composables/use-theme';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeContext(): void {
    // Handle incoming context requests from the ExtensionAPI
    Contena.ExtensionAPI.handle('contextCurrency', () => {
        return {
            systemCurrencyId: Contena.Context.app.systemCurrencyId ?? '',
            systemCurrencyISOCode: Contena.Context.app.systemCurrencyISOCode ?? '',
        };
    });

    Contena.ExtensionAPI.handle('contextEnvironment', () => {
        return Contena.Context.app.environment ?? 'production';
    });

    Contena.ExtensionAPI.handle('contextLanguage', () => {
        return {
            languageId: Contena.Context.api.languageId ?? '',
            systemLanguageId: Contena.Context.api.systemLanguageId ?? '',
        };
    });

    Contena.ExtensionAPI.handle('contextLocale', () => {
        return {
            fallbackLocale: Contena.Context.app.fallbackLocale ?? '',
            locale: Contena.Store.get('session').currentLocale ?? '',
        };
    });

    Contena.ExtensionAPI.handle('contextContenaVersion', () => {
        return Contena.Context.app.config.version ?? '';
    });

    Contena.ExtensionAPI.handle('contextTheme', () => {
        return useTheme().resolvedTheme.value;
    });

    Contena.ExtensionAPI.handle('contextUserTimezone', () => {
        return Contena.Store.get('session').currentUser?.timeZone ?? 'UTC';
    });

    Contena.ExtensionAPI.handle('contextModuleInformation', (_, additionalInformation) => {
        const extension = Object.values(Contena.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extension) {
            return {
                modules: [],
            };
        }

        const modules = Contena.Store.get('extensionSdkModules').getRegisteredModuleInformation(
            extension.baseUrl,
        ) as Array<{
            displaySearchBar: boolean;
            heading: string;
            id: string;
            locationId: string;
        }>;

        return {
            modules,
        };
    });

    Contena.ExtensionAPI.handle('contextIsService', (_, { _event_ }) => {
        const extension = Object.values(Contena.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        return extension?.sourceType === 'service';
    });

    Contena.ExtensionAPI.handle('contextUserInformation', (_, { _event_ }) => {
        const appOrigin = _event_.origin;
        const extension = Object.entries(Contena.Store.get('extensions').extensionsState).find((ext) => {
            return ext[1].baseUrl.startsWith(appOrigin);
        });

        if (!extension) {
            return Promise.reject(new Error(`Could not find a extension with the given event origin "${_event_.origin}"`));
        }

        if (!(extension[1]?.permissions?.read as string[])?.includes('admin_user')) {
            return Promise.reject(new Error(`Extension "${extension[0]}" does not have the permission to read users`));
        }

        const currentUser = Contena.Store.get('session').currentUser;

        return Promise.resolve({
            aclRoles: currentUser?.aclRoles as unknown as Array<{
                name: string;
                type: string;
                id: EntityKey<'acl_role'>;
                privileges: Array<string>;
            }>,
            active: !!currentUser?.active,
            admin: !!currentUser?.admin,
            avatarId: currentUser?.avatarId ?? '',
            email: currentUser?.email ?? '',
            firstName: currentUser?.firstName ?? '',
            id: currentUser?.id ?? '',
            lastName: currentUser?.lastName ?? '',
            localeId: currentUser?.localeId ?? '',
            title: currentUser?.title ?? '',
            // @ts-expect-error - type is not defined in entity directly
            type: (currentUser?.type as unknown as string) ?? '',
            username: currentUser?.username ?? '',
        });
    });

    Contena.ExtensionAPI.handle('contextAppInformation', (_, { _event_ }) => {
        const appOrigin = _event_.origin;
        const extensionEntry = Object.entries(Contena.Store.get('extensions').extensionsState).find((ext) => {
            return ext[1].baseUrl.startsWith(appOrigin);
        });

        if (extensionEntry === undefined) {
            return {
                name: 'unknown',
                type: 'app' as const,
                version: '0.0.0',
                inAppPurchases: [],
                privileges: {},
            };
        }

        const [extensionName, extension] = extensionEntry;

        return {
            name: extensionName,
            type: extension.type,
            version: extension.version ?? '',
            inAppPurchases: Contena.InAppPurchase.getByExtension(extension.name),
            privileges: extension.permissions,
        };
    });

    const contextStore = Contena.Store.get('context');

    watch(
        () => {
            return {
                languageId: contextStore.api.languageId,
                systemLanguageId: contextStore.api.systemLanguageId,
            };
        },
        ({ languageId, systemLanguageId }, { languageId: oldLanguageId, systemLanguageId: oldSystemLanguageId }) => {
            if (languageId === oldLanguageId && systemLanguageId === oldSystemLanguageId) {
                return;
            }

            void publish('contextLanguage', {
                languageId: languageId ?? '',
                systemLanguageId: systemLanguageId ?? '',
            });
        },
    );

    watch(
        () => {
            return {
                fallbackLocale: contextStore.app.fallbackLocale,
            };
        },
        ({ fallbackLocale }, { fallbackLocale: oldFallbackLocale }) => {
            if (fallbackLocale === oldFallbackLocale) {
                return;
            }

            void publish('contextLocale', {
                locale: Contena.Store.get('session').currentLocale ?? '',
                fallbackLocale: fallbackLocale ?? '',
            });
        },
    );

    Contena.Vue.watch(useSession().currentLocale, (locale) => {
        void publish('contextLocale', {
            locale: locale ?? '',
            fallbackLocale: contextStore.app.fallbackLocale ?? '',
        });
    });

    watch(useTheme().resolvedTheme, (resolvedTheme) => {
        void publish('contextTheme', resolvedTheme);
    });

    Contena.ExtensionAPI.handle('windowGetId', () => {
        if (!contextStore.app.windowId) {
            contextStore.app.windowId = Contena.Utils.createId();
        }

        return contextStore.app.windowId;
    });

    Contena.ExtensionAPI.handle('contextShopId', () => {
        return contextStore.app.config.shopId;
    });
}
