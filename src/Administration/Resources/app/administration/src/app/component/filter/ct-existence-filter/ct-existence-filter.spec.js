import 'src/app/component/filter/ct-existence-filter';
import 'src/app/component/filter/ct-base-filter';
import 'src/app/component/form/field-base/ct-block-field';
import 'src/app/component/form/field-base/ct-base-field';
import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from '../../../../../test/_helper_/select-mt-select-by-text';

const { Criteria } = Contena.Data;

async function createWrapper() {
    return mount(await wrapTestComponent('ct-existence-filter', { sync: true }), {
        global: {
            stubs: {
                'ct-block-field': await wrapTestComponent('ct-block-field', { sync: true }),
                'ct-base-field': await wrapTestComponent('ct-base-field', {
                    sync: true,
                }),
                'ct-base-filter': await wrapTestComponent('ct-base-filter', { sync: true }),
                'ct-field-error': {
                    template: '<div></div>',
                },
                'ct-help-text': true,
                'ct-ai-copilot-badge': true,
                'ct-inheritance-switch': true,
                'ct-loader': true,
            },
        },
        props: {
            filter: {
                property: 'media',
                name: 'media',
                label: 'Product without images',
                schema: {
                    localField: 'id',
                },
                optionHasCriteria: true,
                optionNoCriteria: false,
            },
            active: true,
        },
    });
}

describe('components/ct-existence-filter', () => {
    it('should emit `filter-update` event when user changes from unset to `true`', async () => {
        const wrapper = await createWrapper();

        await selectMtSelectOptionByText(wrapper, 'true');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media.id', null)])],
            'true',
        ]);
    });

    it('should emit `filter-update` event when user changes from default option to `false`', async () => {
        const wrapper = await createWrapper();

        await selectMtSelectOptionByText(wrapper, 'false');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.equals('media.id', null)],
            'false',
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button from `true`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: { ...wrapper.vm.filter, value: 'true' },
        });

        // Trigger click Reset button
        await wrapper.find('.ct-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user clicks Reset button from `false`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: { ...wrapper.vm.filter, value: 'false' },
        });

        // Trigger click Reset button
        await wrapper.find('.ct-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-update` event when user changes from `true` to `false`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: { ...wrapper.vm.filter, value: 'true' },
        });

        await selectMtSelectOptionByText(wrapper, 'false');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.equals('media.id', null)],
            'false',
        ]);
    });

    it('should emit `filter-update` event when user changes from `false` to `true`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: { ...wrapper.vm.filter, value: 'false' },
        });

        await selectMtSelectOptionByText(wrapper, 'true');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media.id', null)])],
            'true',
        ]);
    });

    it('should reset the filter value when `active` is false', async () => {
        const wrapper = await createWrapper();

        await selectMtSelectOptionByText(wrapper, 'true');

        await wrapper.setProps({ active: false });

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should not reset the filter value when `active` is true', async () => {
        const wrapper = await createWrapper();

        await selectMtSelectOptionByText(wrapper, 'true');

        await wrapper.setProps({ active: true });

        expect(wrapper.emitted()['filter-reset']).toBeFalsy();
    });

    it('should emit `filter-update` event with correct value when filter has no entity', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'media',
                name: 'media',
                label: 'Product without images',
                optionHasCriteria: 'Has media',
                optionNoCriteria: 'No media',
            },
        });

        await selectMtSelectOptionByText(wrapper, 'Has media');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'media',
            [Criteria.not('AND', [Criteria.equals('media', null)])],
            'true',
        ]);
    });
});
