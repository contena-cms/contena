/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/blog-heading', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/blog-heading',
        name: 'blog-heading',
        component: 'sw-cms-block-blog-heading',
        preview: 'sw-cms-preview-blog-heading',
    });

    it('is only allowed on the blog detail page', () => {
        const blockConfig = Contena.Service('cmsService').getCmsBlockConfigByName('blog-heading');

        expect(blockConfig.allowedPageTypes).toEqual(['blog_detail']);
    });
});
