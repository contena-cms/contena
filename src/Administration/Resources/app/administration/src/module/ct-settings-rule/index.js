import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-rule-list', () => import('./page/ct-settings-rule-list'));
Contena.Component.register('ct-settings-rule-detail', () => import('./page/ct-settings-rule-detail'));
Contena.Component.register('ct-rule-condition-editor', () => import('./component/ct-rule-condition-editor'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-rule', {
    type: 'core',
    name: 'settings-rule',
    title: 'ct-settings-rule.general.mainMenuItemGeneral',
    description: 'ct-settings-rule.general.descriptionTextModule',
    color: 'var(--color-red-300)',
    icon: 'regular-rule',
    favicon: 'icon-module-settings.png',
    entity: 'rule',
    routes: {
        index: {
            component: 'ct-settings-rule-list',
            path: 'index',
            meta: { privilege: 'rule.viewer' },
        },
        create: {
            component: 'ct-settings-rule-detail',
            path: 'create',
            meta: { parentPath: 'ct.settings.rule.index', privilege: 'rule.creator' },
        },
        detail: {
            component: 'ct-settings-rule-detail',
            path: 'detail/:id',
            meta: { parentPath: 'ct.settings.rule.index', privilege: 'rule.viewer' },
            props: { default: (route) => ({ ruleId: route.params.id.toLowerCase() }) },
        },
    },
    navigation: [
        {
            id: 'ct-settings-rule',
            label: 'ct-settings-rule.general.mainMenuItemGeneral',
            path: 'ct.settings.rule.index',
            icon: 'regular-rule',
            color: 'var(--color-red-300)',
            parent: 'ct-automation',
            privilege: 'rule.viewer',
            position: 10,
        },
        {
            id: 'ct-automation',
            label: 'global.ct-admin-menu.navigation.mainMenuItemAutomation',
            icon: 'regular-rule',
            color: 'var(--color-red-300)',
            position: 70,
        },
    ],
});
