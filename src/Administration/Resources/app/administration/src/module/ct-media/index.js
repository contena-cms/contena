import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import { ADMIN_MENU_ROOTS } from 'src/core/constant/admin-menu.constant';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-media-index', () => import('./page/ct-media-index'));
Contena.Component.register('ct-media-grid', () => import('./component/ct-media-grid'));
Contena.Component.register('ct-media-sidebar', () => import('./component/sidebar/ct-media-sidebar'));
Contena.Component.register(
    'ct-media-quickinfo-metadata-item',
    () => import('./component/sidebar/ct-media-quickinfo-metadata-item'),
);
Contena.Component.register('ct-media-quickinfo-usage', () => import('./component/sidebar/ct-media-quickinfo-usage'));
Contena.Component.register('ct-media-collapse', () => import('./component/ct-media-collapse'));
Contena.Component.register('ct-media-folder-info', () => import('./component/sidebar/ct-media-folder-info'));
Contena.Component.register('ct-media-quickinfo', () => import('./component/sidebar/ct-media-quickinfo'));
Contena.Component.register('ct-media-quickinfo-multiple', () => import('./component/sidebar/ct-media-quickinfo-multiple'));
Contena.Component.register('ct-media-tag', () => import('./component/sidebar/ct-media-tag'));
Contena.Component.register('ct-media-display-options', () => import('./component/ct-media-display-options'));
Contena.Component.register('ct-media-breadcrumbs', () => import('./component/ct-media-breadcrumbs'));
Contena.Component.register('ct-media-library', () => import('./component/ct-media-library'));
Contena.Component.register('ct-media-modal-v2', () => import('./component/ct-media-modal-v2'));

Contena.Component.register('ct-media-save-modal', () => import('./component/ct-media-save-modal'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-media', {
    type: 'core',
    name: 'media',
    title: 'ct-media.general.mainMenuItemGeneral',
    description: 'ct-media.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-pink-500)',
    icon: 'solid-image',
    favicon: 'icon-module-content.png',
    entity: 'media',

    routes: {
        index: {
            components: {
                default: 'ct-media-index',
            },
            path: 'index/:folderId?',
            props: {
                default: (route) => {
                    return {
                        routeFolderId: route.params.folderId || null,
                    };
                },
            },
            meta: {
                privilege: 'media.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'ct-media',
            label: 'ct-media.general.mainMenuItemGeneral',
            color: 'var(--color-pink-500)',
            icon: 'regular-image',
            path: 'ct.media.index',
            parent: ADMIN_MENU_ROOTS.content,
            position: 10,
            privilege: 'media.viewer',
        },
    ],

    defaultSearchConfiguration,
});
