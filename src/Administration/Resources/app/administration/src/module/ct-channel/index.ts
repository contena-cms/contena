import './acl';
import './service/domain-link.service';
import './service/channel-favorites.service';
import channelAdminMenuExtension from './component/structure/ct-admin-menu-extension/ct-admin-menu.override.vue';
import defaultSearchConfiguration from './default-search-configuration';

/** @private */
Contena.Component.register('ct-channel-list', () => import('./page/ct-channel-list'));
/** @private */
Contena.Component.register('ct-channel-detail', () => import('./page/ct-channel-detail'));
/** @private */
Contena.Component.register('ct-channel-create', () => import('./page/ct-channel-create'));
/** @private */
Contena.Component.register('ct-channel-detail-base', () => import('./view/ct-channel-detail-base'));
/** @private */
Contena.Component.register('ct-channel-create-base', () => import('./view/ct-channel-create-base'));
/** @private */
Contena.Component.register('ct-channel-detail-domains', () => import('./component/ct-channel-detail-domains'));
/** @private */
Contena.Component.register('ct-channel-defaults-select', () => import('./component/ct-channel-defaults-select'));
/** @private */
Contena.Component.register('ct-channel-detail-hreflang', () => import('./component/ct-channel-detail-hreflang'));
/** @private */
Contena.Component.register('ct-channel-modal', () => import('./component/ct-channel-modal'));
/** @private */
Contena.Component.register('ct-channel-modal-grid', () => import('./component/ct-channel-modal-grid'));
/** @private */
Contena.Component.register('ct-channel-modal-detail', () => import('./component/ct-channel-modal-detail'));
/** @private */
Contena.Component.register('ct-channel-menu', () => import('./component/structure/ct-channel-menu'));

Contena.Component.registerOverrideComponent(channelAdminMenuExtension);

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-channel', {
    type: 'core',
    name: 'channel',
    title: 'ct-channel.general.titleMenuItems',
    description: 'ct-channel.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-module-green-500)',
    icon: 'regular-server',
    entity: 'channel',

    searchMatcher: (regex, labelType, manifest) => {
        const match = labelType.toLowerCase().match(regex);
        if (!match) {
            return false;
        }

        return [
            {
                name: manifest.name,
                icon: manifest.icon,
                color: manifest.color,
                label: labelType,
                entity: manifest.entity,
                route: manifest.routes.list,
                privilege: (manifest.routes.list?.meta as { privilege?: string } | undefined)?.privilege,
            },
        ];
    },

    routes: {
        list: {
            component: 'ct-channel-list',
            path: 'list',
            meta: { privilege: 'channel.viewer' },
        },
        create: {
            component: 'ct-channel-create',
            path: 'create/:typeId/:id?',
            redirect: { name: 'ct.channel.create.base' },
            children: {
                base: {
                    component: 'ct-channel-create-base',
                    path: 'base',
                    meta: {
                        parentPath: 'ct.channel.list',
                        privilege: 'channel.creator',
                    },
                },
            },
        },
        detail: {
            component: 'ct-channel-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'ct.channel.list',
                privilege: 'channel.viewer',
            },
            redirect: { name: 'ct.channel.detail.base' },
            children: {
                base: {
                    component: 'ct-channel-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'ct.channel.list',
                        privilege: 'channel.viewer',
                    },
                },
            },
        },
    },

    navigation: [],

    defaultSearchConfiguration,
});
