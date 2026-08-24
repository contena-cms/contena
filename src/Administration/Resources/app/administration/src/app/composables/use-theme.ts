/**
 * @ct-package framework
 */
import { computed, ref, watch, type ComputedRef, type CSSProperties, type Ref } from 'vue';
import { theme as antDesignTheme } from 'ant-design-vue';
import type { ThemeConfig } from 'ant-design-vue/es/config-provider/context';
import defaultTheme from 'src/app/theme/default.theme';

export const USER_THEME_CONFIG_KEY = 'core.userTheme';
export const ADMIN_THEME_STORAGE_KEY = 'ct-theme';

export type Theme = 'light' | 'dark' | 'system';
type ResolvedTheme = Exclude<Theme, 'system'>;

interface UseThemeReturn {
    theme: Ref<Theme>;
    resolvedTheme: ComputedRef<ResolvedTheme>;
    themeName: string;
    themeConfig: ComputedRef<ThemeConfig>;
    cssVariables: ComputedRef<CSSProperties>;
    setTheme: (theme: Theme) => void;
    loadUserTheme: () => Promise<void>;
    saveUserTheme: (theme: Theme) => Promise<void>;
}

let themeState: UseThemeReturn | null = null;

function isTheme(value: unknown): value is Theme {
    return value === 'light' || value === 'dark' || value === 'system';
}

function getStoredTheme(): Theme {
    const storedTheme = localStorage.getItem(ADMIN_THEME_STORAGE_KEY);

    return isTheme(storedTheme) ? storedTheme : 'system';
}

function getSystemTheme(): ResolvedTheme {
    const mediaQuery = window.matchMedia?.('(prefers-color-scheme: dark)');

    return mediaQuery?.matches ? 'dark' : 'light';
}

function createThemeState(): UseThemeReturn {
    const theme = ref<Theme>(getStoredTheme());
    const systemTheme = ref<ResolvedTheme>(getSystemTheme());
    const resolvedTheme = computed<ResolvedTheme>(() => (theme.value === 'system' ? systemTheme.value : theme.value));
    const themeConfig = computed<ThemeConfig>(() => ({
        algorithm: defaultTheme.algorithms[resolvedTheme.value],
        token: defaultTheme.token,
        components: defaultTheme.components,
    }));
    const cssVariables = computed<CSSProperties>(() => {
        const seedToken = {
            ...antDesignTheme.defaultConfig.token,
            ...defaultTheme.token,
        };
        const token = defaultTheme.algorithms[resolvedTheme.value](seedToken);
        const { layout } = defaultTheme;

        return {
            '--ct-color-bg-layout': token.colorBgLayout,
            '--ct-color-bg-container': token.colorBgContainer,
            '--ct-color-bg-elevated': token.colorBgElevated,
            '--ct-color-fill-secondary': token.colorFillSecondary,
            '--ct-color-fill-tertiary': token.colorFillTertiary,
            '--ct-color-border': token.colorBorder,
            '--ct-color-border-secondary': token.colorBorderSecondary,
            '--ct-color-text': token.colorText,
            '--ct-color-text-secondary': token.colorTextSecondary,
            '--ct-color-text-tertiary': token.colorTextTertiary,
            '--ct-color-primary': token.colorPrimary,
            '--ct-color-primary-bg': token.colorPrimaryBg,
            '--ct-color-success': token.colorSuccess,
            '--ct-color-success-bg': token.colorSuccessBg,
            '--ct-color-warning': token.colorWarning,
            '--ct-color-warning-bg': token.colorWarningBg,
            '--ct-border-radius': `${token.borderRadius}px`,
            '--ct-border-radius-lg': `${token.borderRadiusLG}px`,
            '--ct-control-height': `${token.controlHeight}px`,
            '--ct-font-size': `${token.fontSize}px`,
            '--ct-font-size-sm': `${token.fontSizeSM}px`,
            '--ct-font-size-lg': `${token.fontSizeLG}px`,
            '--ct-font-size-heading-3': `${token.fontSizeHeading3}px`,
            '--ct-line-height': String(token.lineHeight),
            '--ct-line-height-sm': String(token.lineHeightSM),
            '--ct-line-height-lg': String(token.lineHeightLG),
            '--ct-spacing-xxs': `${token.sizeXXS}px`,
            '--ct-spacing-xs': `${token.sizeXS}px`,
            '--ct-spacing-sm': `${token.sizeSM}px`,
            '--ct-spacing': `${token.size}px`,
            '--ct-spacing-md': `${token.sizeMD}px`,
            '--ct-spacing-lg': `${token.sizeLG}px`,
            '--ct-spacing-xl': `${token.sizeXL}px`,
            '--ct-layout-sidebar-width': `${layout.sidebarWidth}px`,
            '--ct-layout-sidebar-collapsed-width': `${layout.sidebarCollapsedWidth}px`,
            '--ct-layout-topbar-height': `${layout.topbarHeight}px`,
            '--ct-layout-content-max-width': `${layout.contentMaxWidth}px`,
        } as CSSProperties;
    });
    const mediaQuery = window.matchMedia?.('(prefers-color-scheme: dark)');

    mediaQuery?.addEventListener?.('change', (event) => {
        systemTheme.value = event.matches ? 'dark' : 'light';
    });

    watch(
        theme,
        (value) => {
            localStorage.setItem(ADMIN_THEME_STORAGE_KEY, value);
        },
        { immediate: true },
    );
    watch(
        resolvedTheme,
        (value) => {
            document.documentElement.dataset.theme = value;
            document.documentElement.style.colorScheme = value;
        },
        { immediate: true },
    );
    watch(
        cssVariables,
        (variables) => {
            Object.entries(variables).forEach(([property, value]) => {
                if (value !== undefined && value !== null) {
                    document.documentElement.style.setProperty(property, String(value));
                }
            });
        },
        { immediate: true },
    );

    const setTheme = (value: Theme): void => {
        theme.value = value;
    };
    const loadUserTheme = async (): Promise<void> => {
        const response = await Contena.Service('userConfigService').search([USER_THEME_CONFIG_KEY]);
        const value = response?.data?.[USER_THEME_CONFIG_KEY] as { theme?: unknown } | undefined;

        if (value && isTheme(value.theme)) {
            setTheme(value.theme);
        }
    };
    const saveUserTheme = async (value: Theme): Promise<void> => {
        setTheme(value);

        await Contena.Service('userConfigService').upsert({
            [USER_THEME_CONFIG_KEY]: { theme: value },
        });
    };

    return {
        theme,
        resolvedTheme,
        themeName: defaultTheme.name,
        themeConfig,
        cssVariables,
        setTheme,
        loadUserTheme,
        saveUserTheme,
    };
}

/**
 * @private
 */
export default function useTheme(): UseThemeReturn {
    themeState ??= createThemeState();

    return themeState;
}
