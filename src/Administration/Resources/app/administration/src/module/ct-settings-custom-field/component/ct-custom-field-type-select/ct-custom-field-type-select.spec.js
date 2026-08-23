import { mount } from '@vue/test-utils';

const currentCustomField = {
    name: 'technical_test',
    type: 'select',
    config: {
        label: { 'en-GB': null },
        options: [
            {
                label: { 'en-GB': 'translated-label-1' },
                value: 'label-with-translated-value',
            },
            {
                label: {},
                value: 'label-without-translated-value',
            },
            {
                label: [],
                value: 'label-with-incorrect-value',
            },
        ],
        helpText: { 'en-GB': null },
        placeholder: { 'en-GB': null },
        componentName: 'mt-select',
        customFieldType: 'select',
        customFieldPosition: 1,
    },
    active: true,
    customFieldSetId: 'd2667dfae415440592a0944fbea2d3ce',
    id: '8e1ab96faf374836a4d68febc8d4f1e1',
    productSearchConfigFields: [],
};

const defaultProps = {
    currentCustomField,
    set: {
        name: 'technical_test',
        config: { label: { 'en-GB': 'test_label' } },
        active: true,
        global: false,
        position: 1,
        appId: null,
        createdAt: '2021-06-30T08:02:28.996+00:00',
        updatedAt: null,
        apiAlias: null,
        id: 'd2667dfae415440592a0944fbea2d3ce',
        customFields: [],
        relations: [
            {
                customFieldSetId: 'd2667dfae415440592a0944fbea2d3ce',
                entityName: 'product',
                createdAt: '2021-06-30T08:02:28.996+00:00',
                updatedAt: null,
                apiAlias: null,
                id: '559b6ae735b04e199505fd4c5ac5f22c',
            },
        ],
        products: [],
    },
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('ct-custom-field-type-select', { sync: true }), {
        props,
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $i18n: {
                    fallbackLocale: {
                        value: 'en-GB',
                    },
                },
            },
            stubs: {
                'ct-custom-field-translated-labels': true,

                'ct-base-field': await wrapTestComponent('ct-base-field'),
                'ct-block-field': await wrapTestComponent('ct-block-field'),
                'ct-field-error': true,
                'ct-container': await wrapTestComponent('ct-container'),
                'ct-contextual-field': await wrapTestComponent('ct-contextual-field'),
                'router-link': true,
                'ct-loader': true,
                'ct-field-copyable': true,
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
            },
        },
    });
}

describe('src/module/ct-settings-custom-field/component/ct-custom-field-type-select', () => {
    it('should allow saving of labels for options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const labelInputs = wrapper.findAll('.ct-custom-field-type-select__option-label input');
        expect(labelInputs[0].element.value).toBe('translated-label-1');
        expect(labelInputs[1].element.value).toBe('');
        expect(labelInputs[2].element.value).toBe('');

        for (const labelInput of labelInputs) {
            const index = labelInputs.indexOf(labelInput);
            await labelInput.setValue(`label-${index}`);
        }

        await flushPromises();

        expect(wrapper.vm.currentCustomField.config).toEqual({
            componentName: 'mt-select',
            customFieldPosition: 1,
            customFieldType: 'select',
            helpText: {
                'en-GB': null,
            },
            label: {
                'en-GB': null,
            },
            options: [
                {
                    label: {
                        'en-GB': 'label-0',
                    },
                    value: 'label-with-translated-value',
                },
                {
                    label: {
                        'en-GB': 'label-1',
                    },
                    value: 'label-without-translated-value',
                },
                {
                    label: {
                        'en-GB': 'label-2',
                    },
                    value: 'label-with-incorrect-value',
                },
            ],
            placeholder: {
                'en-GB': null,
            },
        });
    });

    it('should allow adding new options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const addButton = wrapper.find('.ct-custom-field-type-select__button-add');
        expect(wrapper.vm.currentCustomField.config.options).toHaveLength(3);

        await addButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.options).toHaveLength(4);
    });

    it('should only allow valid component names', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            currentCustomField: {
                ...currentCustomField,
                config: {
                    ...currentCustomField.config,
                    componentName: 'foo',
                },
            },
        });
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.componentName).toBe('mt-select');
    });

    it('should toggle multi-switch on switch', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.componentName).toBe('mt-select');
        expect(wrapper.vm.currentCustomField.config.enableMultiSelection).toBeUndefined();
        expect(wrapper.vm.multiSelectSwitch).toBe(false);

        // Find the switch and trigger checkbox input event to change to true
        const mtSwitch = wrapper.find('.ct-custom-field-detail__switch input');
        await mtSwitch.setValue(true);
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.componentName).toBe('mt-select');
        expect(wrapper.vm.currentCustomField.config.enableMultiSelection).toBe(true);

        // Toggle back to false
        await mtSwitch.setValue(false);
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.componentName).toBe('mt-select');
        expect(wrapper.vm.currentCustomField.config.enableMultiSelection).toBe(false);
    });
});
