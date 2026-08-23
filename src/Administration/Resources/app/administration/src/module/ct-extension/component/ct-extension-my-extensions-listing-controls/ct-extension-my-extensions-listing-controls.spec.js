import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from '../../../../../test/_helper_/select-mt-select-by-text';

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('ct-extension-my-extensions-listing-controls', {
            sync: true,
        }),
        { props },
    );
}

describe('src/module/ct-extension/component/ct-extension-my-extensions-listing-controls', () => {
    it('should emit an event when clicking the switch', async () => {
        const wrapper = await createWrapper();

        const switchField = wrapper.find('.mt-switch input[type="checkbox"]');
        await switchField.setChecked();

        const emittedEvent = wrapper.emitted()['update:active-state'];
        expect(emittedEvent).toBeTruthy();
    });

    it('should emit an event selecting a different option', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.selectedSortingOption).toBe('updated-at');

        expect(wrapper.vm.sortingOptions.map(({ value }) => value)).toEqual([
            'updated-at',
            'name-asc',
            'name-desc',
        ]);

        await selectMtSelectOptionByText(
            wrapper,
            'ct-extension.my-extensions.listing.controls.filterOptions.name-asc',
            '.mt-select__selection',
        );

        expect(wrapper.vm.selectedSortingOption).toBe('name-asc');
        expect(wrapper.emitted()).toHaveProperty('update:sorting-option');
    });

    it('should initialize the selected sorting option from the prop', async () => {
        const wrapper = await createWrapper({ sortingOption: 'name-asc' });

        expect(wrapper.vm.selectedSortingOption).toBe('name-asc');
    });
});
