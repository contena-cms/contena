import { mount } from '@vue/test-utils';

const createWrapper = async (customOptions) => {
    return mount(await wrapTestComponent('ct-media-display-options', { sync: true }), {
        global: {
            stubs: {},
        },
        ...customOptions,
    });
};

describe('src/module/ct-media/component/ct-media-display-options', () => {
    it('should default to created at descending', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.sortingConCat).toBe('createdAt:desc');
    });

    it('should return the correct presentationOptions', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // Click on the presentation dropdown
        const presentationSelect = wrapper.find('.ct-media-display-options__label-presentation');
        await presentationSelect.find('.mt-select__selection').trigger('click');

        // Contains 4 preview options
        const selectResults = wrapper.findAll('.mt-select-result');
        expect(selectResults).toHaveLength(4);
        expect(selectResults[0].text()).toBe('ct-media.presentation.labelPresentationSmall');
        expect(selectResults[1].text()).toBe('ct-media.presentation.labelPresentationMedium');
        expect(selectResults[2].text()).toBe('ct-media.presentation.labelPresentationLarge');
        expect(selectResults[3].text()).toBe('ct-media.presentation.labelPresentationList');
    });

    it('should disable the presentation select when disabled prop is true', async () => {
        const wrapper = await createWrapper({
            props: {
                disabled: true,
            },
        });
        await flushPromises();

        const presentationSelect = wrapper.find('.ct-media-display-options__label-presentation');
        expect(presentationSelect.classes()).toContain('is--disabled');

        const sortSelect = wrapper.find('.ct-media-display-options__label-sort');
        expect(sortSelect.classes()).toContain('is--disabled');
    });

    it('uses dedicated grid and list actions in the inline toolbar', async () => {
        const wrapper = await createWrapper({
            props: {
                inline: true,
                presentation: 'medium-preview',
            },
        });

        const viewButtons = wrapper.findAll('.ct-media-display-options__view-button');
        expect(viewButtons).toHaveLength(2);
        expect(viewButtons[0].classes()).toContain('is--active');

        await viewButtons[1].trigger('click');

        expect(wrapper.emitted('media-presentation-change')).toEqual([['list-preview']]);
    });
});
