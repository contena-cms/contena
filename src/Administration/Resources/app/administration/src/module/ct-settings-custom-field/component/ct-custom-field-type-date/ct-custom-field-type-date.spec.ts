import { mount } from '@vue/test-utils';

async function createWrapper(config: Record<string, unknown> = {}) {
    return mount(await wrapTestComponent('ct-custom-field-type-date', { sync: true }), {
        props: {
            currentCustomField: {
                config,
            },
            set: {
                config: {},
            },
        },
        global: {
            stubs: {
                'ct-custom-field-translated-labels': true,
                'mt-select': {
                    props: [
                        'modelValue',
                        'options',
                    ],
                    template: '<div class="mt-select" />',
                },
            },
        },
    });
}

describe('src/module/ct-settings-custom-field/component/ct-custom-field-type-date', () => {
    it('initializes the default date and time configuration', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.currentCustomField.config).toEqual({
            dateType: 'datetime',
            config: {
                time_24hr: true,
            },
        });
    });

    it('keeps an existing date and time configuration', async () => {
        const wrapper = await createWrapper({
            dateType: 'date',
            config: {
                time_24hr: false,
            },
        });

        expect(wrapper.vm.currentCustomField.config).toEqual({
            dateType: 'date',
            config: {
                time_24hr: false,
            },
        });
        expect(wrapper.findAllComponents('.mt-select')).toHaveLength(1);
    });
});
