import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], isNew = true, currentCustomField = {}) {
    return mount(
        await wrapTestComponent('ct-custom-field-type-entity', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $t: () => {
                        return 'foo';
                    },
                    $i18n: {
                        fallbackLocale: 'en-GB',
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
                },
                stubs: {
                    'ct-custom-field-type-base': true,
                    'ct-custom-field-translated-labels': true,
                    'mt-select': true,
                    'ct-field': true,

                    'ct-text-field': true,
                    'ct-container': true,
                },
            },
            props: {
                currentCustomField: {
                    id: 'id1',
                    name: 'custom_additional_field_1',
                    config: {
                        label: { 'en-GB': 'Entity Type Field' },
                        customFieldType: 'entity',
                        customFieldPosition: 1,
                        options: [],
                    },
                    _isNew: isNew,
                    ...currentCustomField,
                },
                set: {
                    config: {},
                },
            },
        },
    );
}

describe('src/module/ct-settings-custom-field/component/ct-custom-field-type-entity', () => {
    it('uses the Meteor entity select for single and multiple values', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.componentName).toBe('mt-entity-select');
        expect(wrapper.vm.currentCustomField.config.enableMultiSelection).toBeUndefined();

        const multiSelectSwitch = wrapper.find('.ct-custom-field-detail__switch input');
        await multiSelectSwitch.setValue(true);
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.componentName).toBe('mt-entity-select');
        expect(wrapper.vm.currentCustomField.config.enableMultiSelection).toBe(true);
    });

    it('should only offer generic platform entities', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.entityTypes.map(({ value }) => value)).toEqual([
            'country',
            'region',
            'media',
            'tag',
            'user',
        ]);
    });

    it('should allow entity type selection on new custom field', async () => {
        const wrapper = await createWrapper();
        const entitySelect = wrapper.find('mt-select-stub');

        expect(entitySelect.attributes('disabled')).toBeFalsy();
    });

    it('should not allow entity type selection on existing custom field', async () => {
        const wrapper = await createWrapper([], false);
        wrapper.vm.currentCustomField._isNew = false;

        const entitySelect = wrapper.find('mt-select-stub');

        expect(entitySelect.attributes('disabled')).toBeTruthy();
    });

    it('should not allow to add options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.ct-custom-field-type-select__button-add').exists()).toBe(false);
        expect(wrapper.vm.currentCustomField.config.options).toBeUndefined();
    });

    it('should disable multi select switch: old custom field', async () => {
        const wrapper = await createWrapper([], false);
        await flushPromises();

        expect(wrapper.findComponent('.mt-switch').props('disabled')).toBeDefined();
    });

    it('should disable multi select switch: new custom field', async () => {
        const wrapper = await createWrapper([], true);
        await flushPromises();

        expect(wrapper.findComponent('.mt-switch').props('disabled')).toBeUndefined();
    });

    it('should only allow valid component names', async () => {
        const wrapper = await createWrapper([], true, {
            config: {
                componentName: 'foo',
            },
        });
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.componentName).toBe('mt-entity-select');
    });
});
