import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';

function getFieldTypes() {
    return {
        select: {
            configRenderComponent: 'ct-custom-field-type-select',
            config: {
                componentName: 'mt-select',
            },
        },
        checkbox: {
            configRenderComponent: 'ct-custom-field-type-checkbox',
            type: 'bool',
            config: { componentName: 'ct-field', type: 'checkbox' },
        },
        switch: {
            configRenderComponent: 'ct-custom-field-type-checkbox',
            type: 'bool',
            config: { componentName: 'ct-field', type: 'switch' },
        },
    };
}

const customFieldFixture = {
    id: 'id1',
    name: 'custom_additional_field_1',
    config: {
        label: { 'en-GB': 'Special field 1' },
        customFieldType: 'checkbox',
        customFieldPosition: 1,
    },
    _isNew: true,
    getEntityName: () => 'custom_field',
};

const defaultProps = {
    currentCustomField: customFieldFixture,
    set: {},
};

async function createWrapper(props = defaultProps, privileges = []) {
    return mount(
        await wrapTestComponent('ct-custom-field-detail', {
            sync: true,
        }),
        {
            props,
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $i18n: {
                        fallbackLocale: 'en-GB',
                        t: (key) => key,
                        tc: (key) => key,
                    },
                },
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getTypes: () => getFieldTypes(),
                    },
                    SwCustomFieldListIsCustomFieldNameUnique: () => Promise.resolve(true),
                    validationService: {},
                },
                stubs: {
                    'mt-modal-root': {
                        name: 'MtModalRoot',
                        template: '<div><slot /></div>',
                        props: ['isOpen'],
                    },
                    'mt-modal': {
                        template: '<div class="ct-custom-field-detail"><slot /><slot name="footer" /></div>',
                        props: ['title'],
                    },
                    'ct-custom-field-type-checkbox': true,
                    'mt-number-field': true,
                    'ct-text-field': true,
                    'ct-block-field': await wrapTestComponent('ct-block-field'),
                    'ct-base-field': await wrapTestComponent('ct-base-field'),
                    'ct-field-error': true,
                    'ct-help-text': true,
                    'ct-loader': true,
                    'router-link': true,
                    'ct-inheritance-switch': true,
                    'ct-ai-copilot-badge': true,
                    'mt-switch': true,
                    'mt-banner': true,
                    'mt-select': {
                        template: `
                            <div class="mt-select ct-custom-field-detail__modal-type">
                                <input :disabled="disabled" @click="handleClick" />
                                <div v-show="showPopover" class="mt-popover-deprecated">
                                    <ul>
                                        <li v-for="option in options" :key="option.value" @pointerdown="selectOption(option)">
                                            {{ option.label }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        `,
                        props: [
                            'disabled',
                            'options',
                        ],
                        data() {
                            return {
                                showPopover: false,
                            };
                        },
                        methods: {
                            async handleClick() {
                                this.showPopover = true;
                                await this.$nextTick();
                            },
                            selectOption(option) {
                                this.$emit('update:modelValue', option.value);
                                this.showPopover = false;
                            },
                        },
                    },
                    'mt-text-field': {
                        template: `
                            <div class="ct-custom-field-detail__technical-name">
                                <input :disabled="disabled" />
                                <div
                                    v-if="error"
                                    class="mt-field__error"
                                >
                                    {{ error.detail || error }}
                                </div>
                            </div>
                        `,
                        props: [
                            'disabled',
                            'error',
                        ],
                    },
                },
            },
        },
    );
}

describe('src/module/ct-settings-custom-field/component/ct-custom-field-detail', () => {
    it('cancels editing when the modal closes', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);

        wrapper.getComponent({ name: 'MtModalRoot' }).vm.$emit('change', false);
        await flushPromises();

        expect(wrapper.emitted('custom-field-edit-cancel')).toEqual([[customFieldFixture]]);
    });

    it('can edit fields', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        const modalTypeField = wrapper.find('.ct-custom-field-detail__modal-type input');
        const technicalNameField = wrapper.findComponent('.ct-custom-field-detail__technical-name');
        const modalPositionField = wrapper.find('.ct-custom-field-detail__modal-position');
        const modalSaveButton = wrapper.find('.ct-custom-field-detail__footer-save');

        expect(modalTypeField.attributes('disabled')).toBeUndefined();
        expect(technicalNameField.props('disabled')).toBeFalsy();
        expect(modalPositionField.attributes('disabled')).toBeUndefined();
        expect(modalSaveButton.attributes('disabled')).toBeUndefined();
    });

    it('cannot edit fields', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const modalTypeField = wrapper.find('.ct-custom-field-detail__modal-type input');
        const technicalNameField = wrapper.findComponent('.ct-custom-field-detail__technical-name');
        const modalPositionField = wrapper.find('.ct-custom-field-detail__modal-position');
        const modalSaveButton = wrapper.find('.ct-custom-field-detail__footer-save');

        expect(modalTypeField.attributes('disabled')).toBeDefined();
        expect(technicalNameField.props('disabled')).toBeTruthy();
        expect(modalPositionField.attributes('disabled')).toBeDefined();
        expect(modalSaveButton.attributes('disabled')).toBeDefined();
    });

    it('should update config correctly', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        await selectMtSelectOptionByText(wrapper, 'ct-settings-custom-field.types.select');

        await flushPromises();

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'select',
            }),
        );

        await selectMtSelectOptionByText(wrapper, 'ct-settings-custom-field.types.switch');

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'switch',
            }),
        );

        const saveButton = wrapper.find('.ct-custom-field-detail__footer-save');
        await saveButton.trigger('click');

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'switch',
                componentName: 'ct-field',
            }),
        );
    });

    it('should show error if custom field name is invalid', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        expect(wrapper.find('.ct-custom-field-detail__technical-name .mt-field__error').exists()).toBe(false);

        await selectMtSelectOptionByText(wrapper, 'ct-settings-custom-field.types.select');
        await flushPromises();

        await wrapper.find('.ct-custom-field-detail__technical-name input').setValue('invalid-name.');
        expect(wrapper.vm.currentCustomField.name).toBe('custom_additional_field_1');
        await flushPromises();

        await wrapper.find('.ct-custom-field-detail__footer-save').trigger('click');
        expect(wrapper.emitted('custom-field-edit-save')).toBeDefined();

        Contena.Store.get('error').addApiError({
            expression: `custom_field.id1.name.error`,
            error: new Contena.Classes.ContenaError({ code: 'test', detail: 'test' }),
        });
        await flushPromises();

        expect(wrapper.find('.ct-custom-field-detail__technical-name .mt-field__error').exists()).toBe(true);
        expect(wrapper.find('.ct-custom-field-detail__technical-name .mt-field__error').text()).toBe('test');
    });

    it('should set includeInSearch to false by default for new custom fields', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        expect(wrapper.vm.currentCustomField.includeInSearch).toBe(false);
    });

    it('should preserve includeInSearch value for existing custom fields', async () => {
        const existingField = {
            ...customFieldFixture,
            _isNew: false,
            includeInSearch: true,
        };

        const wrapper = await createWrapper(
            {
                currentCustomField: existingField,
                set: {},
            },
            ['custom_field.editor'],
        );
        await flushPromises();

        expect(wrapper.vm.currentCustomField.includeInSearch).toBe(true);
    });

    it('should show the searchable toggle for generic custom fields', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        const searchableToggle = wrapper.find('.ct-custom-field-detail__allow-searchable');
        expect(searchableToggle.exists()).toBe(true);
    });
});
