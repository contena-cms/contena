/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-preview-blog-box', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-config-blog-box', () => import('./config'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-blog-box', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsElement({
    name: 'blog-box',
    label: 'sw-cms.elements.blogBox.label',
    component: 'sw-cms-el-blog-box',
    previewComponent: 'sw-cms-el-preview-blog-box',
    configComponent: 'sw-cms-el-config-blog-box',
    defaultConfig: {
        blog: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'blog',
                criteria: new Contena.Data.Criteria(1, 25).addAssociation('cover'),
            },
        },
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
    },
    defaultData: {
        boxLayout: 'standard',
        blog: null,
    },
    collect: Contena.Service('cmsService').getCollectFunction(),
});
