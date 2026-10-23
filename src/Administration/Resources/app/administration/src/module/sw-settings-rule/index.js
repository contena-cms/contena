import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register(
    'sw-settings-rule-add-assignment-modal',
    () => import('./component/sw-settings-rule-add-assignment-modal'),
);
Contena.Component.register(
    'sw-settings-rule-add-assignment-listing',
    () => import('./component/sw-settings-rule-add-assignment-listing'),
);
Contena.Component.extend(
    'sw-settings-rule-assignment-listing',
    'sw-entity-listing',
    () => import('./component/sw-settings-rule-assignment-listing'),
);
Contena.Component.register('sw-settings-rule-category-tree', () => import('./component/sw-settings-rule-category-tree'));
Contena.Component.extend(
    'sw-settings-rule-tree-item',
    'sw-tree-item',
    () => import('./component/sw-settings-rule-tree-item'),
);
Contena.Component.extend('sw-settings-rule-tree', 'sw-tree', () => import('./component/sw-settings-rule-tree'));
Contena.Component.register('sw-settings-rule-list', () => import('./page/sw-settings-rule-list'));
Contena.Component.register('sw-settings-rule-detail', () => import('./page/sw-settings-rule-detail'));
Contena.Component.register('sw-settings-rule-detail-base', () => import('./view/sw-settings-rule-detail-base'));
Contena.Component.register(
    'sw-settings-rule-detail-assignments',
    () => import('./view/sw-settings-rule-detail-assignments'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
Module.register('sw-settings-rule', {
    type: 'core',
    name: 'settings-rule',
    title: 'sw-settings-rule.general.mainMenuItemGeneral',
    description: 'sw-settings-rule.general.descriptionTextModule',
    color: 'var(--sw-color-module-red-default)',
    icon: 'regular-rule',
    favicon: 'icon-module-settings.svg',
    entity: 'rule',
    defaultSearchConfiguration,

    routes: {
        index: {
            component: 'sw-settings-rule-list',
            path: 'index',
            meta: {
                privilege: 'rule.viewer',
            },
        },
        detail: {
            component: 'sw-settings-rule-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.rule.index',
                privilege: 'rule.viewer',
            },
            props: {
                default(route) {
                    return {
                        ruleId: route.params.id.toLowerCase(),
                    };
                },
            },
            redirect: {
                name: 'sw.settings.rule.detail.base',
            },
            children: {
                base: {
                    component: 'sw-settings-rule-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.settings.rule.index',
                        privilege: 'rule.viewer',
                    },
                },
                assignments: {
                    component: 'sw-settings-rule-detail-assignments',
                    path: 'assignments',
                    meta: {
                        parentPath: 'sw.settings.rule.index',
                        privilege: 'rule.viewer',
                    },
                },
            },
        },
        create: {
            component: 'sw-settings-rule-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.rule.index',
                privilege: 'rule.creator',
            },
            redirect: {
                name: 'sw.settings.rule.create.base',
            },
            children: {
                base: {
                    component: 'sw-settings-rule-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.settings.rule.index',
                        privilege: 'rule.viewer',
                    },
                },
            },
        },
    },

    // The child entry must stay first here, so the main menu active state is working correctly for rule builders
    // sub routes (children of `sw.settings.index`). Settings menu entry would be active otherwise.
    navigation: [
        {
            id: 'sw-settings-rule',
            label: 'sw-settings-rule.general.mainMenuItemGeneral',
            path: 'sw.settings.rule.index',
            icon: 'regular-rule',
            color: 'var(--sw-color-module-red-default)',
            parent: 'sw-automation',
            privilege: 'rule.viewer',
            position: 10,
        },
        {
            id: 'sw-automation',
            label: 'global.sw-admin-menu.navigation.mainMenuItemAutomation',
            icon: 'regular-rule',
            color: 'var(--sw-color-module-red-default)',
            position: 70,
        },
    ],

    settingsItem: {
        group: 'automation',
        to: 'sw.settings.rule.index',
        icon: 'regular-rule',
        privilege: 'rule.viewer',
    },
});
