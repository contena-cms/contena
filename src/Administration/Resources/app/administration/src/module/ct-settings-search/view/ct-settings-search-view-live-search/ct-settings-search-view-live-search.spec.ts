import { mount } from '@vue/test-utils';
import component from './ct-settings-search-view-live-search.vue';

describe('ct-settings-search-view-live-search', () => {
    it('shows the Blog index card when external Frontend search is disabled', () => {
        Object.assign(Contena.Context.app, { storefrontEsEnable: false });
        const wrapper = mount(component, {
            global: {
                stubs: {
                    'ct-block': { template: '<div><slot /></div>' },
                    'ct-settings-search-search-index': { template: '<div class="index" />' },
                    'ct-settings-search-live-search': true,
                },
            },
        });
        expect(wrapper.vm.frontendEsEnable).toBe(false);
        expect(wrapper.find('.index').exists()).toBe(true);
    });

    it('hides the local rebuild card when external Frontend search is enabled', () => {
        Object.assign(Contena.Context.app, { storefrontEsEnable: true });
        const wrapper = mount(component, {
            global: {
                stubs: {
                    'ct-block': { template: '<div><slot /></div>' },
                    'ct-settings-search-search-index': { template: '<div class="index" />' },
                    'ct-settings-search-live-search': true,
                },
            },
        });
        expect(wrapper.find('.index').exists()).toBe(false);
    });
});
