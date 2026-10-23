/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/blog-description-reviews', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/blog-description-reviews',
        name: 'blog-description-reviews',
        component: 'sw-cms-block-blog-description-reviews',
        preview: 'sw-cms-preview-blog-description-reviews',
    });
});
