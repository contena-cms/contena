/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import enGB from './snippet/en.json';
import zhCN from './snippet/zh.json';

Contena.Component.register('ct-dashboard-index', () => import('./page/ct-dashboard-index'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

/**
 *
 * @private
 */
Contena.Module.register('ct-dashboard', {
    type: 'core',
    name: 'dashboard',
    title: 'ct-dashboard.general.mainMenuItemGeneral',
    description: 'ct-dashboard.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#6AD6F0',
    icon: 'regular-dashboard',
    favicon: 'icon-module-dashboard.png',
    snippets: {
        'en-GB': enGB,
        'zh-CN': zhCN,
    },

    routes: {
        index: {
            components: {
                default: 'ct-dashboard-index',
            },
            path: 'index',
        },
    },

    navigation: [
        {
            id: 'ct-home',
            label: 'global.ct-admin-menu.navigation.mainMenuItemHome',
            color: '#6AD6F0',
            icon: 'regular-dashboard',
            path: 'ct.dashboard.index',
            position: 10,
        },
    ],
});
