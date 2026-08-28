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

/**
 * Preference used before the user has chosen a theme. Administration starts in light mode
 * instead of inheriting the operating system preference.
 */
export const DEFAULT_THEME: Theme = 'light';

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

    // The service swallows request errors and resolves without a response. Keep the current preference then.
    if (!response) {
        return;
    }

    const value = response.data?.[USER_THEME_CONFIG_KEY] as { theme?: unknown } | undefined;

    // localStorage is shared by every browser user, so an absent server preference must not inherit another user's choice.
    useTheme().setTheme(value && isTheme(value.theme) ? value.theme : DEFAULT_THEME);
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
            ...scope.run(() => useMeteorTheme({ defaultTheme: DEFAULT_THEME }))!,
            loadUserTheme,
            saveUserTheme,
        };
    }

    return themeState;
}
