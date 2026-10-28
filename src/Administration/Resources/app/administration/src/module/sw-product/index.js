/**
 * @sw-package inventory
 */

import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-product-basic-form', () => import('./component/sw-product-basic-form'));
Contena.Component.register('sw-product-deliverability-form', () => import('./component/sw-product-deliverability-form'));
Contena.Component.register(
    'sw-product-deliverability-downloadable-form',
    () => import('./component/sw-product-deliverability-downloadable-form'),
);
Contena.Component.register('sw-product-feature-set-form', () => import('./component/sw-product-feature-set-form'));
Contena.Component.register('sw-product-category-form', () => import('./component/sw-product-category-form'));
Contena.Component.register('sw-product-clone-modal', () => import('./component/sw-product-clone-modal'));
Contena.Component.register(
    'sw-product-modal-variant-generation',
    () => import('./component/sw-product-variants/sw-product-modal-variant-generation'),
);
Contena.Component.register(
    'sw-product-modal-delivery',
    () => import('./component/sw-product-variants/sw-product-modal-delivery'),
);
Contena.Component.register('sw-product-price-form', () => import('./component/sw-product-price-form'));
Contena.Component.register('sw-product-settings-form', () => import('./component/sw-product-settings-form'));
Contena.Component.register('sw-product-guarantee-form', () => import('./component/sw-product-guarantee-form'));
Contena.Component.register('sw-product-measurement-form', () => import('./component/sw-product-measurement-form'));
Contena.Component.register('sw-product-packaging-form', () => import('./component/sw-product-packaging-form'));
Contena.Component.register('sw-product-seo-form', () => import('./component/sw-product-seo-form'));
Contena.Component.extend(
    'sw-product-visibility-select',
    'sw-entity-multi-select',
    () => import('./component/sw-product-visibility-select'),
);
Contena.Component.register('sw-product-media-form', () => import('./component/sw-product-media-form'));
Contena.Component.register('sw-product-download-form', () => import('./component/sw-product-download-form'));
Contena.Component.register('sw-product-visibility-detail', () => import('./component/sw-product-visibility-detail'));
Contena.Component.register(
    'sw-product-restriction-selection',
    () => import('./component/sw-product-variants/sw-product-variants-configurator/sw-product-restriction-selection'),
);
Contena.Component.extend(
    'sw-product-variants-configurator-selection',
    'sw-property-search',
    () =>
        import(
            './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-selection'
        ),
);
Contena.Component.register(
    'sw-product-variants-configurator-prices',
    () => import('./component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-prices'),
);
Contena.Component.register(
    'sw-product-variants-configurator-restrictions',
    () =>
        import(
            './component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-restrictions'
        ),
);
Contena.Component.register(
    'sw-product-variants-delivery-order',
    () => import('./component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-order'),
);
Contena.Component.register(
    'sw-product-variants-delivery-media',
    () => import('./component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-media'),
);
Contena.Component.register(
    'sw-product-variants-delivery-listing',
    () => import('./component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-listing'),
);
Contena.Component.register(
    'sw-product-variants-overview',
    () => import('./component/sw-product-variants/sw-product-variants-overview'),
);
Contena.Component.register(
    'sw-product-variants-price-field',
    () => import('./component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-price-field'),
);
Contena.Component.extend(
    'sw-product-variants-media-upload',
    'sw-media-upload-v2',
    () => import('./component/sw-product-variants/sw-product-variants-media-upload'),
);
Contena.Component.register('sw-product-cross-selling-form', () => import('./component/sw-product-cross-selling-form'));
Contena.Component.register('sw-product-variant-modal', () => import('./component/sw-product-variant-modal'));
Contena.Component.register(
    'sw-product-cross-selling-assignment',
    () => import('./component/sw-product-cross-selling-assignment'),
);
Contena.Component.register('sw-product-layout-assignment', () => import('./component/sw-product-layout-assignment'));
/**
 * @deprecated tag:v6.8.0 - File will be removed. No longer used.
 */
if (!Contena.Feature.isActive('v6.8.0.0')) {
    Contena.Component.register('sw-product-settings-mode', () => import('./component/sw-product-settings-mode'));
}
Contena.Component.register('sw-product-properties', () => import('./component/sw-product-properties'));
Contena.Component.register('sw-product-add-properties-modal', () => import('./component/sw-product-add-properties-modal'));
Contena.Component.register('sw-product-detail-base', () => import('./view/sw-product-detail-base'));
Contena.Component.register('sw-product-detail-specifications', () => import('./view/sw-product-detail-specifications'));
Contena.Component.register('sw-product-detail-context-prices', () => import('./view/sw-product-detail-context-prices'));
Contena.Component.register('sw-product-detail-variants', () => import('./view/sw-product-detail-variants'));
Contena.Component.register('sw-product-detail-layout', () => import('./view/sw-product-detail-layout'));
Contena.Component.register('sw-product-detail-seo', () => import('./view/sw-product-detail-seo'));
Contena.Component.register('sw-product-detail-cross-selling', () => import('./view/sw-product-detail-cross-selling'));
Contena.Component.register('sw-product-detail-reviews', () => import('./view/sw-product-detail-reviews'));
Contena.Component.register('sw-product-list', () => import('./page/sw-product-list'));
Contena.Component.register('sw-product-detail', () => import('./page/sw-product-detail'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-product', {
    type: 'core',
    name: 'product',
    title: 'sw-product.general.mainMenuItemGeneral',
    description: 'sw-product.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-products',
    favicon: 'icon-module-products.svg',
    entity: 'product',

    routes: {
        index: {
            components: {
                default: 'sw-product-list',
            },
            path: 'index',
            meta: {
                privilege: 'product.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-product-detail',
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
                name: 'sw.product.create.base',
            },
            meta: {
                privilege: 'product.creator',
            },
            children: {
                base: {
                    component: 'sw-product-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.creator',
                    },
                },
            },
        },

        detail: {
            component: 'sw-product-detail',
            path: 'detail/:id?',
            props: {
                default: (route) => ({ productId: route.params.id?.toLowerCase() }),
            },
            redirect: {
                name: 'sw.product.detail.base',
            },
            meta: {
                privilege: 'product.viewer',
                appSystem: {
                    view: 'detail',
                },
                parentPath: 'sw.product.index',
            },
            children: {
                base: {
                    component: 'sw-product-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                specifications: {
                    component: 'sw-product-detail-specifications',
                    path: 'specifications',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                prices: {
                    component: 'sw-product-detail-context-prices',
                    path: 'prices',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                variants: {
                    component: 'sw-product-detail-variants',
                    path: 'variants',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                layout: {
                    component: 'sw-product-detail-layout',
                    path: 'layout',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                seo: {
                    component: 'sw-product-detail-seo',
                    path: 'seo',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                crossSelling: {
                    component: 'sw-product-detail-cross-selling',
                    path: 'cross-selling',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
                    },
                },
                reviews: {
                    component: 'sw-product-detail-reviews',
                    path: 'reviews',
                    meta: {
                        parentPath: 'sw.product.index',
                        privilege: 'product.viewer',
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
            icon: 'regular-products',
            position: 20,
        },
        {
            id: 'sw-product',
            label: 'sw-product.general.mainMenuItemOverview',
            color: 'var(--sw-color-module-green-default)',
            path: 'sw.product.index',
            icon: 'regular-products',
            parent: 'sw-catalogue',
            privilege: 'product.viewer',
            position: 10,
        },
    ],

    defaultSearchConfiguration,
});
