import 'src/app/component/filter/ct-range-filter';
import 'src/app/component/filter/ct-base-filter';
import { mount } from '@vue/test-utils';

const { Criteria } = Contena.Data;

async function createWrapper() {
    return mount(await wrapTestComponent('ct-range-filter', { sync: true }), {
        global: {
            stubs: {
                'ct-base-filter': await wrapTestComponent('ct-base-filter', {
                    sync: true,
                }),
                'ct-container': {
                    template: '<div class="ct-container"><slot></slot></div>',
                },
                'ct-field-error': true,
            },
        },
        props: {
            isShowDivider: true,
            value: {
                from: null,
                to: null,
            },
            active: true,
            property: 'releaseDate',
            showResetButton: false,
            title: 'Release Date',
        },
    });
}

describe('src/app/component/filter/ct-range-filter', () => {
    it('should emit `filter-update` event when `From` value exits', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            value: {
                from: '2021-01-20',
                to: null,
            },
        });

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            [Criteria.range('releaseDate', { gte: '2021-01-20' })],
        ]);
    });

    it('should emit `filter-update` event when `To` value exits', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            value: {
                from: null,
                to: '2021-01-23',
            },
        });

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            [Criteria.range('releaseDate', { lte: '2021-01-23' })],
        ]);
    });

    it('should emit `filter-update` event when `From` and `To` value exits', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            value: {
                from: '2021-01-20',
                to: '2021-01-23',
            },
        });

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            [
                Criteria.range('releaseDate', {
                    gte: '2021-01-20',
                    lte: '2021-01-23',
                }),
            ],
        ]);
    });

    it('should render From field and To field on the same line', async () => {
        const wrapper = await createWrapper();

        const container = wrapper.find('.ct-container');
        const divider = wrapper.find('.ct-range-filter__divider');

        expect(divider.exists()).toBeTruthy();
        expect(container.classes()).toContain('ct-container--has-divider');
    });

    it('should render From field and To field in different line', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            isShowDivider: false,
        });

        const container = wrapper.find('.ct-container');
        const divider = wrapper.find('.ct-range-filter__divider');

        expect(divider.exists()).toBeFalsy();
        expect(container.classes()).not.toContain('ct-container--has-divider');
    });

    it('should emit `filter-update` event when `From` and `To` values are both 0', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            value: {
                from: 0,
                to: 0,
            },
            property: 'stock',
        });

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            [Criteria.range('stock', { gte: 0, lte: 0 })],
        ]);
    });
});
