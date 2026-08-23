import './acl';
import './component/flow-modal.scss';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-flow-index', () => import('./page/ct-flow-index'));
Contena.Component.register('ct-flow-detail', () => import('./page/ct-flow-detail'));
Contena.Component.register('ct-flow-sequence-editor', () => import('./component/ct-flow-sequence-editor'));
Contena.Component.register('ct-flow-trigger', () => import('./component/ct-flow-trigger'));
Contena.Component.register('ct-flow-sequence', () => import('./component/ct-flow-sequence'));
Contena.Component.register('ct-flow-sequence-selector', () => import('./component/ct-flow-sequence-selector'));
Contena.Component.register('ct-flow-sequence-action', () => import('./component/ct-flow-sequence-action'));
Contena.Component.register('ct-flow-sequence-condition', () => import('./component/ct-flow-sequence-condition'));
Contena.Component.register('ct-flow-mail-send-modal', () => import('./component/ct-flow-mail-send-modal'));
Contena.Component.register('ct-flow-notification-modal', () => import('./component/ct-flow-notification-modal'));
Contena.Component.register('ct-flow-rule-modal', () => import('./component/ct-flow-rule-modal'));
Contena.Component.register('ct-flow-user-status-modal', () => import('./component/ct-flow-user-status-modal'));
Contena.Component.register('ct-flow-tag-modal', () => import('./component/ct-flow-tag-modal'));
Contena.Component.register('ct-flow-user-custom-field-modal', () => import('./component/ct-flow-user-custom-field-modal'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-flow', {
    type: 'core',
    name: 'flow',
    title: 'ct-flow.general.mainMenuItemGeneral',
    description: 'ct-flow.general.descriptionTextModule',
    color: 'var(--color-red-300)',
    icon: 'regular-rule',
    favicon: 'icon-module-settings.png',
    entity: 'flow',
    routes: {
        index: {
            component: 'ct-flow-index',
            path: 'index',
            meta: { privilege: 'flow.viewer' },
        },
        create: {
            component: 'ct-flow-detail',
            path: 'create/:flowTemplateId?',
            meta: { parentPath: 'ct.flow.index', privilege: 'flow.creator' },
            props: { default: (route) => ({ flowTemplateId: route.params.flowTemplateId ?? null }) },
        },
        detail: {
            component: 'ct-flow-detail',
            path: 'detail/:id',
            meta: { parentPath: 'ct.flow.index', privilege: 'flow.viewer' },
            props: { default: (route) => ({ flowId: route.params.id.toLowerCase() }) },
        },
    },
    navigation: [
        {
            id: 'ct-flow',
            label: 'ct-flow.general.mainMenuItemGeneral',
            path: 'ct.flow.index',
            icon: 'regular-flow',
            color: 'var(--color-red-300)',
            parent: 'ct-automation',
            privilege: 'flow.viewer',
            position: 20,
        },
    ],
});
