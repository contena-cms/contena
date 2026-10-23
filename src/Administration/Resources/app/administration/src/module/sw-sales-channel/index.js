/**
 * @sw-package discovery
 */

import './service/export-template.service';
import './blog-export-templates';
import './agentic-blog-export-templates';
import './service/domain-link.service';
import './service/sales-channel-file.api.service';
import './service/sales-channel-favorites.service';
import './component/structure/sw-admin-menu-extension';
import './acl';

import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register(
    'sw-sales-channel-defaults-select',
    () => import('./component/sw-sales-channel-defaults-select'),
);
Contena.Component.register('sw-sales-channel-modal', () => import('./component/sw-sales-channel-modal'));
Contena.Component.register('sw-sales-channel-modal-grid', () => import('./component/sw-sales-channel-modal-grid'));
Contena.Component.register('sw-sales-channel-modal-detail', () => import('./component/sw-sales-channel-modal-detail'));
Contena.Component.register('sw-sales-channel-detail-domains', () => import('./component/sw-sales-channel-detail-domains'));
Contena.Component.register(
    'sw-sales-channel-detail-hreflang',
    () => import('./component/sw-sales-channel-detail-hreflang'),
);
Contena.Component.register('sw-sales-channel-detail', () => import('./page/sw-sales-channel-detail'));
Contena.Component.extend(
    'sw-sales-channel-create',
    'sw-sales-channel-detail',
    () => import('./page/sw-sales-channel-create'),
);
Contena.Component.register('sw-sales-channel-list', () => import('./page/sw-sales-channel-list'));
Contena.Component.register('sw-sales-channel-detail-base', () => import('./view/sw-sales-channel-detail-base'));
Contena.Component.register('sw-sales-channel-detail-blogs', () => import('./view/sw-sales-channel-detail-blogs'));
Contena.Component.register(
    'sw-sales-channel-detail-agentic-files',
    () => import('./view/sw-sales-channel-detail-agentic-files'),
);
Contena.Component.register(
    'sw-sales-channel-detail-agentic-file',
    () => import('./view/sw-sales-channel-detail-agentic-file'),
);
Contena.Component.register('sw-sales-channel-detail-analytics', () => import('./view/sw-sales-channel-detail-analytics'));
Contena.Component.extend(
    'sw-sales-channel-create-base',
    'sw-sales-channel-detail-base',
    () => import('./view/sw-sales-channel-create-base'),
);
Contena.Component.register(
    'sw-sales-channel-detail-blog-comparison',
    () => import('./view/sw-sales-channel-detail-blog-comparison'),
);
Contena.Component.register(
    'sw-sales-channel-detail-blog-comparison-preview',
    () => import('./view/sw-sales-channel-detail-blog-comparison-preview'),
);
Contena.Component.register(
    'sw-sales-channel-detail-agentic-commerce-integration',
    () => import('./view/sw-sales-channel-detail-agentic-commerce-integration'),
);
Contena.Component.register(
    'sw-agentic-commerce-tracking-config',
    () => import('./component/sw-agentic-commerce-tracking-config'),
);
Contena.Component.register(
    'sw-sales-channel-detail-blog-export-insights',
    () => import('./view/sw-sales-channel-detail-blog-export-insights'),
);
Contena.Component.register(
    'sw-sales-channel-blogs-assignment-modal',
    () => import('./component/sw-sales-channel-blogs-assignment-modal'),
);
Contena.Component.register(
    'sw-sales-channel-blogs-assignment-single-blogs',
    () => import('./component/sw-sales-channel-blogs-assignment-single-blogs'),
);
Contena.Component.register(
    'sw-sales-channel-blogs-assignment-dynamic-blog-groups',
    () => import('./component/sw-sales-channel-blogs-assignment-dynamic-blog-groups'),
);
Contena.Component.register(
    'sw-sales-channel-blog-assignment-categories',
    () => import('./component/sw-sales-channel-blog-assignment-categories'),
);
Contena.Component.register('sw-sales-channel-menu', () => import('./component/structure/sw-sales-channel-menu'));

Contena.Component.register('sw-sales-channel-measurement', () => import('./component/sw-sales-channel-measurement'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-sales-channel', {
    type: 'core',
    name: 'sales-channel',
    title: 'sw-sales-channel.general.titleMenuItems',
    description: 'The module for managing Sales Channels.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-frontend',
    entity: 'channel',

    searchMatcher: (regex, labelType, manifest) => {
        const match = labelType.toLowerCase().match(regex);

        if (!match) {
            return false;
        }

        return [
            {
                name: manifest.name,
                icon: manifest.icon,
                color: manifest.color,
                label: labelType,
                entity: manifest.entity,
                route: manifest.routes.list,
                privilege: manifest.routes.list?.meta.privilege,
            },
        ];
    },

    routes: {
        detail: {
            component: 'sw-sales-channel-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.sales.channel.list',
                privilege: 'channel.viewer',
            },
            redirect: {
                name: 'sw.sales.channel.detail.base',
            },
            children: {
                base: {
                    component: 'sw-sales-channel-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
                blogs: {
                    component: 'sw-sales-channel-detail-blogs',
                    path: 'blogs',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
                agenticFiles: {
                    component: 'sw-sales-channel-detail-agentic-files',
                    path: 'agentic-files',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
                agenticFile: {
                    component: 'sw-sales-channel-detail-agentic-file',
                    path: 'agentic-files/:fileName(.*)',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
                blogComparison: {
                    component: 'sw-sales-channel-detail-blog-comparison',
                    path: 'blog-comparison',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
                analytics: {
                    component: 'sw-sales-channel-detail-analytics',
                    path: 'analytics',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
                agenticCommerceIntegration: {
                    component: 'sw-sales-channel-detail-agentic-commerce-integration',
                    path: 'agentic-commerce-integration',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
                blogExportInsights: {
                    component: 'sw-sales-channel-detail-blog-export-insights',
                    path: 'blog-export-insights',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
            },
        },

        create: {
            component: 'sw-sales-channel-create',
            path: 'create/:typeId',
            redirect: {
                name: 'sw.sales.channel.create.base',
            },
            children: {
                base: {
                    component: 'sw-sales-channel-create-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'channel.creator',
                    },
                },
            },
        },

        list: {
            component: 'sw-sales-channel-list',
            path: 'list',
            meta: {
                privilege: 'channel.viewer',
            },
        },
    },

    defaultSearchConfiguration,
});
