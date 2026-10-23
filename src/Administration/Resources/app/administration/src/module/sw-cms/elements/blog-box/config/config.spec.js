/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-blog-box', { sync: true }), {
        props: {
            element: {
                type: 'blog-box',
            },
        },
        global: {
            provide: {
                cmsService: Contena.Service('cmsService'),
            },
            stubs: {
                'sw-entity-single-select': true,
                'sw-blog-variant-info': true,
                'sw-select-field': true,
                'sw-select-result': true,
                'sw-cms-inherit-wrapper': {
                    template: '<div><slot :isInherited="false"></slot></div>',
                    props: [
                        'field',
                        'element',
                        'contentEntity',
                        'label',
                    ],
                },
            },
        },
    });
}

describe('src/module/sw-cms/elements/blog-box/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/blog-box');
    });

    it('mounts the component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeDefined();
    });
});
