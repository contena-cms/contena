/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-preview-blog-listing', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-config-blog-listing', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-el-blog-listing', () => import('./component'));
/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register(
    'sw-cms-el-config-blog-listing-config-sorting-grid',
    () => import('./config/components/sw-cms-el-config-blog-listing-config-sorting-grid'),
);

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsElement({
    name: 'blog-listing',
    label: 'sw-cms.elements.blogListing.label',
    hidden: true,
    removable: false,
    component: 'sw-cms-el-blog-listing',
    previewComponent: 'sw-cms-el-preview-blog-listing',
    configComponent: 'sw-cms-el-config-blog-listing',
    defaultConfig: {
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        boxHeadlineLevel: {
            source: 'static',
            value: 2,
        },
        showSorting: {
            source: 'static',
            value: true,
        },
        useCustomSorting: {
            source: 'static',
            value: false,
        },
        availableSortings: {
            source: 'static',
            value: {},
        },
        defaultSorting: {
            source: 'static',
            value: '',
        },
        filters: {
            source: 'static',
            value: 'manufacturer-filter,rating-filter,price-filter,shipping-free-filter,property-filter',
        },
        // eslint-disable-next-line inclusive-language/use-inclusive-words
        propertyWhitelist: {
            source: 'static',
            value: [],
        },
    },
});
