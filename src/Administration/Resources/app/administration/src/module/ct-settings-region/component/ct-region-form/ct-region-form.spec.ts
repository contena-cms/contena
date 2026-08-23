import { mount } from '@vue/test-utils';

const createRegion = () => ({
    id: 'region-id',
    name: 'Guangdong',
    shortName: 'GD',
    code: '440000',
    type: 'province',
    position: 1,
    active: true,
    translated: {},
    translations: [],
});

async function createWrapper(disabled = false, customFieldSets: unknown[] = []) {
    return mount(await wrapTestComponent('ct-region-form', { sync: true }), {
        props: {
            region: createRegion(),
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
                'ct-data-dictionary-select': {
                    name: 'ct-data-dictionary-select',
                    props: [
                        'modelValue',
                        'technicalName',
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

describe('module/ct-settings-region/component/ct-region-form', () => {
    it('can be embedded without a modal container', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('mt-modal-root').exists()).toBe(false);
        expect(wrapper.find('input[name="ct-field--region-name"]').exists()).toBe(true);
    });

    it('disables every editable field in read-only contexts', async () => {
        const wrapper = await createWrapper(true);

        expect(wrapper.findAll('input').every((field) => field.attributes('disabled') !== undefined)).toBe(true);
        expect(wrapper.find('mt-number-field-stub').attributes('disabled')).toBeDefined();
        expect(wrapper.find('mt-switch-stub').attributes('disabled')).toBeDefined();
    });

    it('renders matching Region custom fields', async () => {
        const wrapper = await createWrapper(false, [{ id: 'custom-field-set-id' }]);

        expect(wrapper.findComponent({ name: 'ct-custom-field-set-renderer' }).props('sets')).toEqual([
            { id: 'custom-field-set-id' },
        ]);
    });

    it('uses the core region type data dictionary', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'ct-data-dictionary-select' }).props('technicalName')).toBe('core.region.type');
    });

    it('emits field changes for an embedding page to persist', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('input[name="ct-field--region-name"]').setValue('Guangdong Province');

        expect(wrapper.emitted('update:region')?.[0]).toEqual([
            'name',
            'Guangdong Province',
        ]);
    });
});
