import { mount } from '@vue/test-utils';

const { Criteria } = Contena.Data;

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('ct-number-filter', { sync: true }), {
        global: {
            stubs: {
                'ct-base-filter': await wrapTestComponent('ct-base-filter', { sync: true }),
                'ct-range-filter': await wrapTestComponent('ct-range-filter', { sync: true }),
                'ct-text-field': await wrapTestComponent('ct-text-field', {
                    sync: true,
                }),
                'ct-contextual-field': await wrapTestComponent('ct-contextual-field', { sync: true }),
                'ct-block-field': await wrapTestComponent('ct-block-field', { sync: true }),
                'ct-base-field': await wrapTestComponent('ct-base-field', {
                    sync: true,
                }),
                'ct-container': {
                    template: '<div class="ct-container"><slot></slot></div>',
                },
                'ct-field-error': {
                    template: '<div></div>',
                },
                'ct-field-copyable': true,
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
            },
            provide: {
                validationService: {},
            },
        },
        props: {
            filter: {
                property: 'stock',
                name: 'stock',
                label: 'Stock',
                numberType: 'int',
                numberStep: 1,
                numberMin: 0,
            },
            active: true,
        },
    });
    await flushPromises();

    const inputFrom = wrapper.findByLabel('global.default.from');
    const inputTo = wrapper.findByLabel('global.default.to');

    return { wrapper, inputFrom, inputTo };
}

describe('components/ct-number-filter', () => {
    it('should emit `filter-update` event when user input `From` field', async () => {
        const { wrapper, inputFrom } = await createWrapper();

        // type "2"
        await inputFrom.setValue('2');
        await inputFrom.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2 })],
            { from: 2, to: null },
        ]);
    });

    it('should emit `filter-update` event when user input `To` field', async () => {
        const { wrapper, inputTo } = await createWrapper();

        // type "5"
        await inputTo.setValue('5');
        await inputTo.trigger('change');

        expect(wrapper.emitted('filter-update')[0]).toEqual([
            'stock',
            [Criteria.range('stock', { lte: 5 })],
            { from: null, to: 5 },
        ]);
    });

    it('should emit `filter-update` event when user input `From` field and `To` field', async () => {
        const { wrapper, inputFrom, inputTo } = await createWrapper();

        await inputFrom.setValue('2');
        await inputFrom.trigger('change');

        expect(wrapper.emitted('filter-update')[0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2 })],
            { from: 2, to: null },
        ]);

        await inputTo.setValue('5');
        await inputTo.trigger('change');

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2, lte: 5 })],
            { from: 2, to: 5 },
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button when from value exists', async () => {
        const { wrapper, inputFrom } = await createWrapper();

        // type "2"
        await inputFrom.setValue('2');
        await inputFrom.trigger('change');

        // Trigger click Reset button
        await wrapper.find('.ct-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user clicks Reset button when to value exists', async () => {
        const { wrapper, inputTo } = await createWrapper();

        // type "5"
        await inputTo.setValue('5');
        await inputTo.trigger('change');

        // Trigger click Reset button
        await wrapper.find('.ct-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-update` event when user input both `From` and `To` fields with value 0', async () => {
        const { wrapper, inputFrom, inputTo } = await createWrapper();

        // type "0" in From field
        await inputFrom.setValue('0');
        await inputFrom.trigger('change');

        expect(wrapper.emitted('filter-update')[0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 0 })],
            { from: 0, to: null },
        ]);

        // type "0" in To field
        await inputTo.setValue('0');
        await inputTo.trigger('change');

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 0, lte: 0 })],
            { from: 0, to: 0 },
        ]);
    });
});
