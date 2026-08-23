import { computed, reactive } from 'vue';
import type { privileges } from '@contena/meteor-admin-sdk/es/_internals/privileges';

type ApiAuthToken = {
    access: string;
    expiry: number;
    refresh: string;
};

/**
 * @private
 */
export interface ContextState {
    app: {
        config: {
            adminWorker: null | {
                enableAdminWorker: boolean;
                enableNotificationWorker: boolean;
                transports: string[];
            };
            bundles: null | {
                [BundleName: string]: {
                    css: string | string[];
                    js: string | string[];
                    permissions?: privileges;
                    integrationId?: string;
                    active?: boolean;
                };
            };
            settings?: {
                disableExtensionManagement: boolean;
                minSearchTermLength: number;
            };
            version: null | string;
            versionRevision: null | string;
        };
        environment: null | 'development' | 'production' | 'testing';
        fallbackLocale: null | string;
        features: null | {
            [FeatureKey: string]: boolean;
        };
        windowId: null | string;
    };
    api: {
        apiPath: null | string;
        apiResourcePath: null | string;
        assetsPath: null | string;
        authToken: null | ApiAuthToken;
        basePath: null | string;
        pathInfo: null | string;
        inheritance: null | boolean;
        installationPath: null | string;
        languageId: null | string;
        language: null | {
            name: string;
            parentId?: string;
        };
        apiVersion: null | string;
        liveVersionId: null | string;
        systemLanguageId: null | string;
        versionId: null | string;
        refreshTokenTtl: null | string;
    };
}

const state: ContextState = reactive({
    app: {
        config: {
            adminWorker: null,
            bundles: null,
            version: null,
            versionRevision: null,
        },
        environment: null,
        fallbackLocale: null,
        features: null,
        windowId: null,
    },
    api: {
        apiPath: null,
        apiResourcePath: null,
        assetsPath: null,
        authToken: null,
        basePath: null,
        pathInfo: null,
        inheritance: null,
        installationPath: null,
        languageId: null,
        language: null,
        apiVersion: null,
        liveVersionId: null,
        systemLanguageId: null,
        versionId: null,
        refreshTokenTtl: null,
    },
});

function addAppValue<K extends keyof ContextState['app']>({ key, value }: { key: K; value: ContextState['app'][K] }) {
    state.app[key] = value;
}

function addApiValue<K extends keyof ContextState['api']>({ key, value }: { key: K; value: ContextState['api'][K] }) {
    state.api[key] = value;
}

function addAppConfigValue<K extends keyof ContextState['app']['config']>({
    key,
    value,
}: {
    key: K;
    value: ContextState['app']['config'][K];
}) {
    state.app.config[key] = value;
}

function setApiLanguageId(newLanguageId: string) {
    state.api.languageId = newLanguageId;
    localStorage.setItem('ct-admin-current-language', newLanguageId);
}

function resetLanguageToDefault() {
    state.api.languageId = state.api.systemLanguageId;
}

const isSystemDefaultLanguage = computed(() => state.api.languageId === state.api.systemLanguageId);

/**
 * @private
 */
export default function useContext() {
    return {
        ...state,
        addAppValue,
        addApiValue,
        addAppConfigValue,
        setApiLanguageId,
        resetLanguageToDefault,
        isSystemDefaultLanguage,
    };
}
