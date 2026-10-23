/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/blog-listing', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/blog-listing',
        name: 'blog-listing',
        component: 'sw-cms-el-blog-listing',
        config: 'sw-cms-el-config-blog-listing',
        preview: 'sw-cms-el-preview-blog-listing',
    });
});
