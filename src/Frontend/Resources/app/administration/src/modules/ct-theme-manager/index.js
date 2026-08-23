import './acl';

const { Module } = Contena;

/** @private */
Contena.Component.register('ct-theme-manager-detail', () => import('./page/ct-theme-manager-detail'));

/** @private */
Contena.Component.register('ct-theme-manager-list', () => import('./page/ct-theme-manager-list'));

/** @private */
Contena.Component.register('ct-theme-list-item', () => import('./component/ct-theme-list-item'));

/** @private */
Contena.Component.register('ct-theme-modal', () => import('./component/ct-theme-modal'));

/** @private */
Contena.Component.register('ct-channel-detail-theme', () => import('./extension/ct-channel-detail-theme'));

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-theme-manager', {
    type: 'core',
    title: 'ct-theme-manager.general.mainMenuItemGeneral',
    description: 'ct-theme-manager.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ff68b4',
    icon: 'regular-content',
    favicon: 'icon-module-content.png',
    entity: 'theme',

    routes: {
        index: {
            component: 'ct-theme-manager-list',
            path: 'index',
            meta: {
                privilege: 'theme.viewer',
            },
        },
        detail: {
            component: 'ct-theme-manager-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'ct.theme.manager.index',
                privilege: 'theme.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'ct-theme-manager',
            label: 'ct-theme-manager.general.mainMenuItemGeneral',
            color: '#ff68b4',
            icon: 'default-object-image',
            path: 'ct.theme.manager.index',
            privilege: 'theme.viewer',
            position: 80,
            parent: 'ct-content',
        },
    ],

    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'ct.channel.detail') {
            const routes = [
                {
                    component: 'ct-channel-detail-theme',
                    name: 'ct.channel.detail.theme',
                    path: '/sw/channel/detail/:id/theme',
                },
                {
                    component: 'ct-channel-detail-content-layouts',
                    name: 'ct.channel.detail.contentLayouts',
                    path: '/sw/channel/detail/:id/content-layouts',
                },
            ];

            routes.forEach((route) => {
                if (currentRoute.children.some((child) => child.name === route.name)) {
                    return;
                }

                currentRoute.children.push({
                    ...route,
                    isChildren: true,
                    meta: {
                        parentPath: 'ct.channel.list',
                        privilege: 'channel.viewer',
                    },
                });
            });
        }

        next(currentRoute);
    },
});
