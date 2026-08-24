import type { MappingAlgorithm, ThemeConfig } from 'ant-design-vue/es/config-provider/context';

export type AdministrationColorMode = 'light' | 'dark';

export interface AdministrationThemeDefinition {
    name: string;
    algorithms: Record<AdministrationColorMode, MappingAlgorithm>;
    token: ThemeConfig['token'];
    components: ThemeConfig['components'];
    layout: {
        sidebarWidth: number;
        sidebarCollapsedWidth: number;
        topbarHeight: number;
        contentMaxWidth: number;
    };
}
