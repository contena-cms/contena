import { mount } from '@vue/test-utils';

import 'src/app/component/modal/ct-confirm-modal';
import 'src/app/component/base/ct-modal';

describe('src/app/component/modal/ct-confirm-modal', () => {
    let wrapper = null;

    async function createWrapper(props = {}) {
        return mount(await wrapTestComponent('ct-confirm-modal', { sync: true }), {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'ct-block': await wrapTestComponent('ct-block', { sync: true }),
                    'ct-modal': await wrapTestComponent('ct-modal'),
                    'ct-loader': true,
                    'router-link': true,
                },
            },
            props,
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.unmount();
    });

    it('emits confirm when confirm button is clicked', async () => {
        wrapper = await createWrapper({});

        await wrapper.get('.ct-confirm-modal__button-confirm').trigger('click');

        expect(wrapper.emitted('confirm')).toBeTruthy();
    });

    it('emits cancel when cancel button is clicked', async () => {
        wrapper = await createWrapper({});

        await wrapper.get('.ct-confirm-modal__button-cancel').trigger('click');

        expect(wrapper.emitted('cancel')).toBeTruthy();
    });

    it('emits close when modal is closed', async () => {
        wrapper = await createWrapper({});

        await wrapper.find('.ct-modal__close').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('close')).toBeTruthy();
    });

    function expectedValues(confirmButtonVariant, confirmText, cancelText) {
        return {
            confirmButtonVariant,
            confirmText,
            cancelText,
        };
    }

    const typeExpectations = [
        [
            'confirm',
            expectedValues('primary', 'confirm', 'cancel'),
        ],
        [
            'yesno',
            expectedValues('primary', 'yes', 'no'),
        ],
        [
            'delete',
            expectedValues('critical', 'delete', 'cancel'),
        ],
        [
            'discard',
            expectedValues('critical', 'discard', 'cancel'),
        ],
    ];

    it.each(typeExpectations)(
        'has correct labels for %s',
        async (type, { cancelText, confirmText, confirmButtonVariant }) => {
            wrapper = await createWrapper({ type });

            expect(wrapper.get('.ct-confirm-modal__button-cancel').text()).toBe(`global.default.${cancelText}`);
            expect(wrapper.get('.ct-confirm-modal__button-confirm').text()).toBe(`global.default.${confirmText}`);
            expect(wrapper.get('.ct-confirm-modal__button-confirm').classes(`mt-button--${confirmButtonVariant}`)).toBe(
                true,
            );
        },
    );
});
