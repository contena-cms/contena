import { mount } from '@vue/test-utils';

const de = 'de-DE';
const en = 'en-GB';

const config = {
    label: {
        [de]: 'DeutschLabel',
        [en]: 'EnglishLabel',
    },
    translated: true,
};

const intl = {
    fallbackLocale: {
        value: en,
    },
};

const defaultProps = {
    locales: [
        en,
        de,
    ],
    config,
    propertyNames: {
        label1: 'label1',
        label2: 'label2',
    },
    disabled: false,
};

async function createWrapper(props = defaultProps) {
    return mount(
        await wrapTestComponent('ct-custom-field-translated-labels', {
            sync: true,
        }),
        {
            props,
            global: {
                mocks: {
                    $i18n: intl,
                },
                stubs: {
                    'mt-tabs': {
                        props: ['items'],
                        emits: ['new-item-active'],
                        template:
                            '<div><button v-for="item in items" :key="item.name" class="mt-tabs__item" @click="$emit(\'new-item-active\', item.name)">{{ item.label }}</button></div>',
                    },
                },
            },
        },
    );
}

describe('src/module/ct-settings-custom-field/component/ct-custom-field-translated-labels', () => {
    it('should render text field for single locale', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            locales: [en],
        });
        await flushPromises();

        expect(wrapper.find('.ct-custom-field-translated-labels__single').exists()).toBe(true);
        expect(wrapper.findAll('.mt-field')).toHaveLength(2);
    });

    it.each([
        { name: 'with value', value: 'TestValue' },
        { name: 'with value', value: '' },
    ])('should update single locale text fields: $name', async ({ value }) => {
        const wrapper = await createWrapper({
            ...defaultProps,
            locales: [en],
        });
        await flushPromises();

        const textField = wrapper.find('.ct-custom-field-translated-labels__single input');
        expect(textField.exists()).toBe(true);

        await textField.setValue(value);
        await textField.trigger('update');
        await flushPromises();

        expect(wrapper.vm.config.label1[en]).toBe(value !== '' ? value : null);
    });

    it('should render multiple locales with tabs', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.ct-custom-field-translated-labels__single').exists()).toBe(false);
        expect(wrapper.find('.ct-custom-field-translated-labels__tabs').exists()).toBe(true);

        expect(wrapper.findAll('.mt-tabs__item')).toHaveLength(2);
        expect(wrapper.findAll('.ct-custom-field-translated-labels__translated-content-field')).toHaveLength(2);
        expect(
            wrapper.findAllComponents('.ct-custom-field-translated-labels__translated-content-field')[0].props('label'),
        ).toBe('label1 (locale.en-GB)');

        await wrapper.findAll('.mt-tabs__item')[1].trigger('click');
        expect(wrapper.findAll('.ct-custom-field-translated-labels__translated-content-field')).toHaveLength(2);
        expect(
            wrapper.findAllComponents('.ct-custom-field-translated-labels__translated-content-field')[0].props('label'),
        ).toBe('label1 (locale.de-DE)');
    });

    it('should update multiple locales with tabs', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const textField = wrapper.find('.ct-custom-field-translated-labels__translated-content-field input');
        expect(textField.exists()).toBe(true);

        await textField.setValue('NewValue');
        await textField.trigger('update');
        await flushPromises();

        expect(wrapper.vm.config.label1[en]).toBe('NewValue');
    });

    it('should update config when locales change and set fallback if config does not contain property', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            locales: [de],
            propertyNames: {
                test: 'label1',
            },
        });
        await flushPromises();

        expect(wrapper.vm.config).toHaveProperty('test');
        expect(wrapper.vm.config.test).toStrictEqual({
            [intl.fallbackLocale.value]: null,
        });
    });
});
