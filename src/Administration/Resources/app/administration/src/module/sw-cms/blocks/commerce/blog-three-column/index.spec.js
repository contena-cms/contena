/**
 * @sw-package discovery
 */
import { runCmsBlockRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/blocks/commerce/blog-three-column', () => {
    runCmsBlockRegistryTest({
        import: 'src/module/sw-cms/blocks/commerce/blog-three-column',
        name: 'blog-three-column',
        component: 'sw-cms-block-blog-three-column',
        preview: 'sw-cms-preview-blog-three-column',
    });
});
