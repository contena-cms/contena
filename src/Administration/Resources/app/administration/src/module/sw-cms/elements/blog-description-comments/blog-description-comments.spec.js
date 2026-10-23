/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/blog-description-comments', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/blog-description-comments',
        name: 'blog-description-comments',
        component: 'sw-cms-el-blog-description-comments',
        config: 'sw-cms-el-config-blog-description-comments',
        preview: 'sw-cms-el-preview-blog-description-comments',
    });
});
