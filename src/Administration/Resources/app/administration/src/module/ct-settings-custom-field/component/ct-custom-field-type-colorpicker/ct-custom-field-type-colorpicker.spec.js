import { mount } from '@vue/test-utils';

const currentCustomField = {
    name: 'technical_test',
    type: 'colorpicker',
    config: {
        label: { 'en-GB': null },
        helpText: { 'en-GB': null },
        componentName: 'ct-colorpicker',
        customFieldType: 'colorpicker',
        customFieldPosition: 1,
    },
    active: true,
    customFieldSetId: 'd2667dfae415440592a0944fbea2d3ce',
    id: '8e1ab96faf374836a4d68febc8d4f1e1',
};

const defaultProps = {
    currentCustomField,
    set: {
        name: 'technical_test',
        config: { label: { 'en-GB': 'test_label' } },
        active: true,
        global: false,
        position: 1,
        id: 'd2667dfae415440592a0944fbea2d3ce',
    },
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('ct-custom-field-type-colorpicker', { sync: true }), {
        props,
        global: {
            mocks: {
                $t: (key) => key,
            },
            stubs: {
                'ct-custom-field-translated-labels': true,
            },
        },
    });
}

describe('src/module/ct-settings-custom-field/component/ct-custom-field-type-colorpicker', () => {
    it('should provide correct property names for translations', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.propertyNames).toEqual({
            label: 'ct-settings-custom-field.customField.detail.labelLabel',
            helpText: 'ct-settings-custom-field.customField.detail.labelHelpText',
        });
    });
});
