/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-preview-blog-description-comments', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-block-blog-description-comments', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsBlock({
    name: 'blog-description-comments',
    label: 'sw-cms.blocks.commerce.blogDescriptionComments.label',
    category: 'commerce',
    component: 'sw-cms-block-blog-description-comments',
    previewComponent: 'sw-cms-preview-blog-description-comments',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'blog-description-comments',
    },
});
