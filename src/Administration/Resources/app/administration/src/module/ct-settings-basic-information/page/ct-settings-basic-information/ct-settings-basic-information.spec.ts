import { mount } from '@vue/test-utils';
import { routeLocationKey } from 'vue-router';
import BasicInformation from './ct-settings-basic-information.vue';

const saveAllMock = jest.fn<Promise<void>, []>();

function createWrapper() {
    return mount(BasicInformation, {
        global: {
            provide: {
                [routeLocationKey as symbol]: {
                    meta: {
                        $module: {
                            title: 'ct-settings-basic-information.general.mainMenuItemGeneral',
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

describe('module/ct-settings-basic-information/page/ct-settings-basic-information', () => {
    beforeEach(() => {
        saveAllMock.mockReset();
        saveAllMock.mockResolvedValue();
    });

    it('loads the channel-aware basic information configuration', () => {
        const wrapper = createWrapper();
        const systemConfig = wrapper.findComponent({ name: 'CtSystemConfigStub' });
        const page = wrapper.findComponent({ name: 'CtPageStub' });

        expect(systemConfig.props('domain')).toBe('core.basicInformation');
        expect(systemConfig.props('channelSwitchable')).toBe(true);
        expect(page.props('showSearchBar')).toBe(true);
    });

    it('finishes saving the basic information configuration', async () => {
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
        saveAllMock.mockRejectedValueOnce(new Error('Could not save basic information.'));
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
