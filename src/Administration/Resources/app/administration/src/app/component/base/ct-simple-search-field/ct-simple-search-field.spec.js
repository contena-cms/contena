import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-simple-search-field', { sync: true }), {
        props: {
            value: 'search term',
        },
        global: {
            stubs: {
                'ct-text-field': await wrapTestComponent('ct-text-field'),
                'ct-contextual-field': await wrapTestComponent('ct-contextual-field'),
                'ct-block-field': await wrapTestComponent('ct-block-field'),
                'ct-base-field': await wrapTestComponent('ct-base-field'),
                'ct-field-error': await wrapTestComponent('ct-field-error'),
                'icons-small-search': true,
                'ct-field-copyable': await wrapTestComponent('ct-field-copyable'),
                'ct-inheritance-switch': await wrapTestComponent('ct-inheritance-switch'),
                'ct-ai-copilot-badge': await wrapTestComponent('ct-ai-copilot-badge'),
                'ct-help-text': await wrapTestComponent('ct-help-text'),
            },
            provide: {
                validationService: {},
            },
        },
    });
}

describe('components/base/ct-simple-search-field', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should have `search term` as initial value', async () => {
        expect(wrapper.find('input[type="text"]').element.value).toBe('search term');
    });

    it('should emit `input` event', async () => {
        await wrapper.find('input[type="text"]').setValue('@input Sw Simple Search Field Typing');

        /* wait for `$emit('input')` */
        await wrapper.vm.$nextTick();
        expect(wrapper.emitted().input).toBeTruthy();
    });
});
