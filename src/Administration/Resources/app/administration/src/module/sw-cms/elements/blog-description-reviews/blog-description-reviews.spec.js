/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/blog-description-reviews', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/blog-description-reviews',
        name: 'blog-description-reviews',
        component: 'sw-cms-el-blog-description-reviews',
        config: 'sw-cms-el-config-blog-description-reviews',
        preview: 'sw-cms-el-preview-blog-description-reviews',
    });
});
