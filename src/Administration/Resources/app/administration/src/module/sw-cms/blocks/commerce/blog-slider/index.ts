/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-preview-blog-slider', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-block-blog-slider', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsBlock({
    name: 'blog-slider',
    label: 'sw-cms.blocks.commerce.blogSlider.label',
    category: 'commerce',
    component: 'sw-cms-block-blog-slider',
    previewComponent: 'sw-cms-preview-blog-slider',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        blogSlider: 'blog-slider',
    },
});
