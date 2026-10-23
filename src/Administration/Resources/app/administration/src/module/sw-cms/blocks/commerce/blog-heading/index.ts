/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-preview-blog-heading', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-block-blog-heading', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsBlock({
    name: 'blog-heading',
    label: 'sw-cms.blocks.commerce.blogHeading.label',
    category: 'commerce',
    component: 'sw-cms-block-blog-heading',
    previewComponent: 'sw-cms-preview-blog-heading',
    allowedPageTypes: [Contena.Constants.CMS.PAGE_TYPES.BLOG_DETAIL],
    defaultConfig: {
        marginTop: '20px',
        marginLeft: null,
        marginBottom: '20px',
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        left: 'blog-name',
        right: 'manufacturer-logo',
    },
});
