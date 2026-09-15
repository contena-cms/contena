import './acl';

const { Module } = Contena;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Component.register('ct-integration-list', () => import('./page/ct-integration-list'));
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Component.register('ct-oauth-client-list', () => import('./page/ct-oauth-client-list'));

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Component.register('ct-integration-mcp-allowlist', () => import('./component/ct-integration-mcp-allowlist'));

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-integration', {
    type: 'core',
    name: 'integration',
    title: 'ct-integration.general.mainMenuItemIndex',
    description: 'The module for managing integrations.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--ct-color-module-neutral-default)',
    icon: 'regular-key',
    favicon: 'icon-module-settings.png',
    entity: 'integration',

    routes: {
        index: {
            component: 'ct-integration-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index.system',
                privilege: 'integration.viewer',
            },
        },
        oauthClients: {
            component: 'ct-oauth-client-list',
            path: 'oauth-clients',
            meta: {
                parentPath: 'ct.settings.index.system',
                privilege: 'oauth_client.viewer',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'ct.integration.index',
        icon: 'regular-key',
        privilege: 'integration.viewer',
    },
});
