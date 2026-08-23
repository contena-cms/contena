import './acl';
import './page/ct-blog-detail/store';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import defaultSearchConfiguration from './default-search-configuration';

/**
 * @private
 */
Contena.Component.register('ct-blog-list', () => import('./page/ct-blog-list'));

/**
 * @private
 */
Contena.Component.register('ct-blog-detail', () => import('./page/ct-blog-detail'));

/**
 * @private
 */
Contena.Component.register('ct-blog-detail-base', () => import('./view/ct-blog-detail-base'));

/**
 * @private
 */
Contena.Component.register('ct-blog-detail-layout', () => import('./view/ct-blog-detail-layout'));

/**
 * @private
 */
Contena.Component.register('ct-blog-basic-form', () => import('./component/ct-blog-basic-form'));

/**
 * @private
 */
Contena.Component.register('ct-blog-category-form', () => import('./component/ct-blog-category-form'));

/**
 * @private
 */
Contena.Component.register('ct-blog-visibility-select', () => import('./component/ct-blog-visibility-select'));

/**
 * @private
 */
Contena.Component.register('ct-blog-visibility-detail', () => import('./component/ct-blog-visibility-detail'));

/**
 * @private
 */
Contena.Component.register('ct-blog-media-form', () => import('./component/ct-blog-media-form'));

/**
 * @private
 */
Contena.Component.register('ct-blog-seo-form', () => import('./component/ct-blog-seo-form'));

/**
 * @private
 */
Contena.Component.register('ct-blog-detail-seo', () => import('./view/ct-blog-detail-seo'));

/**
 * @private
 */
Contena.Module.register('ct-blog', {
    type: 'core',
    name: 'blog',
    title: 'ct-blog.general.mainMenuItemGeneral',
    description: 'ct-blog.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-module-green-500)',
    icon: 'regular-file-text',
    favicon: 'icon-module-content.png',
    entity: 'blog',

    routes: {
        index: {
            component: 'ct-blog-list',
            path: 'index',
            meta: { privilege: 'blog.viewer' },
        },
        create: {
            component: 'ct-blog-detail',
            path: 'create',
            props: {
                default: (route: RouteLocationNormalizedLoaded) => ({
                    createMode: true,
                    creationType: route.query.creationType ?? 'post',
                }),
            },
            redirect: { name: 'ct.blog.create.base' },
            meta: { privilege: 'blog.creator' },
            children: {
                base: {
                    component: 'ct-blog-detail-base',
                    path: 'base',
                    meta: { parentPath: 'ct.blog.index', privilege: 'blog.creator' },
                },
            },
        },
        detail: {
            component: 'ct-blog-detail',
            path: 'detail/:id',
            redirect: { name: 'ct.blog.detail.base' },
            meta: { parentPath: 'ct.blog.index', privilege: 'blog.viewer' },
            children: {
                base: {
                    component: 'ct-blog-detail-base',
                    path: 'base',
                    meta: { parentPath: 'ct.blog.index', privilege: 'blog.viewer' },
                },
                layout: {
                    component: 'ct-blog-detail-layout',
                    path: 'layout',
                    meta: { parentPath: 'ct.blog.index', privilege: 'blog.viewer' },
                },
                seo: {
                    component: 'ct-blog-detail-seo',
                    path: 'seo',
                    meta: { parentPath: 'ct.blog.index', privilege: 'blog.viewer' },
                },
            },
        },
    },

    navigation: [
        {
            id: 'ct-blog',
            label: 'ct-blog.general.mainMenuItemGeneral',
            color: 'var(--color-module-green-500)',
            path: 'ct.blog.index',
            icon: 'regular-file-text',
            parent: 'ct-content',
            privilege: 'blog.viewer',
            position: 10,
        },
    ],

    defaultSearchConfiguration,
});
