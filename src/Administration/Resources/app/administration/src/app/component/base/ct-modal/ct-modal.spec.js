import { mount } from '@vue/test-utils';

async function createWrapper(additionalSlots = null) {
    return mount(await wrapTestComponent('ct-modal', { sync: true }), {
        attachTo: document.body,
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'ct-block': {
                    inheritAttrs: false,
                    template: '<slot />',
                },
                transition: {
                    inheritAttrs: false,
                    template: '<slot />',
                },
                'ct-loader': true,
            },
        },
        slots: {
            default: `
                <div class="modal-content-stuff">
                    Some content inside modal body
                    <input name="test" class="test-input">
                </div>
            `,
            ...additionalSlots,
        },
    });
}

describe('src/app/component/base/ct-modal/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper({
            'modal-footer': '<div class="modal-footer-stuff">Some content inside modal footer</div>',
        });

        await flushPromises();
    });

    afterEach(async () => {
        await flushPromises();
        wrapper?.unmount();
    });

    it('should render modal with body', async () => {
        await wrapper.setProps({
            title: 'Cool modal',
        });

        expect(wrapper.get('.ct-modal__body').text()).toBe('Some content inside modal body');
        expect(wrapper.get('.ct-modal__title').text()).toBe('Cool modal');
    });

    it('should show console error when using invalid variant', async () => {
        const swModal = await wrapTestComponent('ct-modal', { sync: true });
        const validator = swModal.props.variant.validator;

        expect(validator('default')).toBe(true);
        expect(validator('small')).toBe(true);
        expect(validator('large')).toBe(true);
        expect(validator('full')).toBe(true);
        expect(validator('not-existing')).toBe(false);
    });

    it.each([
        'default',
        'small',
        'large',
        'full',
    ])('should set correct variant class for %s', async (variant) => {
        await wrapper.setProps({
            variant: variant,
        });

        expect(wrapper.get('.ct-modal').classes(`ct-modal--${variant}`)).toBe(true);
    });

    it('should have has--header class if showHeader option is true', async () => {
        await wrapper.setProps({
            showHeader: true,
        });

        expect(wrapper.get('.ct-modal__dialog').classes('has--header')).toBe(true);
    });

    it('should not have has--header class if showHeader option is false', async () => {
        await wrapper.setProps({
            showHeader: false,
        });

        expect(wrapper.get('.ct-modal__dialog').classes('has--header')).toBe(false);
    });

    it('should have ct-modal__footer class if showFooter option is true', async () => {
        await wrapper.setProps({
            showFooter: true,
        });

        expect(wrapper.get('.ct-modal__footer').exists()).toBeTruthy();
        expect(wrapper.get('.modal-footer-stuff').exists()).toBeTruthy();
    });

    it('should not have ct-modal__footer class if showFooter option is false', async () => {
        await wrapper.setProps({
            showFooter: false,
        });

        expect(wrapper.get('.ct-modal__dialog').classes('ct-modal__footer')).toBeFalsy();
    });

    it('should fire the close event when clicking the close button', async () => {
        await wrapper.get('.ct-modal__close').trigger('click');

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should remove the relocated modal element after unmounting', () => {
        jest.useFakeTimers();
        const modalElement = wrapper.get('.ct-modal').element;

        wrapper.unmount();
        jest.advanceTimersByTime(400);
        jest.useRealTimers();

        expect(document.body.contains(modalElement)).toBe(false);
    });

    it('should close the modal when clicking outside modal', async () => {
        await wrapper.get('.ct-modal').trigger('mousedown');

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should not close the modal when clicking outside modal and closeable option is false', async () => {
        await wrapper.setProps({
            closable: false,
        });

        await wrapper.get('.ct-modal').trigger('mousedown');

        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should close the modal when using ESC key', async () => {
        await wrapper.setProps({
            closable: true,
        });

        await wrapper.get('.ct-modal__dialog').trigger('keyup.esc');

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should not close the modal when using ESC key when the event does not come from the modal dialog', async () => {
        await wrapper.get('.test-input').trigger('keyup.esc');

        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should not close the modal when using ESC key if closable option is false', async () => {
        await wrapper.setProps({
            closable: false,
        });

        await wrapper.get('.ct-modal__dialog').trigger('keyup.esc');

        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should render content from modal title slot', async () => {
        wrapper = await createWrapper({
            'modal-title': '<div class="custom-html">Custom HTML title</div>',
        });

        expect(wrapper.get('.ct-modal__titles').html()).toContain('<div class="custom-html">Custom HTML title</div>');
    });

    it('should be able to update the modal classes', async () => {
        expect(wrapper.get('.ct-modal').classes('ct-modal--has-sidebar')).toBe(false);

        Contena.Store.get('adminHelpCenter').showHelpSidebar = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.get('.ct-modal').classes('ct-modal--has-sidebar')).toBe(true);
    });

    it('should add classes for the modal body correctly', async () => {
        await wrapper.setProps({
            showFooter: false,
        });
        expect(wrapper.get('.ct-modal__body').classes('has--no-footer')).toBeTruthy();

        await wrapper.setProps({
            showFooter: true,
        });
        expect(wrapper.get('.ct-modal__body').classes('has--no-footer')).toBeFalsy();
    });
});
