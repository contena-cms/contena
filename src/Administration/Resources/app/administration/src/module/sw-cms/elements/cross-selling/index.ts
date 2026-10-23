/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-preview-cross-selling', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-config-cross-selling', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-cross-selling', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsElement({
    name: 'cross-selling',
    label: 'sw-cms.elements.crossSelling.label',
    component: 'sw-cms-el-cross-selling',
    configComponent: 'sw-cms-el-config-cross-selling',
    previewComponent: 'sw-cms-el-preview-cross-selling',
    defaultConfig: {
        blog: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'blog',
                criteria: new Contena.Data.Criteria(1, 25).addAssociation('crossSellings.assignedProducts.blog'),
            },
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        elMinWidth: {
            source: 'static',
            value: '300px',
        },
        speed: {
            source: 'static',
            value: 300,
        },
    },
    collect: Contena.Service('cmsService').getCollectFunction(),
});
