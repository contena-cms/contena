/**
 * @sw-package inventory
 */

import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-blog-basic-form', () => import('./component/sw-blog-basic-form'));
Contena.Component.register('sw-blog-deliverability-form', () => import('./component/sw-blog-deliverability-form'));
Contena.Component.register(
    'sw-blog-deliverability-downloadable-form',
    () => import('./component/sw-blog-deliverability-downloadable-form'),
);
Contena.Component.register('sw-blog-feature-set-form', () => import('./component/sw-blog-feature-set-form'));
Contena.Component.register('sw-blog-category-form', () => import('./component/sw-blog-category-form'));
Contena.Component.register('sw-blog-clone-modal', () => import('./component/sw-blog-clone-modal'));
Contena.Component.register(
    'sw-blog-modal-variant-generation',
    () => import('./component/sw-blog-variants/sw-blog-modal-variant-generation'),
);
Contena.Component.register(
    'sw-blog-modal-delivery',
    () => import('./component/sw-blog-variants/sw-blog-modal-delivery'),
);
Contena.Component.register('sw-blog-price-form', () => import('./component/sw-blog-price-form'));
Contena.Component.register('sw-blog-settings-form', () => import('./component/sw-blog-settings-form'));
Contena.Component.register('sw-blog-guarantee-form', () => import('./component/sw-blog-guarantee-form'));
Contena.Component.register('sw-blog-measurement-form', () => import('./component/sw-blog-measurement-form'));
Contena.Component.register('sw-blog-packaging-form', () => import('./component/sw-blog-packaging-form'));
Contena.Component.register('sw-blog-seo-form', () => import('./component/sw-blog-seo-form'));
Contena.Component.extend(
    'sw-blog-visibility-select',
    'sw-entity-multi-select',
    () => import('./component/sw-blog-visibility-select'),
);
Contena.Component.register('sw-blog-media-form', () => import('./component/sw-blog-media-form'));
Contena.Component.register('sw-blog-download-form', () => import('./component/sw-blog-download-form'));
Contena.Component.register('sw-blog-visibility-detail', () => import('./component/sw-blog-visibility-detail'));
Contena.Component.register(
    'sw-blog-restriction-selection',
    () => import('./component/sw-blog-variants/sw-blog-variants-configurator/sw-blog-restriction-selection'),
);
Contena.Component.extend(
    'sw-blog-variants-configurator-selection',
    'sw-property-search',
    () =>
        import(
            './component/sw-blog-variants/sw-blog-variants-configurator/sw-blog-variants-configurator-selection'
        ),
);
Contena.Component.register(
    'sw-blog-variants-configurator-prices',
    () => import('./component/sw-blog-variants/sw-blog-variants-configurator/sw-blog-variants-configurator-prices'),
);
Contena.Component.register(
    'sw-blog-variants-configurator-restrictions',
    () =>
        import(
            './component/sw-blog-variants/sw-blog-variants-configurator/sw-blog-variants-configurator-restrictions'
        ),
);
Contena.Component.register(
    'sw-blog-variants-delivery-order',
    () => import('./component/sw-blog-variants/sw-blog-variants-delivery/sw-blog-variants-delivery-order'),
);
Contena.Component.register(
    'sw-blog-variants-delivery-media',
    () => import('./component/sw-blog-variants/sw-blog-variants-delivery/sw-blog-variants-delivery-media'),
);
Contena.Component.register(
    'sw-blog-variants-delivery-listing',
    () => import('./component/sw-blog-variants/sw-blog-variants-delivery/sw-blog-variants-delivery-listing'),
);
Contena.Component.register(
    'sw-blog-variants-overview',
    () => import('./component/sw-blog-variants/sw-blog-variants-overview'),
);
Contena.Component.register(
    'sw-blog-variants-price-field',
    () => import('./component/sw-blog-variants/sw-blog-variants-configurator/sw-blog-variants-price-field'),
);
Contena.Component.extend(
    'sw-blog-variants-media-upload',
    'sw-media-upload-v2',
    () => import('./component/sw-blog-variants/sw-blog-variants-media-upload'),
);
Contena.Component.register('sw-blog-cross-selling-form', () => import('./component/sw-blog-cross-selling-form'));
Contena.Component.register('sw-blog-variant-modal', () => import('./component/sw-blog-variant-modal'));
Contena.Component.register(
    'sw-blog-cross-selling-assignment',
    () => import('./component/sw-blog-cross-selling-assignment'),
);
Contena.Component.register('sw-blog-layout-assignment', () => import('./component/sw-blog-layout-assignment'));
/**
 * @deprecated tag:v6.8.0 - File will be removed. No longer used.
 */
