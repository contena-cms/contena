import { mount } from '@vue/test-utils';

const createWrapper = async (props = {}, attrs = {}) => {
    const wrapper = mount(await wrapTestComponent('ct-select-base', { sync: true }), {
        props,
        attrs,
        global: {
            stubs: {
                'ct-block-field': await wrapTestComponent('ct-block-field'),
                'ct-base-field': await wrapTestComponent('ct-base-field'),
                'ct-field-error': await wrapTestComponent('ct-field-error'),
                'ct-help-text': true,
                'ct-ai-copilot-badge': true,
                'ct-inheritance-switch': true,
                'ct-loader': true,
            },
        },
    });

    await flushPromises();

    return wrapper;
};

describe('components/ct-select-base', () => {
    it('should show the clearable icon by default when required is not set', async () => {
        const wrapper = await createWrapper();

        const clearableIcon = wrapper.find('.ct-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should not show the clearable icon by default when required is true', async () => {
        const wrapper = await createWrapper({}, { required: true });

        const clearableIcon = wrapper.find('.ct-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(false);
    });

    it('should show the clearable icon when required is false', async () => {
        const wrapper = await createWrapper({}, { required: false });

        const clearableIcon = wrapper.find('.ct-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should show the clearable icon when explicitly set to true even if required', async () => {
        const wrapper = await createWrapper({ showClearableButton: true }, { required: true });

        const clearableIcon = wrapper.find('.ct-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(true);
    });

    it('should not show the clearable icon when explicitly set to false even if not required', async () => {
        const wrapper = await createWrapper({ showClearableButton: false }, { required: false });

        const clearableIcon = wrapper.find('.ct-select__select-indicator-clear');
        expect(clearableIcon.exists()).toBe(false);
    });

    it('should trigger clear event when user clicks on clearable icon', async () => {
        const wrapper = await createWrapper({ showClearableButton: true });

        const clearableIcon = wrapper.find('.ct-select__select-indicator-clear');

        // expect no clear event
        expect(wrapper.emitted('clear')).toBeUndefined();

        // click on clear
        await clearableIcon.trigger('click');

        // expect clear event thrown
        expect(wrapper.emitted('clear')).toHaveLength(1);
    });

    it('should stay expanded when focus is followed by a click inside the select', async () => {
        const wrapper = await createWrapper();
        const selection = wrapper.find('.ct-select__selection');

        await selection.trigger('focus');
        await selection.trigger('click');

        expect(selection.attributes('aria-expanded')).toBe('true');
        expect(wrapper.emitted('select-collapsed')).toBeUndefined();
    });

    it('should not collapse when the event target is outside but the click position is inside the select', async () => {
        const wrapper = await createWrapper();
        const selection = wrapper.find('.ct-select__selection').element;
        const originalElementsFromPoint = document.elementsFromPoint;

        try {
            document.elementsFromPoint = jest.fn(() => [
                selection,
                document.body,
            ]);
            wrapper.vm.listenToClickOutside({
                target: document.body,
                clientX: 10,
                clientY: 10,
            });

            expect(wrapper.emitted('select-collapsed')).toBeUndefined();
        } finally {
            document.elementsFromPoint = originalElementsFromPoint;
        }
    });

    it('should collapse when the event target and click position are outside the select', async () => {
        const wrapper = await createWrapper();
        const originalElementsFromPoint = document.elementsFromPoint;

        try {
            document.elementsFromPoint = jest.fn(() => [
                document.body,
            ]);
            wrapper.vm.listenToClickOutside({
                target: document.body,
                clientX: 10,
                clientY: 10,
            });

            expect(wrapper.emitted('select-collapsed')).toHaveLength(1);
        } finally {
            document.elementsFromPoint = originalElementsFromPoint;
        }
    });
});
