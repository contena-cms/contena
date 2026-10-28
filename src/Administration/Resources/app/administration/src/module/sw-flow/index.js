import './service';
import './acl';
import './store/flow.store';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-flow-index', () => import('./page/sw-flow-index'));
Contena.Component.register('sw-flow-detail', () => import('./page/sw-flow-detail'));
Contena.Component.register('sw-flow-detail-flow', () => import('./view/detail/sw-flow-detail-flow'));
Contena.Component.register('sw-flow-sequence-modal', () => import('./component/sw-flow-sequence-modal'));
Contena.Component.register('sw-flow-detail-general', () => import('./view/detail/sw-flow-detail-general'));
Contena.Component.register('sw-flow-list', () => import('./view/listing/sw-flow-list'));
Contena.Component.register('sw-flow-list-flow-templates', () => import('./view/listing/sw-flow-list-flow-templates'));
Contena.Component.register('sw-flow-trigger', () => import('./component/sw-flow-trigger'));
Contena.Component.register('sw-flow-sequence', () => import('./component/sw-flow-sequence'));
Contena.Component.register('sw-flow-sequence-action', () => import('./component/sw-flow-sequence-action'));
Contena.Component.register('sw-flow-sequence-condition', () => import('./component/sw-flow-sequence-condition'));
Contena.Component.register('sw-flow-sequence-selector', () => import('./component/sw-flow-sequence-selector'));
Contena.Component.register('sw-flow-sequence-action-error', () => import('./component/sw-flow-sequence-action-error'));
Contena.Component.register('sw-flow-rule-modal', () => import('./component/modals/sw-flow-rule-modal'));
Contena.Component.register('sw-flow-tag-modal', () => import('./component/modals/sw-flow-tag-modal'));
Contena.Component.register(
    'sw-flow-set-order-state-modal',
    () => import('./component/modals/sw-flow-set-order-state-modal'),
);
Contena.Component.register(
    'sw-flow-generate-document-modal',
    () => import('./component/modals/sw-flow-generate-document-modal'),
);
Contena.Component.register(
    'sw-flow-grant-download-access-modal',
    () => import('./component/modals/sw-flow-grant-download-access-modal'),
);
Contena.Component.register('sw-flow-mail-send-modal', () => import('./component/modals/sw-flow-mail-send-modal'));
Contena.Component.register(
    'sw-flow-create-mail-template-modal',
    () => import('./component/modals/sw-flow-create-mail-template-modal'),
);
Contena.Component.register(
    'sw-flow-event-change-confirm-modal',
    () => import('./component/modals/sw-flow-event-change-confirm-modal'),
);
Contena.Component.register(
    'sw-flow-change-customer-group-modal',
    () => import('./component/modals/sw-flow-change-customer-group-modal'),
);
Contena.Component.register(
    'sw-flow-change-customer-status-modal',
    () => import('./component/modals/sw-flow-change-customer-status-modal'),
);
Contena.Component.register(
    'sw-flow-set-entity-custom-field-modal',
    () => import('./component/modals/sw-flow-set-entity-custom-field-modal'),
);
Contena.Component.register(
    'sw-flow-affiliate-and-campaign-code-modal',
    () => import('./component/modals/sw-flow-affiliate-and-campaign-code-modal'),
);
Contena.Component.register('sw-flow-app-action-modal', () => import('./component/modals/sw-flow-app-action-modal'));
Contena.Component.register('sw-flow-leave-page-modal', () => import('./component/modals/sw-flow-leave-page-modal'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @private
 * @sw-package after-sales
 */
Module.register('sw-flow', {
    type: 'core',
    name: 'flow',
    title: 'sw-flow.general.mainMenuItemGeneral',
    description: 'sw-flow.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-red-default)',
    icon: 'regular-flow',
    favicon: 'icon-module-settings.svg',
    entity: 'flow',
    defaultSearchConfiguration,

    routes: {
        index: {
            component: 'sw-flow-index',
            path: 'index',
            meta: {
                privilege: 'flow.viewer',
            },
            redirect: {
                name: 'sw.flow.index.flows',
            },
            children: {
                flows: {
                    component: 'sw-flow-list',
                    path: 'flows',
                    meta: {
                        privilege: 'flow.viewer',
                    },
                },
                templates: {
                    component: 'sw-flow-list-flow-templates',
                    path: 'templates',
                    meta: {
                        privilege: 'flow.viewer',
                    },
                },
            },
        },
        detail: {
            component: 'sw-flow-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.flow.index',
                privilege: 'flow.viewer',
            },
            props: {
                default(route) {
                    return {
                        flowId: route.params.id.toLowerCase(),
                    };
                },
            },
            redirect: {
                name: 'sw.flow.detail.general',
            },
            children: {
                general: {
                    component: 'sw-flow-detail-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.viewer',
                    },
                },
                flow: {
                    component: 'sw-flow-detail-flow',
                    path: 'flow',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.viewer',
                    },
                },
            },
        },
        create: {
            component: 'sw-flow-detail',
            path: 'create/:flowTemplateId?',
            meta: {
                parentPath: 'sw.flow.index',
                privilege: 'flow.creator',
            },
            redirect: {
                name: 'sw.flow.create.general',
            },
            children: {
                general: {
                    component: 'sw-flow-detail-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.viewer',
                    },
                },
                flow: {
                    component: 'sw-flow-detail-flow',
                    path: 'flow',
                    meta: {
                        parentPath: 'sw.flow.index',
                        privilege: 'flow.viewer',
                    },
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-flow',
            label: 'sw-flow.general.mainMenuItemGeneral',
            path: 'sw.flow.index',
            icon: 'regular-flow',
            color: 'var(--sw-color-module-red-default)',
            parent: 'sw-automation',
            privilege: 'flow.viewer',
            position: 20,
        },
    ],

    settingsItem: {
        group: 'automation',
        to: 'sw.flow.index',
        icon: 'regular-flow',
        privilege: 'flow.viewer',
    },
});
