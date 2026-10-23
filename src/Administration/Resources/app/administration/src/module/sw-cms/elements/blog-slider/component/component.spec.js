/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-blog-slider', { sync: true }), {
        props: {
            element: {
                type: 'blog-slider',
            },
        },
        global: {
            provide: {
                cmsService: Contena.Service('cmsService'),
            },
            stubs: {
                'sw-cms-el-blog-box': await wrapTestComponent('sw-cms-el-blog-box'),
            },
        },
    });
}

describe('src/module/sw-cms/elements/blog-slider/component', () => {
    let resizeCallback = null;
    let observeMock = null;
    let disconnectMock = null;

    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/blog-slider');
    });

    beforeEach(() => {
        resizeCallback = null;
        observeMock = jest.fn();
        disconnectMock = jest.fn();

        window.ResizeObserver = jest.fn().mockImplementation((callback) => {
            resizeCallback = callback;

            return {
                observe: observeMock,
                disconnect: disconnectMock,
            };
        });
    });

    it('mounts the component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeDefined();
    });

    it('observes the blog holder to recalculate the box limit', async () => {
        const wrapper = await createWrapper();

        expect(observeMock).toHaveBeenCalledWith(wrapper.vm.$refs.blogHolder);
    });

    it('recalculates the box limit when the blog holder resizes but never shows more than three', async () => {
        const wrapper = await createWrapper();

        jest.spyOn(wrapper.vm.$refs.blogHolder, 'offsetWidth', 'get').mockReturnValue(1360);
        wrapper.vm.element.config.elMinWidth.value = '300px';

        resizeCallback();

        expect(wrapper.vm.sliderBoxLimit).toBe(3);
    });

    it('disconnects the resize observer before unmount', async () => {
        const wrapper = await createWrapper();

        wrapper.unmount();

        expect(disconnectMock).toHaveBeenCalled();
    });
});
