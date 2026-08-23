import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import component from './index';

type ModalVm = {
    open: boolean;
    openModal: () => Promise<void>;
};

function createWrapper(canClearCache = true, clear = jest.fn(() => Promise.resolve())) {
    return mount(component, {
        global: {
            provide: {
                acl: { can: jest.fn(() => canClearCache) },
                cacheApiService: { clear },
            },
            stubs: {
                'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                'mt-modal-root': defineComponent({
                    props: ['isOpen'],
                    template: '<div v-if="isOpen"><slot /></div>',
                }),
                'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                'mt-button': defineComponent({
                    emits: ['click'],
                    template: '<button @click="$emit(\'click\')"><slot /></button>',
                }),
            },
        },
    });
}

describe('module/ct-settings-cache/component/ct-settings-cache-modal', () => {
    it('opens only for users allowed to clear the cache', async () => {
        const allowed = createWrapper();
        const denied = createWrapper(false);
        const allowedVm = allowed.vm as unknown as ModalVm;
        const deniedVm = denied.vm as unknown as ModalVm;

        await allowedVm.openModal();
        await deniedVm.openModal();

        expect(allowedVm.open).toBe(true);
        expect(deniedVm.open).toBe(false);
    });

    it('clears the cache and closes the modal', async () => {
        const clear = jest.fn(() => Promise.resolve());
        const wrapper = createWrapper(true, clear);
        const vm = wrapper.vm as unknown as ModalVm;
        await vm.openModal();

        await wrapper.findAll('button')[1].trigger('click');

        expect(clear).toHaveBeenCalledTimes(1);
        expect(vm.open).toBe(false);
    });

    it('removes its keyboard listener when unmounted', () => {
        const removeEventListener = jest.spyOn(document, 'removeEventListener');
        const wrapper = createWrapper();

        wrapper.unmount();

        expect(removeEventListener).toHaveBeenCalledWith('keydown', expect.any(Function));
    });
});
