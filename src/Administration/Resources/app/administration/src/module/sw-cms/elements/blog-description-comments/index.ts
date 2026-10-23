/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-preview-blog-description-comments', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-config-blog-description-comments', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-blog-description-comments', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsElement({
    name: 'blog-description-comments',
    label: 'sw-cms.elements.blogDescriptionComments.label',
    component: 'sw-cms-el-blog-description-comments',
    configComponent: 'sw-cms-el-config-blog-description-comments',
    previewComponent: 'sw-cms-el-preview-blog-description-comments',
    disabledConfigInfoTextKey: 'sw-cms.elements.blogDescriptionComments.infoText.descriptionAndReviewsElement',
    defaultConfig: {
        blog: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'blog',
                criteria: new Contena.Data.Criteria(1, 25).addAssociation('properties'),
            },
        },
        alignment: {
            source: 'static',
            value: null,
        },
    },
    collect: Contena.Service('cmsService').getCollectFunction(),
});
