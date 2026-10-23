/**
 * @sw-package framework
 */
import { effectScope } from 'vue';
import { useTheme as useMeteorTheme } from '@contena-ag/meteor-component-library';
import type { Theme, UseThemeReturn } from '@contena-ag/meteor-component-library';

/**
 * admin_user_config key the theme preference is persisted under for the current admin_user.
 *
 * @private
 */
export const USER_THEME_CONFIG_KEY = 'core.admin_userTheme';

/**
 * Preference used before the admin_user has chosen a theme. The Administration
 * starts in light mode instead of following the operating system.
 *
 * Mirrored by the inline pre-boot script in
 * `Resources/shared/page-loading-screen/page-loading-screen.js`, keep both in sync.
 *
 * @private
 */
export const DEFAULT_THEME: Theme = 'light';

/**
 * Selectable theme preferences in the order the appearance shortcut cycles through them.
 *
 * @private
 */
export const THEMES: readonly Theme[] = ['light', 'dark', 'system'];

/**
 * Snippet keys of the theme preference names.
 *
 * @private
 */
export const THEME_LABELS: Readonly<Record<Theme, string>> = {
    light: 'global.theme.names.light',
    dark: 'global.theme.names.dark',
    system: 'global.theme.names.system',
};

type UseAdminThemeReturn = UseThemeReturn & {
    /**
     * Loads the persisted theme preference of the current admin_user from the
     * server and applies it. Keeps the current (localStorage) preference
     * when the admin_user has not persisted one yet.
     */
    loadUserTheme: () => Promise<void>;
    /**
     * Applies the given theme preference and persists it server-side in the
     * admin_user configuration of the current admin_user.
     */
    saveUserTheme: (theme: Theme) => Promise<void>;
};

let themeState: UseAdminThemeReturn | null = null;

function isTheme(value: unknown): value is Theme {
    return THEMES.includes(value as Theme);
}

async function loadUserTheme(): Promise<void> {
    const response = await Contena.Service('admin_userConfigService').search([USER_THEME_CONFIG_KEY]);

    // The service swallows request errors and resolves without a response.
    // Keep the current preference then instead of resetting a valid choice.
    if (!response) {
        return;
    }

    const value = response.data?.[USER_THEME_CONFIG_KEY] as { theme?: unknown } | undefined;

    // `localStorage` is shared by every admin_user of the browser, so a admin_user without
    // a server-side preference has to fall back to the default instead of
    // inheriting the choice of whoever logged in here before.
    useTheme().setTheme(value && isTheme(value.theme) ? value.theme : DEFAULT_THEME);
}

async function saveUserTheme(theme: Theme): Promise<void> {
    useTheme().setTheme(theme);

    await Contena.Service('admin_userConfigService').upsert({
        [USER_THEME_CONFIG_KEY]: { theme },
    });
}

/**
 * App-wide singleton around the Meteor `useTheme` composable. The state is
 * created in a detached effect scope so its watchers are never bound to a
 * component lifecycle, even when the first call happens inside a component.
 *
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
