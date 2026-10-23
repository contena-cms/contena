import { mount } from '@vue/test-utils';

/**
 * @sw-package after-sales
 */

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-flow-change-user-status-modal', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-modal': {
                        template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `,
                    },
                    'sw-single-select': {
                        model: {
                            prop: 'value',
                            event: 'change',
                        },
                        props: ['value'],
                        template: `
                    <div class="sw-single-select">
                        <input
                            class="sw-single-select__selection-input"
                            :value="value"
                            @input="$emit('update:value', $event.target.value)"
                        />
                        <slot></slot>
                    </div>
                `,
                    },
                },
            },
            props: {
                sequence: {},
            },
        },
    );
}

describe('module/sw-flow/component/sw-flow-change-user-status-modal', () => {
    it('should emit process-finish when user status is selected', async () => {
        const wrapper = await createWrapper();

        const userStatusInput = wrapper.find('.sw-single-select__selection-input');
        await userStatusInput.setValue(false);
        await userStatusInput.trigger('input');

        const saveButton = wrapper.find('.sw-flow-change-user-status-modal__save-button');
        await saveButton.trigger('click');

        expect(wrapper.emitted()['process-finish'][0]).toEqual([
            {
                config: {
                    active: 'false',
                },
            },
        ]);
    });
});
