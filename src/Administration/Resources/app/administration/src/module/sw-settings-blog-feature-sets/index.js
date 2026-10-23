/**
 * @sw-package inventory
 */

import './acl';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register(
    'sw-settings-blog-feature-sets-list',
    () => import('./page/sw-settings-blog-feature-sets-list'),
);
Contena.Component.register(
    'sw-settings-blog-feature-sets-detail',
    () => import('./page/sw-settings-blog-feature-sets-detail'),
);
Contena.Component.register(
    'sw-settings-blog-feature-sets-values-card',
    () => import('./component/sw-settings-blog-feature-sets-values-card'),
);
Contena.Component.register(
    'sw-settings-blog-feature-sets-modal',
    () => import('./component/sw-settings-blog-feature-sets-modal'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Contena;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-blog-feature-sets', {
    type: 'core',
    name: 'settings-blog-feature-sets',
    title: 'sw-settings-blog-feature-sets.general.mainMenuItemGeneral',
    description: 'Essential characteristics section in the settings module',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-check-square',
    favicon: 'icon-module-settings.svg',
    entity: 'blog_feature_set',

    routes: {
        index: {
            component: 'sw-settings-blog-feature-sets-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'blog_feature_sets.viewer',
            },
        },

        detail: {
            component: 'sw-settings-blog-feature-sets-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.blog.feature.sets.index',
                privilege: 'blog_feature_sets.viewer',
            },
            props: {
                default(route) {
                    return {
                        blogFeatureSetId: route.params.id.toLowerCase(),
                    };
                },
            },
        },

        create: {
            component: 'sw-settings-blog-feature-sets-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.blog.feature.sets.index',
                privilege: 'blog_feature_sets.creator',
            },
        },
    },

    settingsItem: {
        group: 'commerce',
        to: 'sw.settings.blog.feature.sets.index',
        icon: 'regular-check-square',
        privilege: 'blog_feature_sets.viewer',
    },
});
