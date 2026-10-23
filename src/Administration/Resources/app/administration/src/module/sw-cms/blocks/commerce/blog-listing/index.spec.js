/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/blog-listing', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/blog-listing',
        name: 'blog-listing',
        component: 'sw-cms-block-blog-listing',
        preview: 'sw-cms-preview-blog-listing',
    });
});
