/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/blog-slider', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/blog-slider',
        name: 'blog-slider',
        component: 'sw-cms-el-blog-slider',
        config: 'sw-cms-el-config-blog-slider',
        preview: 'sw-cms-el-preview-blog-slider',
    });
});
