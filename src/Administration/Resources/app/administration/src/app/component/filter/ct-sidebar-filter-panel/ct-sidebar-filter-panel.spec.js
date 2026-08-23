import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

describe('components/ct-sidebar-filter-panel', () => {
    async function createWrapper(activeFilterNumber = 0, resetAll = jest.fn()) {
        return mount(await wrapTestComponent('ct-sidebar-filter-panel', { sync: true }), {
            props: {
                activeFilterNumber,
            },
            global: {
                provide: {
                    registerSidebarItem: jest.fn(),
                },
                stubs: {
                    'ct-sidebar-item': await wrapTestComponent('ct-sidebar-item', { sync: true }),
                    'ct-filter-panel': defineComponent({
                        name: 'SwFilterPanel',
                        setup(_, { expose }) {
                            expose({ resetAll });

                            return () => h('div');
                        },
                    }),
                    'mt-icon': true,
                },
            },
        });
    }

    it('should open the filter panel', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.filterSidebarItem).not.toBeNull();
        const openContentSpy = jest.spyOn(wrapper.vm.filterSidebarItem, 'openContent');

        wrapper.vm.openFilterPanel();

        expect(openContentSpy).toHaveBeenCalledTimes(1);
    });

    it('should reset all active filters', async () => {
        const resetAllSpy = jest.fn();
        const wrapper = await createWrapper(1, resetAllSpy);
        await flushPromises();

        wrapper.vm.filterSidebarItem.sidebarButtonClick(wrapper.vm.filterSidebarItem);
        await wrapper.vm.$nextTick();

        await wrapper.find('a').trigger('click');

        expect(resetAllSpy).toHaveBeenCalledTimes(1);
    });
});
