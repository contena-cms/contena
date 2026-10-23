/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-preview-blog-three-column', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-block-blog-three-column', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsBlock({
    name: 'blog-three-column',
    label: 'sw-cms.blocks.commerce.blogThreeColumn.label',
    category: 'commerce',
    component: 'sw-cms-block-blog-three-column',
    previewComponent: 'sw-cms-preview-blog-three-column',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        left: 'blog-box',
        center: 'blog-box',
        right: 'blog-box',
    },
});
