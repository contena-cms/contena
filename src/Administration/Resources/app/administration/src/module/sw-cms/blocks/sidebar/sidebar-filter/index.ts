/**
 * @private
 * @sw-package discovery
 */
Contena.Component.register('sw-cms-preview-sidebar-filter', () => import('./preview'));
/**
 * @private
 */
Contena.Component.register('sw-cms-block-sidebar-filter', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Contena.Service('cmsService').registerCmsBlock({
    name: 'sidebar-filter',
    label: 'sw-cms.blocks.sidebar.sidebarFilter.label',
    category: 'sidebar',
    component: 'sw-cms-block-sidebar-filter',
    previewComponent: 'sw-cms-preview-sidebar-filter',
    allowedPageTypes: [Contena.Constants.CMS.PAGE_TYPES.LISTING],
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'sidebar-filter',
    },
});
