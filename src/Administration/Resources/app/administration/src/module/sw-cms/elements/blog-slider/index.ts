/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-preview-blog-slider', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-config-blog-slider', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-blog-slider', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsElement({
    name: 'blog-slider',
    label: 'sw-cms.elements.blogSlider.label',
    component: 'sw-cms-el-blog-slider',
    configComponent: 'sw-cms-el-config-blog-slider',
    previewComponent: 'sw-cms-el-preview-blog-slider',
    defaultConfig: {
        blogs: {
            source: 'static',
            value: [],
            required: true,
            entity: {
                name: 'blog',
                criteria: new Contena.Data.Criteria(1, 25).addAssociation('cover'),
            },
        },
        title: {
            source: 'static',
            value: '',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        navigationArrows: {
            source: 'static',
            value: 'outside',
        },
        rotate: {
            source: 'static',
            value: false,
        },
        autoplayTimeout: {
            source: 'static',
            value: 5000,
        },
        speed: {
            source: 'static',
            value: 300,
        },
        border: {
            source: 'static',
            value: false,
        },
        elMinWidth: {
            source: 'static',
            value: '300px',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
        blogStreamSorting: {
            source: 'static',
            value: 'name:ASC',
        },
        blogStreamLimit: {
            source: 'static',
            value: 10,
        },
    },
    collect: Contena.Service('cmsService').getCollectFunction(),
});
