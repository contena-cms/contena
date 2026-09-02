import { mount } from '@vue/test-utils';

const deviceMock = {
    onResize: jest.fn(),
    removeResizeListener: jest.fn(),
};

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('ct-settings-snippet-sidebar', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'ct-settings-snippet-filter-switch': true,
                },
                mocks: {
                    $device: deviceMock,
                },
                provide: {
                    setCtPageSidebarOffset: () => {},
                    removeCtPageSidebarOffset: () => {},
                },
            },
            props: {
                filterItems: [],
                authorFilters: [],
                ...props,
            },
        },
    );
}

describe('ct-settings-snippet-sidebar', () => {
    it('should register the open filters shortcut', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$options.shortcuts.OF).toBe('openFilterSidebar');
    });

    it('should open the filter sidebar via the open-filters shortcut', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const openContent = jest.fn();
        wrapper.vm.registerFilterSidebarItem({ isActive: false, openContent });

        wrapper.vm.openFilterSidebar();
        await flushPromises();

        expect(openContent).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('ct-sidebar-open')).toBeTruthy();
    });

    it('should contain a computed property, called: activeFilterNumber', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.activeFilterNumber).toBe(0);

        await wrapper.setProps({
            filterSettings: {
                Contena: true,
                System: true,
            },
        });
        expect(wrapper.vm.activeFilterNumber).toBe(2);
    });

    it('should contain a computed property, called: isExpandedAuthorFilters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.isExpandedAuthorFilters).toBe(false);

        await wrapper.setProps({
            filterSettings: {
                Contena: true,
                System: true,
            },
            authorFilters: [
                'Contena',
                'System',
            ],
        });
        expect(wrapper.vm.isExpandedAuthorFilters).toBe(true);
    });

    it('should contain a computed property, called: isExpandedMoreFilters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.isExpandedMoreFilters).toBe(false);

        await wrapper.setProps({
            filterSettings: {
                blog: true,
                channel: false,
                member: false,
            },
            filterItems: [
                'blog',
                'channel',
                'member',
            ],
        });
        expect(wrapper.vm.isExpandedMoreFilters).toBe(true);
    });

    it('should be able to reset all filters', async () => {
        const wrapper = await createWrapper({
            filterSettings: {
                Contena: true,
                System: true,
            },
        });
        await flushPromises();

        wrapper.vm.openFilterSidebar();
        await flushPromises();

        const resetAllFiltersLink = wrapper.find('.ct-snippet-settings__sidebar-reset-all');
        await resetAllFiltersLink.trigger('click');

        expect(wrapper.emitted('sidebar-reset-all')).toBeTruthy();
    });
});
