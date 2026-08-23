import initState from './store';
import './mixin/ct-extension-error.mixin';
import './service';
import './acl';

initState(Contena);

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-extension-config', () => import('./page/ct-extension-config'));
Contena.Component.register('ct-extension-my-extensions-listing', () => import('./page/ct-extension-my-extensions-listing'));
Contena.Component.register('ct-extension-my-extensions-index', () => import('./page/ct-extension-my-extensions-index'));
Contena.Component.register('ct-extension-store-landing-page', () => import('./page/ct-extension-store-landing-page'));
Contena.Component.register('ct-extension-file-upload', () => import('./component/ct-extension-file-upload'));
Contena.Component.register('ct-extension-card-base', () => import('./component/ct-extension-card-base'));
Contena.Component.register(
    'ct-extension-my-extensions-listing-controls',
    () => import('./component/ct-extension-my-extensions-listing-controls'),
);
Contena.Component.register('ct-extension-removal-modal', () => import('./component/ct-extension-removal-modal'));
Contena.Component.register('ct-extension-uninstall-modal', () => import('./component/ct-extension-uninstall-modal'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

/**
 * @private
 */
Contena.Module.register('ct-extension', {
    type: 'core',
    title: 'ct-extension.mainMenu.plugins',
    color: '#189EFF',
    icon: 'regular-plug',
    version: '1.0.0',
    targetVersion: '1.0.0',
    entity: 'extension',

    searchMatcher: (regex, labelType, manifest) => {
        const match = labelType.toLowerCase().match(regex);

        if (!match) {
            return false;
        }

        return [
            {
                icon: manifest.icon,
                color: manifest.color,
                label: labelType,
                entity: manifest.entity,
                route: manifest.routes['my-extensions'],
                privilege: manifest.routes.index?.meta.privilege,
            },
        ];
    },

    routes: {
        'my-extensions': {
            path: 'my-extensions',
            component: 'ct-extension-my-extensions-index',
            redirect: {
                name: 'ct.extension.my-extensions.listing',
            },
            meta: {
                privilege: 'system.plugin_maintain',
            },
            children: {
                listing: {
                    path: 'listing',
                    component: 'ct-extension-my-extensions-listing',
                    meta: {
                        privilege: 'system.plugin_maintain',
                    },
                },
            },
        },
        config: {
            component: 'ct-extension-config',
            path: 'config/:namespace',
            beforeEnter(to, from) {
                to.meta.fromLink = from;
            },
            meta: {
                parentPath: 'ct.extension.my-extensions',
                privilege: 'system.plugin_maintain',
            },

            props: {
                default(route) {
                    return {
                        namespace: route.params.namespace,
                        fromLink: route.meta.fromLink ?? null,
                    };
                },
            },
        },
        store: {
            path: 'store',
            component: 'ct-extension-store-landing-page',
            redirect: {
                name: 'ct.extension.store.landing-page',
            },
        },
        'store.landing-page': {
            path: 'store/landing-page',
            component: 'ct-extension-store-landing-page',
        },
    },

    navigation: [
        {
            id: 'ct-plugins',
            label: 'ct-extension.mainMenu.plugins',
            color: '#189EFF',
            icon: 'regular-plug',
            position: 80,
        },
        {
            id: 'ct-my-plugins',
            label: 'ct-extension.mainMenu.purchased',
            path: 'ct.extension.my-extensions',
            parent: 'ct-plugins',
            privilege: 'system.plugin_maintain',
            color: '#189EFF',
            icon: 'regular-plug',
            position: 10,
        },
        {
            id: 'ct-plugins-store',
            label: 'ct-extension.mainMenu.store',
            path: 'ct.extension.store',
            parent: 'ct-plugins',
            privilege: 'system.extension_store',
            color: '#189EFF',
            icon: 'regular-storefront',
            position: 20,
        },
    ],
});
