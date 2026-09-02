import { mount } from '@vue/test-utils';
import { routeLocationKey } from 'vue-router';
import SitemapComponent from './ct-settings-sitemap.vue';

const saveAllMock = jest.fn<Promise<void>, []>();

function createWrapper() {
    return mount(SitemapComponent, {
        global: {
            provide: {
                [routeLocationKey as symbol]: {
                    meta: {
                        $module: {
                            title: 'ct-settings-sitemap.general.mainMenuItemGeneral',
                        },
                    },
                },
            },
            stubs: {
                'ct-page': {
                    name: 'CtPageStub',
                    props: {
                        showSearchBar: Boolean,
                    },
                    template:
                        '<div><slot name="smart-bar-header" /><slot name="smart-bar-actions" /><slot name="content" /></div>',
                },
                'ct-card-view': {
                    template: '<div class="ct-card-view"><slot /></div>',
                },
                'ct-skeleton': true,
                'ct-system-config': {
                    name: 'CtSystemConfigStub',
                    props: {
                        channelSwitchable: Boolean,
                        domain: String,
                    },
                    methods: {
                        saveAll: saveAllMock,
                    },
                    template: '<div class="ct-system-config" />',
                },
            },
        },
    });
}

describe('module/ct-settings-sitemap/page/ct-settings-sitemap', () => {
    beforeEach(() => {
        saveAllMock.mockReset();
        saveAllMock.mockResolvedValue();
    });

    it('loads the channel-aware sitemap configuration', () => {
        const wrapper = createWrapper();
        const systemConfig = wrapper.findComponent({ name: 'CtSystemConfigStub' });
        const page = wrapper.findComponent({ name: 'CtPageStub' });

        expect(systemConfig.props('domain')).toBe('core.sitemap');
        expect(systemConfig.props('channelSwitchable')).toBe(true);
        expect(page.props('showSearchBar')).toBe(true);
    });

    it('finishes saving the sitemap configuration', async () => {
        const wrapper = createWrapper();
        const vm = wrapper.vm as unknown as {
            onSave: () => Promise<void>;
            isSaveSuccessful: boolean;
            isLoading: boolean;
        };

        await vm.onSave();

        expect(saveAllMock).toHaveBeenCalledTimes(1);
        expect(vm.isSaveSuccessful).toBe(true);
        expect(vm.isLoading).toBe(false);
    });

    it('resets loading state when saving fails', async () => {
        saveAllMock.mockRejectedValueOnce(new Error('Could not save sitemap configuration.'));
        const wrapper = createWrapper();
        const vm = wrapper.vm as unknown as {
            onSave: () => Promise<void>;
            isSaveSuccessful: boolean;
            isLoading: boolean;
        };

        await vm.onSave();

        expect(vm.isSaveSuccessful).toBe(false);
        expect(vm.isLoading).toBe(false);
    });
});
