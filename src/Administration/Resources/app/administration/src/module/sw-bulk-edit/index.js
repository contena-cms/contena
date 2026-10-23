/**
 * @sw-package framework
 */
import './init/services.init';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-bulk-edit-blog', () => import('./page/sw-bulk-edit-blog'));
Contena.Component.register('sw-bulk-edit-order', () => import('./page/sw-bulk-edit-order'));
Contena.Component.register('sw-bulk-edit-user', () => import('./page/sw-bulk-edit-user'));
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
    'sw-bulk-edit-blog-visibility',
    () => import('./component/blog/sw-bulk-edit-blog-visibility'),
);
Contena.Component.register('sw-bulk-edit-blog-media', () => import('./component/blog/sw-bulk-edit-blog-media'));
Contena.Component.extend(
    'sw-bulk-edit-blog-media-form',
    'sw-blog-media-form',
    () => import('./component/blog/sw-bulk-edit-blog-media-form'),
);
Contena.Component.extend(
    'sw-bulk-edit-blog-description',
    'sw-text-editor',
    () => import('./component/blog/sw-bulk-edit-blog-description'),
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
        blog: {
            component: 'sw-bulk-edit-blog',
            path: 'blog/:parentId/:includesDigital',
            meta: {
                parentPath: 'sw.blog.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.blog.save.confirm',
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

        user: {
            component: 'sw-bulk-edit-user',
            path: 'user',
            meta: {
                parentPath: 'sw.user.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.user.save.confirm',
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
