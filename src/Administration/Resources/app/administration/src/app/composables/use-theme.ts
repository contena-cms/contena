/**
 * @ct-package framework
 */
import { effectScope } from 'vue';
import { useTheme as useMeteorTheme } from '@contena/meteor-component-library';
import type { Theme, UseThemeReturn } from '@contena/meteor-component-library';

/**
 * @private
 */
export const USER_THEME_CONFIG_KEY = 'core.userTheme';

type UseAdminThemeReturn = UseThemeReturn & {
    loadUserTheme: () => Promise<void>;
    saveUserTheme: (theme: Theme) => Promise<void>;
};

let themeState: UseAdminThemeReturn | null = null;

function isTheme(value: unknown): value is Theme {
    return value === 'light' || value === 'dark' || value === 'system';
}

async function loadUserTheme(): Promise<void> {
    const response = await Contena.Service('userConfigService').search([USER_THEME_CONFIG_KEY]);
    const value = response?.data?.[USER_THEME_CONFIG_KEY] as { theme?: unknown } | undefined;

    if (value && isTheme(value.theme)) {
        useTheme().setTheme(value.theme);
    }
}

async function saveUserTheme(theme: Theme): Promise<void> {
    useTheme().setTheme(theme);

    await Contena.Service('userConfigService').upsert({
        [USER_THEME_CONFIG_KEY]: { theme },
    });
}

/**
 * @private
 */
export default function useTheme(): UseAdminThemeReturn {
    if (!themeState) {
        const scope = effectScope(true);

        themeState = {
            ...scope.run(() => useMeteorTheme())!,
            loadUserTheme,
            saveUserTheme,
        };
    }

    return themeState;
}
