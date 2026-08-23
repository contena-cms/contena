import './acl';
import { ADMIN_MENU_ROOTS } from 'src/core/constant/admin-menu.constant';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-data-dictionary-list', () => import('./page/ct-data-dictionary-list'));
Contena.Component.register('ct-data-dictionary-detail', () => import('./page/ct-data-dictionary-detail'));
Contena.Component.register('ct-data-dictionary-item-modal', () => import('./component/ct-data-dictionary-item-modal'));
Contena.Component.register('ct-data-dictionary-tree', () => import('./component/ct-data-dictionary-tree'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-data-dictionary', {
    type: 'core',
    name: 'data-dictionary',
    title: 'ct-data-dictionary.general.mainMenuItemGeneral',
    description: 'ct-data-dictionary.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-bars-square',
    favicon: 'icon-module-settings.png',
    entity: 'data_dictionary',

    routes: {
        index: {
            component: 'ct-data-dictionary-list',
            path: 'index',
            meta: {
                privilege: 'data_dictionary.viewer',
            },
        },
        detail: {
            component: 'ct-data-dictionary-detail',
            path: 'detail/:id?',
            meta: {
                parentPath: 'ct.data.dictionary.index',
                privilege: 'data_dictionary.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'ct-data-dictionary',
            label: 'ct-data-dictionary.general.mainMenuItemGeneral',
            path: 'ct.data.dictionary.index',
            parent: ADMIN_MENU_ROOTS.system,
            icon: 'regular-bars-square',
            position: 30,
            privilege: 'data_dictionary.viewer',
        },
    ],
});
