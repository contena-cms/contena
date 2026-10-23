import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-blog-stream-list', () => import('./page/sw-blog-stream-list'));
Contena.Component.register('sw-blog-stream-detail', () => import('./page/sw-blog-stream-detail'));
Contena.Component.register('sw-blog-stream-field-select', () => import('./component/sw-blog-stream-field-select'));
Contena.Component.register('sw-blog-stream-value', () => import('./component/sw-blog-stream-value'));
Contena.Component.register('sw-blog-stream-modal-preview', () => import('./component/sw-blog-stream-modal-preview'));
Contena.Component.extend(
    'sw-blog-stream-filter',
    'sw-condition-base',
    () => import('./component/sw-blog-stream-filter'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @sw-package inventory
 * @private
 */
Module.register('sw-blog-stream', {
    type: 'core',
    name: 'blog-stream',
    title: 'sw-blog-stream.general.mainMenuItemGeneral',
    description: 'sw-blog-stream.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-layer-group',
    favicon: 'icon-module-blogs.svg',
    entity: 'content_stream',

    routes: {
        index: {
            components: {
                default: 'sw-blog-stream-list',
            },
            path: 'index',
            meta: {
                privilege: 'content_stream.viewer',
            },
        },
        create: {
            component: 'sw-blog-stream-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.blog.stream.index',
                privilege: 'content_stream.viewer',
            },
        },
        detail: {
            component: 'sw-blog-stream-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.blog.stream.index',
                privilege: 'content_stream.viewer',
            },
            props: {
                default(route) {
                    return { contentStreamId: route.params.id.toLowerCase() };
                },
            },
        },
    },

    navigation: [
        {
            path: 'sw.blog.stream.index',
            label: 'sw-blog-stream.general.mainMenuItemGeneral',
            id: 'sw-blog-stream',
            privilege: 'content_stream.viewer',
            parent: 'sw-catalogue',
            color: 'var(--sw-color-module-green-default)',
            position: 30,
        },
    ],

    defaultSearchConfiguration,
});
