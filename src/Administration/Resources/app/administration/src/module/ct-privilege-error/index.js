// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Component.register('ct-privilege-error', () => import('./page/ct-privilege-error'));

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-privilege-error', {
    type: 'core',
    name: 'privilege',
    title: 'ct-privilege-error.general.mainMenuItemGeneral',
    description: 'ct-privilege-error.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',

    routes: {
        index: {
            components: {
                default: 'ct-privilege-error',
            },
            path: 'index',
        },
    },
});