if (!Contena.Feature.isActive('v6.8.0.0')) {
    Contena.Component.register('sw-blog-settings-mode', () => import('./component/sw-blog-settings-mode'));
}
Contena.Component.register('sw-blog-properties', () => import('./component/sw-blog-properties'));
Contena.Component.register('sw-blog-add-properties-modal', () => import('./component/sw-blog-add-properties-modal'));
Contena.Component.register('sw-blog-detail-base', () => import('./view/sw-blog-detail-base'));
Contena.Component.register('sw-blog-detail-specifications', () => import('./view/sw-blog-detail-specifications'));
Contena.Component.register('sw-blog-detail-context-prices', () => import('./view/sw-blog-detail-context-prices'));
Contena.Component.register('sw-blog-detail-variants', () => import('./view/sw-blog-detail-variants'));
Contena.Component.register('sw-blog-detail-layout', () => import('./view/sw-blog-detail-layout'));
Contena.Component.register('sw-blog-detail-seo', () => import('./view/sw-blog-detail-seo'));
Contena.Component.register('sw-blog-detail-cross-selling', () => import('./view/sw-blog-detail-cross-selling'));
Contena.Component.register('sw-blog-detail-reviews', () => import('./view/sw-blog-detail-reviews'));
Contena.Component.register('sw-blog-list', () => import('./page/sw-blog-list'));
Contena.Component.register('sw-blog-detail', () => import('./page/sw-blog-detail'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-blog', {
    type: 'core',
    name: 'blog',
    title: 'sw-blog.general.mainMenuItemGeneral',
    description: 'sw-blog.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-blogs',
    favicon: 'icon-module-blogs.svg',
    entity: 'blog',

    routes: {
        index: {
            components: {
                default: 'sw-blog-list',
            },
            path: 'index',
            meta: {
                privilege: 'blog.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-blog-detail',
            path: 'create',
            props: {
                default: (route) => {
                    const props = {
                        creationType: route.query.creationType ?? 'physical',
                    };

                    if (!Contena.Feature.isActive('v6.8.0.0')) {
                        props.creationStates = route.query.creationStates ?? ['is-physical'];
                    }

                    return props;
                },
            },
            redirect: {
                name: 'sw.blog.create.base',
            },
            meta: {
                privilege: 'blog.creator',
            },
            children: {
                base: {
                    component: 'sw-blog-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.creator',
                    },
                },
            },
        },

        detail: {
            component: 'sw-blog-detail',
            path: 'detail/:id?',
            props: {
                default: (route) => ({ blogId: route.params.id?.toLowerCase() }),
            },
            redirect: {
                name: 'sw.blog.detail.base',
            },
            meta: {
                privilege: 'blog.viewer',
                appSystem: {
                    view: 'detail',
                },
                parentPath: 'sw.blog.index',
            },
            children: {
                base: {
                    component: 'sw-blog-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.viewer',
                    },
                },
                specifications: {
                    component: 'sw-blog-detail-specifications',
                    path: 'specifications',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.viewer',
                    },
                },
                prices: {
                    component: 'sw-blog-detail-context-prices',
                    path: 'prices',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.viewer',
                    },
                },
                variants: {
                    component: 'sw-blog-detail-variants',
                    path: 'variants',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.viewer',
                    },
                },
                layout: {
                    component: 'sw-blog-detail-layout',
                    path: 'layout',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.viewer',
                    },
                },
                seo: {
                    component: 'sw-blog-detail-seo',
                    path: 'seo',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.viewer',
                    },
                },
                crossSelling: {
                    component: 'sw-blog-detail-cross-selling',
                    path: 'cross-selling',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.viewer',
                    },
                },
                reviews: {
                    component: 'sw-blog-detail-reviews',
                    path: 'reviews',
                    meta: {
                        parentPath: 'sw.blog.index',
                        privilege: 'blog.viewer',
                    },
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-catalogue',
            label: 'global.sw-admin-menu.navigation.mainMenuItemProducts',
            color: 'var(--sw-color-module-green-default)',
            icon: 'regular-blogs',
            position: 20,
        },
        {
            id: 'sw-blog',
            label: 'sw-blog.general.mainMenuItemOverview',
            color: 'var(--sw-color-module-green-default)',
            path: 'sw.blog.index',
            icon: 'regular-blogs',
            parent: 'sw-catalogue',
            privilege: 'blog.viewer',
            position: 10,
        },
    ],

    defaultSearchConfiguration,
});
