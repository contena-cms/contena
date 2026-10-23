/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-preview-blog-description-reviews', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-block-blog-description-reviews', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsBlock({
    name: 'blog-description-reviews',
    label: 'sw-cms.blocks.commerce.blogDescriptionReviews.label',
    category: 'commerce',
    component: 'sw-cms-block-blog-description-reviews',
    previewComponent: 'sw-cms-preview-blog-description-reviews',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'blog-description-reviews',
    },
});
