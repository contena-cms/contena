/**
 * @sw-package framework
 */

import { createRouter as createRouterVue, createWebHistory } from 'vue-router';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function createRouter() {
    return createRouterVue({
        routes: [
            {
                name: 'index',
                path: '/',
                component: {},
            },
            {
                name: 'sw.blog.detail',
                path: '/sw/blog/detail/:{id}',
                component: {},
                meta: {
                    $module: {
                        entity: 'blog',
                    },
                    appSystem: {
                        view: 'detail',
                    },
                },
            },
            {
                name: 'sw.blog.list',
                path: '/sw/blog/list',
                component: {},
                meta: {
                    $module: {
                        entity: 'blog',
                    },
                    appSystem: {
                        view: 'list',
                    },
                },
            },
            {
                name: 'sw.order.detail',
                path: '/sw/order/detail',
                component: {},
                meta: {
                    $module: {
                        entity: 'order',
                    },
                    appSystem: {
                        view: 'list',
                    },
                },
            },
            {
                name: 'sw.settings.index',
                path: '/sw/setting/index',
                component: {},
                meta: {
                    $module: {},
                },
            },
        ],
        history: createWebHistory(),
    });
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const actionButtonData = [
    {
        id: Contena.Utils.createId(),
        action: 'addProduct',
        app: 'TestApp',
        icon: 'someBase64Icon',
        label: {
            'de-DE': 'Blog hinzufügen',
            'en-GB': 'Add blog',
        },
        url: 'http://test-url/actions/blog/add',
    },
    {
        id: Contena.Utils.createId(),
        action: 'renameProduct',
        app: 'TestApp',
        icon: 'someBase64Icon',
        label: {
            'de-DE': 'Blog umbenennen',
            'en-GB': 'Rename blog',
        },
        url: 'http://test-url/actions/blog/rename',
    },
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const actionResultData = {
    data: {
        actionType: 'notification',
        status: 'success',
        message: 'This is successful',
    },
};
