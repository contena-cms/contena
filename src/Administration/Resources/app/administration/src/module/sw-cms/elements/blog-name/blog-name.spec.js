/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/blog-name', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/blog-name',
        name: 'blog-name',
        component: 'sw-cms-el-blog-name',
        config: 'sw-cms-el-config-blog-name',
        preview: 'sw-cms-el-preview-blog-name',
    });

    it('is only allowed on the blog detail page', () => {
        const elementConfig = Contena.Service('cmsService').getCmsElementConfigByName('blog-name');

        expect(elementConfig.allowedPageTypes).toEqual(['blog_detail']);
    });
});
