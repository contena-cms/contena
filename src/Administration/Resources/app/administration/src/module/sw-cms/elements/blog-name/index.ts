Contena.Component.extend('sw-cms-el-blog-name', 'sw-cms-el-text', () => import('./component'));
Contena.Component.extend('sw-cms-el-config-blog-name', 'sw-cms-el-config-text', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-preview-blog-name', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsElement({
    name: 'blog-name',
    label: 'sw-cms.elements.blogHeading.name.label',
    component: 'sw-cms-el-blog-name',
    configComponent: 'sw-cms-el-config-blog-name',
    previewComponent: 'sw-cms-el-preview-blog-name',
    allowedPageTypes: [Contena.Constants.CMS.PAGE_TYPES.BLOG_DETAIL],
    defaultConfig: {
        content: {
            source: 'static',
            value: '<h2>Lorem ipsum dolor sit amet.</h2>',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
    },
});
