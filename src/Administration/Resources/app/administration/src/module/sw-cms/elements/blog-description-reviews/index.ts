/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-preview-blog-description-reviews', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-config-blog-description-reviews', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-blog-description-reviews', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsElement({
    name: 'blog-description-reviews',
    label: 'sw-cms.elements.blogDescriptionReviews.label',
    component: 'sw-cms-el-blog-description-reviews',
    configComponent: 'sw-cms-el-config-blog-description-reviews',
    previewComponent: 'sw-cms-el-preview-blog-description-reviews',
    disabledConfigInfoTextKey: 'sw-cms.elements.blogDescriptionReviews.infoText.descriptionAndReviewsElement',
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
