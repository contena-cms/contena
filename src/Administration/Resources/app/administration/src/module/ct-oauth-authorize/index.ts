// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Component.register('ct-oauth-authorize-index', () => import('./page/ct-oauth-authorize-index'));

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-oauth-authorize', {
    type: 'core',
    name: 'oauth-authorize',
    title: 'ct-oauth-authorize.general.title',
    description: 'ct-oauth-authorize.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#189EFF',
    routes: {
        index: {
            coreRoute: true,
            component: 'ct-oauth-authorize-index',
            path: '/oauth/authorize',
        },
    },
});
