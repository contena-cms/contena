/**
 * @sw-package framework
 */
import './init/services.init';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-bulk-edit-product', () => import('./page/sw-bulk-edit-product'));
Contena.Component.register('sw-bulk-edit-order', () => import('./page/sw-bulk-edit-order'));
Contena.Component.register('sw-bulk-edit-customer', () => import('./page/sw-bulk-edit-customer'));
Contena.Component.register(
    'sw-bulk-edit-order-documents',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents'),
);
Contena.Component.register(
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-invoice'),
);
Contena.Component.extend(
    'sw-bulk-edit-order-documents-generate-cancellation-invoice',
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-cancellation-invoice'),
);
Contena.Component.extend(
    'sw-bulk-edit-order-documents-generate-delivery-note',
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-delivery-note'),
);
Contena.Component.extend(
    'sw-bulk-edit-order-documents-generate-credit-note',
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-credit-note'),
);
Contena.Component.register(
    'sw-bulk-edit-order-documents-download-documents',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-download-documents'),
);
Contena.Component.register(
    'sw-bulk-edit-order-documents-delete-documents',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-delete-documents'),
);
Contena.Component.extend(
    'sw-bulk-edit-custom-fields',
    'sw-custom-field-set-renderer',
    () => import('./component/sw-bulk-edit-custom-fields'),
);
Contena.Component.register('sw-bulk-edit-change-type', () => import('./component/sw-bulk-edit-change-type'));
Contena.Component.register(
    'sw-bulk-edit-change-type-field-renderer',
    () => import('./component/sw-bulk-edit-change-type-field-renderer'),
);
Contena.Component.extend(
    'sw-bulk-edit-form-field-renderer',
    'sw-form-field-renderer',
    () => import('./component/sw-bulk-edit-form-field-renderer'),
);
Contena.Component.register(
    'sw-bulk-edit-product-visibility',
    () => import('./component/product/sw-bulk-edit-product-visibility'),
);
Contena.Component.register('sw-bulk-edit-product-media', () => import('./component/product/sw-bulk-edit-product-media'));
Contena.Component.extend(
    'sw-bulk-edit-product-media-form',
    'sw-product-media-form',
    () => import('./component/product/sw-bulk-edit-product-media-form'),
);
Contena.Component.extend(
    'sw-bulk-edit-product-description',
    'sw-text-editor',
    () => import('./component/product/sw-bulk-edit-product-description'),
);
Contena.Component.register('sw-bulk-edit-save-modal', () => import('./component/sw-bulk-edit-save-modal'));
Contena.Component.register('sw-bulk-edit-save-modal-confirm', () => import('./component/sw-bulk-edit-save-modal-confirm'));
Contena.Component.register('sw-bulk-edit-save-modal-process', () => import('./component/sw-bulk-edit-save-modal-process'));
Contena.Component.register('sw-bulk-edit-save-modal-success', () => import('./component/sw-bulk-edit-save-modal-success'));
Contena.Component.register(
    'sw-bulk-edit-document-generation-failed-list',
    () => import('./component/sw-bulk-edit-document-generation-failed-list'),
);
Contena.Component.register('sw-bulk-edit-save-modal-error', () => import('./component/sw-bulk-edit-save-modal-error'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Contena;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-bulk-edit', {
    type: 'core',
    name: 'bulk-edit',
    title: 'global.default.bulkEdit',
    description: 'sw-bulk-edit.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',

    routes: {
        product: {
            component: 'sw-bulk-edit-product',
            path: 'product/:parentId/:includesDigital',
            meta: {
                parentPath: 'sw.product.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.product.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },
        order: {
            component: 'sw-bulk-edit-order',
            path: 'order/:excludeDelivery',
            meta: {
                parentPath: 'sw.order.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.order.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },

        customer: {
            component: 'sw-bulk-edit-customer',
            path: 'customer',
            meta: {
                parentPath: 'sw.customer.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.customer.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },
    },
});
