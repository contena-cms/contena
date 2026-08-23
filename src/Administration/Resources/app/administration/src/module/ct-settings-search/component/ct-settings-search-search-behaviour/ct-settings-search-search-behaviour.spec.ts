import { mount } from '@vue/test-utils';
import component from './ct-settings-search-search-behaviour.vue';

const radioRoot = {
    props: [
        'modelValue',
        'disabled',
    ],
    emits: ['update:modelValue'],
    template: '<fieldset class="mt-radio-group-root" :disabled="disabled"><slot /></fieldset>',
};
const numberField = {
    name: 'mt-number-field',
    props: [
        'modelValue',
        'disabled',
        'min',
        'max',
    ],
    template: '<input class="mt-number-field" type="number" :disabled="disabled" />',
};

function createWrapper(privileges: string[] = []) {
    return mount(component, {
        props: { searchBehaviourConfigs: { andLogic: true, minSearchLength: 2 } },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-card': { template: '<section><slot /></section>' },
                'mt-radio-group-root': radioRoot,
                'mt-radio-group-list': { template: '<div><slot /></div>' },
                'mt-radio-group-item': {
                    props: [
                        'value',
                        'label',
                    ],
                    template: '<label><input type="radio" />{{ label }}</label>',
                },
                'mt-number-field': numberField,
            },
            provide: { acl: { can: (identifier: string) => privileges.includes(identifier) } },
        },
    });
}

describe('ct-settings-search-search-behaviour', () => {
    it('disables behaviour and minimum length without editor privilege', () => {
        const wrapper = createWrapper(['blog_search_config.viewer']);
        expect(wrapper.get('.mt-radio-group-root').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.mt-number-field').attributes('disabled')).toBeDefined();
    });

    it('offers broad OR before exact AND search', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm.conditionsOptions).toEqual([
            expect.objectContaining({ value: false, name: 'ct-settings-search.generalTab.labelSearchOrCondition' }),
            expect.objectContaining({ value: true, name: 'ct-settings-search.generalTab.labelSearchAndCondition' }),
        ]);
    });

    it('keeps the upstream minimum and maximum search term limits', () => {
        const wrapper = createWrapper(['blog_search_config.editor']);
        expect(wrapper.vm.min).toBe(1);
        expect(wrapper.vm.max).toBe(20);
        expect(wrapper.getComponent(numberField).props()).toEqual(expect.objectContaining({ min: 1, max: 20 }));
    });
});
