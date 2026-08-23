import { mount } from '@vue/test-utils';

const createPosition = () => ({
    id: 'position-id',
    name: 'General Manager',
    code: 'general_manager',
    description: 'Leads the company',
    position: 1,
    active: true,
    translated: {},
    translations: [],
});

async function createWrapper(disabled = false, customFieldSets: unknown[] = []) {
    return mount(await wrapTestComponent('mt-position-form', { sync: true }), {
        props: {
            positionEntity: createPosition(),
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
                'mt-textarea': true,
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

describe('module/ct-settings-position/component/mt-position-form', () => {
    it('emits field changes for an embedding page to persist', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('input[name="ct-field--position-name"]').setValue('Chief Executive Officer');

        expect(wrapper.emitted('update:position')?.[0]).toEqual([
            'name',
            'Chief Executive Officer',
        ]);
    });

    it('supports read-only embedding and custom fields', async () => {
        const wrapper = await createWrapper(true, [{ id: 'position-fields' }]);

        expect(wrapper.get('input[name="ct-field--position-name"]').attributes('disabled')).toBeDefined();
        expect(wrapper.findComponent({ name: 'ct-custom-field-set-renderer' }).props('sets')).toEqual([
            { id: 'position-fields' },
        ]);
    });
});
