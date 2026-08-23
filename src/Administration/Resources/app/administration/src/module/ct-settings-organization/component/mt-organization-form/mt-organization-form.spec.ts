import { mount } from '@vue/test-utils';

const createOrganization = () => ({
    id: 'organization-id',
    name: 'Contena',
    shortName: 'SW',
    code: 'CONTENA',
    organizationUnitId: 'company-unit-id',
    position: 1,
    active: true,
    translated: {},
    translations: [],
});

async function createWrapper(disabled = false, customFieldSets: unknown[] = []) {
    return mount(await wrapTestComponent('mt-organization-form', { sync: true }), {
        props: {
            organization: createOrganization(),
            disabled,
            customFieldSets,
        },
        global: {
            mocks: { $t: (key: string) => key },
            stubs: {
                'mt-text-field': {
                    props: [
                        'modelValue',
                        'name',
                        'disabled',
                    ],
                    emits: ['update:modelValue'],
                    template:
                        '<input :name="name" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                'mt-entity-select': {
                    name: 'mt-entity-select',
                    props: [
                        'modelValue',
                        'entity',
                        'labelProperty',
                        'name',
                        'disabled',
                    ],
                    emits: ['update:modelValue'],
                    template:
                        '<input :name="name" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                'mt-number-field': true,
                'mt-switch': true,
                'ct-custom-field-set-renderer': {
                    name: 'ct-custom-field-set-renderer',
                    props: [
                        'entity',
                        'sets',
                        'disabled',
                    ],
                    template: '<div />',
                },
            },
        },
    });
}

describe('module/ct-settings-organization/component/mt-organization-form', () => {
    it('uses the Organization Unit aggregate selector', async () => {
        const wrapper = await createWrapper();
        const unitSelect = wrapper.findComponent({ name: 'mt-entity-select' });

        expect(unitSelect.props('entity')).toBe('organization_unit');
        expect(unitSelect.props('labelProperty')).toBe('name');
    });

    it('emits field changes for an embedding page to persist', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('input[name="ct-field--organization-name"]').setValue('Contena Technology');

        expect(wrapper.emitted('update:organization')?.[0]).toEqual([
            'name',
            'Contena Technology',
        ]);
    });

    it('supports read-only embedding and custom fields', async () => {
        const wrapper = await createWrapper(true, [{ id: 'organization-fields' }]);

        expect(wrapper.findAll('input').every((field) => field.attributes('disabled') !== undefined)).toBe(true);
        expect(wrapper.findComponent({ name: 'ct-custom-field-set-renderer' }).props('sets')).toEqual([
            { id: 'organization-fields' },
        ]);
    });
});
