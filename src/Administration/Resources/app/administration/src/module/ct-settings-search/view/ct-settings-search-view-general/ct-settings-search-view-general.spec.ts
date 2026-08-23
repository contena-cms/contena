import { mount } from '@vue/test-utils';
import component from './ct-settings-search-view-general.vue';

describe('ct-settings-search-view-general', () => {
    it('forwards the Blog config id and reload event to all three upstream cards', () => {
        const wrapper = mount(component, {
            props: { blogSearchConfigs: { id: 'config' } as Entity<'blog_search_config'> },
            global: {
                stubs: {
                    'ct-block': { template: '<div><slot /></div>' },
                    'ct-settings-search-search-behaviour': true,
                    'ct-settings-search-searchable-content': true,
                    'ct-settings-search-excluded-search-terms': true,
                },
            },
        });
        const vm = wrapper.vm as unknown as { searchConfigId: string; loadData: () => void };
        expect(vm.searchConfigId).toBe('config');
        vm.loadData();
        expect(wrapper.emitted('excluded-search-terms-load')).toBeTruthy();
    });
});
