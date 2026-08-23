import { defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { useRemoveApiError } from './use-remove-api-error';

describe('use-remove-api-error', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('removes the current API error when the watched value changes', async () => {
        const removeApiError = jest.spyOn(Contena.Store.get('error'), 'removeApiError');
        const wrapper = mount(
            defineComponent({
                setup() {
                    const value = ref('initial');
                    useRemoveApiError(
                        () => value.value,
                        () => ({ selfLink: 'error-link' }),
                    );

                    return { value };
                },
                template: '<div />',
            }),
        );

        wrapper.vm.value = 'changed';
        await nextTick();

        expect(removeApiError).toHaveBeenCalledWith('error-link');
        wrapper.unmount();
    });

    it('ignores value changes without an API error link', async () => {
        const removeApiError = jest.spyOn(Contena.Store.get('error'), 'removeApiError');
        const value = ref('initial');
        const wrapper = mount(
            defineComponent({
                setup() {
                    useRemoveApiError(
                        () => value.value,
                        () => undefined,
                    );
                },
                template: '<div />',
            }),
        );

        value.value = 'changed';
        await nextTick();

        expect(removeApiError).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});
