/**
 * @sw-package discovery
 */
const defaultPageTypes = [
    {
        name: 'page',
        icon: 'regular-lightbulb',
    },
    {
        name: 'landingpage',
        icon: 'regular-dashboard',
    },
    {
        name: 'blog_list',
        icon: 'regular-shopping-basket',
    },
    {
        name: 'blog_detail',
        icon: 'regular-tag',
    },
];

/**
 * @private
 */
export default () => {
    const pageTypeService = Contena.Service().get('cmsPageTypeService');

    defaultPageTypes.forEach((type: { name: string; icon: string }) => {
        pageTypeService.register(type);
    });
};
