/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-preview-blog-listing', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-block-blog-listing', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsBlock({
    name: 'blog-listing',
    label: 'sw-cms.blocks.commerce.blogListing.label',
    category: 'commerce',
    hidden: true,
    removable: false,
    component: 'sw-cms-block-blog-listing',
    previewComponent: 'sw-cms-preview-blog-listing',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'blog-listing',
    },
});
