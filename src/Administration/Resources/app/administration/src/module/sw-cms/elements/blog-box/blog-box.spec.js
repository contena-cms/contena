/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/blog-box', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/blog-box',
        name: 'blog-box',
        component: 'sw-cms-el-blog-box',
        config: 'sw-cms-el-config-blog-box',
        preview: 'sw-cms-el-preview-blog-box',
    });
});
