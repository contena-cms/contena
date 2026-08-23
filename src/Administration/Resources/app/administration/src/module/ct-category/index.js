/**
 * @ct-package discovery
 */
import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import './page/ct-category-detail/store';
import enGB from './snippet/en.json';
import zhCN from './snippet/zh.json';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-category-tree', () => import('./component/ct-category-tree'));
Contena.Component.register('ct-landing-page-tree', () => import('./component/ct-landing-page-tree'));
Contena.Component.register('ct-landing-page-view', () => import('./component/ct-landing-page-view'));
Contena.Component.register('ct-category-view', () => import('./component/ct-category-view'));
Contena.Component.register('ct-category-link-settings', () => import('./component/ct-category-link-settings'));
Contena.Component.register('ct-category-detail-menu', () => import('./component/ct-category-detail-menu'));
Contena.Component.register('ct-category-seo-form', () => import('./component/ct-category-seo-form'));
Contena.Component.register('ct-category-entry-point-card', () => import('./component/ct-category-entry-point-card'));
Contena.Component.register('ct-category-entry-point-modal', () => import('./component/ct-category-entry-point-modal'));
Contena.Component.register('ct-category-layout-assignment', () => import('./component/ct-category-layout-assignment'));
Contena.Component.register(
    'ct-category-entry-point-overwrite-modal',
    () => import('./component/ct-category-entry-point-overwrite-modal'),
);
Contena.Component.extend(
    'ct-category-channel-multi-select',
    'ct-entity-multi-select',
    () => import('./component/ct-category-channel-multi-select'),
);
Contena.Component.register('ct-category-detail', () => import('./page/ct-category-detail'));
Contena.Component.register('ct-category-detail-base', () => import('./view/ct-category-detail-base'));
Contena.Component.register('ct-landing-page-detail-base', () => import('./view/ct-landing-page-detail-base'));
Contena.Component.register('ct-category-detail-seo', () => import('./view/ct-category-detail-seo'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-category', {
    type: 'core',
    name: 'category',
    title: 'ct-category.general.mainMenuItemIndex',
    description: 'ct-category.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-module-purple-500)',
    icon: 'regular-sitemap',
    favicon: 'icon-module-content.png',
    entity: 'category',
    snippets: {
        'en-GB': enGB,
        'zh-CN': zhCN,
    },

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
                route: manifest.routes.index,
                privilege: manifest.routes.index?.meta.privilege,
            },
            {
                name: manifest.name,
                icon: manifest.icon,
                color: manifest.color,
                route: {
                    ...manifest.routes.landingPageDetail,
                    params: { id: 'create' },
                },
                entity: 'landing_page',
                privilege: manifest.routes.landingPageDetail?.meta.privilege,
                action: true,
            },
        ];
    },

    routes: {
        index: {
            component: 'ct-category-detail',
            path: 'index',
            meta: {
                parentPath: 'ct.category.index',
                privilege: 'category.viewer',
            },
        },

        detail: {
            component: 'ct-category-detail',
            path: 'index/:id',
            meta: {
                privilege: 'category.viewer',
            },
            redirect: {
                name: 'ct.category.detail.base',
            },

            children: {
                base: {
                    component: 'ct-category-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'ct.category.index',
                        privilege: 'category.viewer',
                    },
                },
                layout: {
                    component: 'ct-category-layout-assignment',
                    path: 'layout',
                    props: { entityType: 'category' },
                    meta: {
                        parentPath: 'ct.category.index',
                        privilege: 'category.viewer',
                    },
                },
                seo: {
                    component: 'ct-category-detail-seo',
                    path: 'seo',
                    meta: {
                        parentPath: 'ct.category.index',
                        privilege: 'category.viewer',
                    },
                },
            },

            props: {
                default(route) {
                    return {
                        categoryId: route.params.id.toLowerCase(),
                    };
                },
            },
        },

        landingPageDetail: {
            component: 'ct-category-detail',
            path: 'landingPage/:id',
            meta: {
                privilege: 'category.viewer',
            },
            redirect: {
                name: 'ct.category.landingPageDetail.base',
            },

            children: {
                base: {
                    component: 'ct-landing-page-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'ct.category.index',
                        privilege: 'category.viewer',
                    },
                },
                layout: {
                    component: 'ct-category-layout-assignment',
                    path: 'layout',
                    props: { entityType: 'landing_page' },
                    meta: {
                        parentPath: 'ct.category.index',
                        privilege: 'landing_page.viewer',
                    },
                },
            },

            props: {
                default(route) {
                    return {
                        landingPageId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
    },

    navigation: [
        {
            id: 'ct-category',
            path: 'ct.category.index',
            label: 'ct-category.general.mainMenuItemIndex',
            parent: 'ct-content',
            privilege: 'category.viewer',
            position: 20,
        },
    ],

    defaultSearchConfiguration,
});
