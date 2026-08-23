import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('ct-extension-uninstall-modal', { sync: true }), {
        global: {
            mocks: {
                $t: (path, values) => {
                    if (values) {
                        return path + Object.values(values);
                    }

                    return path;
                },
            },
            stubs: {
                'ct-modal': true,
            },
            provide: {},
        },
        props: {
            extensionName: 'Sample extension',
            isLoading: false,
            ...propsData,
        },
    });
}

describe('src/module/ct-extension/component/ct-extension-uninstall-modal', () => {
    it('should show the correct title', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.title).toBe('ct-extension.component.ct-extension-uninstall-modal.titleSample extension');
    });

    it('should not emit the close event when is loading', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            isLoading: true,
        });

        expect(wrapper.emitted()).not.toHaveProperty('modal-close');

        await wrapper.vm.emitClose();

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()).not.toHaveProperty('modal-close');
    });

    it('should emit the uninstall extension event', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted()).not.toHaveProperty('uninstall-extension');

        await wrapper.vm.emitUninstall();

        expect(wrapper.emitted()).toHaveProperty('uninstall-extension', [
            [false],
        ]);
    });
});
