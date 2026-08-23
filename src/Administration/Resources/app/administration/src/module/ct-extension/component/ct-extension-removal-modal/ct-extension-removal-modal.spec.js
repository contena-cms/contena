import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('ct-extension-removal-modal', { sync: true }), {
        global: {
            mocks: {
                $t: (key, values) => {
                    return values ? key + JSON.stringify(Object.values(values)) : key;
                },
            },
            stubs: {},
        },
        props: {
            extensionName: 'Awesome extension',
            isLoading: false,
            ...propsData,
        },
    });
}

describe('src/module/ct-extension/component/ct-extension-removal-modal', () => {
    it('should show the correct title', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.title).toBe('ct-extension.component.ct-extension-removal-modal.titleRemove["Awesome extension"]');
    });

    it('should show the correct alert text', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.alert).toBe('ct-extension.component.ct-extension-removal-modal.alertRemove');
    });

    it('should show the correct button label', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.btnLabel).toBe('global.default.remove');
    });

    it('should emit the close event', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted()).not.toHaveProperty('modal-close');

        await wrapper.vm.emitClose();

        expect(wrapper.emitted()).toHaveProperty('modal-close');
    });

    it('should not emit the close event when is loading', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            isLoading: true,
        });

        expect(wrapper.emitted()).not.toHaveProperty('modal-close');

        await wrapper.vm.emitClose();

        expect(wrapper.emitted()).not.toHaveProperty('modal-close');
    });

    it('should emit the remove extension event', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted()).not.toHaveProperty('remove-extension');

        await wrapper.vm.emitRemoval();

        expect(wrapper.emitted()).toHaveProperty('remove-extension');
    });
});
