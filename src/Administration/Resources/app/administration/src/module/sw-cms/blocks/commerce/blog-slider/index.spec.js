/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/blog-slider', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/blog-slider',
        name: 'blog-slider',
        component: 'sw-cms-block-blog-slider',
        preview: 'sw-cms-preview-blog-slider',
    });
});
