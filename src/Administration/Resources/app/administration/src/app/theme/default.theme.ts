import { theme as antDesignTheme } from 'ant-design-vue';
import type { AdministrationThemeDefinition } from './administration-theme.types';

const defaultTheme: AdministrationThemeDefinition = {
    name: 'default',
    algorithms: {
        light: antDesignTheme.defaultAlgorithm,
        dark: antDesignTheme.darkAlgorithm,
    },
    token: {
        colorPrimary: '#2563eb',
        colorInfo: '#2563eb',
        colorSuccess: '#16a34a',
        colorWarning: '#d97706',
        colorError: '#dc2626',
        borderRadius: 6,
        borderRadiusLG: 8,
        controlHeight: 32,
        controlHeightLG: 40,
        fontFamily:
            'Inter, "PingFang SC", "Microsoft YaHei", "Noto Sans CJK SC", -apple-system, BlinkMacSystemFont, sans-serif',
    },
    components: {
        Menu: { radiusItem: 6, radiusSubMenuItem: 6 },
    },
    layout: {
        sidebarWidth: 240,
        sidebarCollapsedWidth: 72,
        topbarHeight: 56,
        contentMaxWidth: 1640,
    },
};

export default defaultTheme;
