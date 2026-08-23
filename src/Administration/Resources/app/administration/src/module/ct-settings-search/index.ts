import './service/live-search.api.service';
import './service/blog-index.api.service';
import 'src/core/service/api/excluded-search-term.api.service';
import './acl';
import enGB from './snippet/en.json';
import zhCN from './snippet/zh.json';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-search-live-search', () => import('./component/ct-settings-search-live-search'));
Contena.Component.register('ct-settings-search-example-modal', () => import('./component/ct-settings-search-example-modal'));
Contena.Component.register(
    'ct-settings-search-live-search-explain',
    () => import('./component/ct-settings-search-live-search-explain'),
);
Contena.Component.register('ct-settings-search', () => import('./page/ct-settings-search'));
Contena.Component.register('ct-settings-search-view-general', () => import('./view/ct-settings-search-view-general'));
Contena.Component.register(
    'ct-settings-search-view-live-search',
    () => import('./view/ct-settings-search-view-live-search'),
);
Contena.Component.register(
    'ct-settings-search-excluded-search-terms',
    () => import('./component/ct-settings-search-excluded-search-terms'),
);
Contena.Component.register('ct-settings-search-search-index', () => import('./component/ct-settings-search-search-index'));
Contena.Component.register(
    'ct-settings-search-live-search-keyword',
    () => import('./component/ct-settings-search-live-search-keyword'),
);
Contena.Component.register(
    'ct-settings-search-search-behaviour',
    () => import('./component/ct-settings-search-search-behaviour'),
);
Contena.Component.register(
    'ct-settings-search-searchable-content',
    () => import('./component/ct-settings-search-searchable-content'),
);
Contena.Component.register(
    'ct-settings-search-searchable-content-general',
    () => import('./component/ct-settings-search-searchable-content-general'),
);
Contena.Component.register(
    'ct-settings-search-searchable-content-customfields',
    () => import('./component/ct-settings-search-searchable-content-customfields'),
);
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Module.register('ct-settings-search', {
    type: 'core',
    name: 'settings-blog-search-config',
    title: 'ct-settings-search.general.mainMenuItemGeneral',
    description: 'ct-settings-search.general.mainMenuItemGeneral',
    color: '#9AA8B5',
    icon: 'regular-search',
    version: '1.0.0',
    targetVersion: '1.0.0',
    entity: 'blog_search_config',
    snippets: { 'en-GB': enGB, 'zh-CN': zhCN },
    routes: {
        index: {
            component: 'ct-settings-search',
            path: 'index',
            meta: { parentPath: 'ct.settings.index', privilege: 'blog_search_config.viewer' },
            redirect: { name: 'ct.settings.search.index.general' },
            children: {
                general: {
                    component: 'ct-settings-search-view-general',
                    path: 'general',
                    meta: { parentPath: 'ct.settings.index', privilege: 'blog_search_config.viewer' },
                },
                liveSearch: {
                    component: 'ct-settings-search-view-live-search',
                    path: 'live-search',
                    meta: { parentPath: 'ct.settings.index', privilege: 'blog_search_config.viewer' },
                },
            },
        },
    },
    settingsItem: {
        group: 'general',
        to: 'ct.settings.search.index',
        icon: 'regular-search',
        privilege: 'blog_search_config.viewer',
    },
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
