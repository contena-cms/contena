/**
 * @sw-package inventory
 */
import './init/services.init';
import './acl';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-settings-search', () => import('./page/sw-settings-search'));
Contena.Component.register('sw-settings-search-view-general', () => import('./view/sw-settings-search-view-general'));
Contena.Component.register(
    'sw-settings-search-view-live-search',
    () => import('./view/sw-settings-search-view-live-search'),
);
Contena.Component.register(
    'sw-settings-search-search-behaviour',
    () => import('./component/sw-settings-search-search-behaviour'),
);
Contena.Component.register(
    'sw-settings-search-searchable-content',
    () => import('./component/sw-settings-search-searchable-content'),
);
Contena.Component.register(
    'sw-settings-search-example-modal',
    () => import('./component/sw-settings-search-example-modal'),
);
Contena.Component.register(
    'sw-settings-search-searchable-content-general',
    () => import('./component/sw-settings-search-searchable-content-general'),
);
Contena.Component.register(
    'sw-settings-search-searchable-content-customfields',
    () => import('./component/sw-settings-search-searchable-content-customfields'),
);
Contena.Component.register(
    'sw-settings-search-excluded-search-terms',
    () => import('./component/sw-settings-search-excluded-search-terms'),
);
Contena.Component.register('sw-settings-search-search-index', () => import('./component/sw-settings-search-search-index'));
Contena.Component.register('sw-settings-search-live-search', () => import('./component/sw-settings-search-live-search'));
Contena.Component.register(
    'sw-settings-search-live-search-keyword',
    () => import('./component/sw-settings-search-live-search-keyword'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-search', {
    type: 'core',
    name: 'settings-blog-search-config',
    title: 'sw-settings-search.general.mainMenuItemGeneral',
    description: 'sw-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-search',
    favicon: 'icon-module-settings.svg',
    entity: 'blog_search_config',

    routes: {
        index: {
            component: 'sw-settings-search',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'blog_search_config.viewer',
            },

            redirect: {
                name: 'sw.settings.search.index.general',
            },

            children: {
                general: {
                    component: 'sw-settings-search-view-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'blog_search_config.viewer',
                    },
                },

                liveSearch: {
                    component: 'sw-settings-search-view-live-search',
                    path: 'live-search',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'blog_search_config.viewer',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'sw.settings.search.index',
        icon: 'regular-search',
        privilege: 'blog_search_config.viewer',
    },
});
