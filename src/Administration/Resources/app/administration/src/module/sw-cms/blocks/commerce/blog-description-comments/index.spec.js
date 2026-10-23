/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/blog-description-comments', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/blog-description-comments',
        name: 'blog-description-comments',
        component: 'sw-cms-block-blog-description-comments',
        preview: 'sw-cms-preview-blog-description-comments',
    });
});
