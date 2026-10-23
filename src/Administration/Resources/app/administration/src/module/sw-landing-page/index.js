/**
 * @sw-package discovery
 */
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Contena;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-landing-page', {
    type: 'core',
    name: 'landing_page',
    title: 'sw-landing-page.general.mainMenuItemIndex',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-blogs',
    favicon: 'icon-module-blogs.svg',
    entity: 'landing_page',

    routes: {
        index: {
            component: 'sw-category-detail',
            path: 'index',
            redirect: {
                name: 'sw.category.detail.base',
            },
        },
    },

    defaultSearchConfiguration,
});
