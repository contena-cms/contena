/**
 * @ct-package discovery
 */
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Contena;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-landing-page', {
    type: 'core',
    name: 'landing_page',
    title: 'ct-landing-page.general.mainMenuItemIndex',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'regular-file',
    favicon: 'icon-module-content.png',
    entity: 'landing_page',

    routes: {
        index: {
            component: 'ct-category-detail',
            path: 'index',
            redirect: {
                name: 'ct.category.detail.base',
            },
        },
    },

    defaultSearchConfiguration,
});
