import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('ct-settings-media', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: {
                        params: {},
                        meta: {
                            $module: {
                                icon: 'default-symbol-content',
                            },
                        },
                    },
                },
                stubs: {
                    'ct-page': await wrapTestComponent('ct-page'),
                    'ct-card-view': await wrapTestComponent('ct-card-view'),
                    'ct-button-process': await wrapTestComponent('ct-button-process'),
                    'ct-skeleton': true,
                    'ct-system-config': await wrapTestComponent('ct-system-config'),
                    'ct-search-bar': true,
                    'ct-loader': true,
                    'ct-ignore-class': true,
                    'ct-extension-component-section': true,
                    'ct-error-summary': true,
                    'mt-slider': true,
                    'ct-app-topbar-button': true,
                    'ct-app-topbar-sidebar': true,
                    'ct-notification-center': true,
                    'ct-help-center-v2': true,
                    'router-link': true,
                    'ct-app-actions': true,
                    'ct-channel-switch': true,
                    'ct-context-menu-item': true,
                    'ct-form-field-renderer': true,
                    'ct-inherit-wrapper': true,
                    'ct-ai-copilot-badge': true,
                    'ct-context-button': true,
                },
                provide: {
                    systemConfigApiService: {
                        getConfig: () => {
                            return Promise.resolve([
                                {
                                    title: {
                                        'en-GB': '3D Files',
                                    },
                                    name: null,
                                    elements: [
                                        {
                                            name: 'core.media.defaultEnableAugmentedReality',
                                            type: 'bool',
                                            config: {
                                                label: {
                                                    'en-GB': 'enableAugmentedRealityDefault',
                                                },
                                                helpText: {
                                                    'en-GB': 'enableAugmentedRealityDefault.helptext',
                                                },
                                            },
                                        },
                                    ],
                                },
                            ]);
                        },
                        getValues: () => {
                            return Promise.resolve({
                                'core.media.defaultEnableAugmentedReality': false,
                            });
                        },
                    },
                },
            },
        },
    );
}

describe('module/ct-settings-media/page/ct-settings-media', () => {
    it('should save system config failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$refs.systemConfig.saveAll = jest.fn(() => {
            return Promise.reject(new Error('Oops!'));
        });

        await wrapper.vm.onSave();
        await flushPromises();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
    });

    it('should finish saving correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$refs.systemConfig.saveAll = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.vm.onSave();
        await flushPromises();

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should finish saving failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.isSaveSuccessful = true;

        wrapper.vm.saveFinish();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should contain the settings card', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.ct-card-view').find('.ct-system-config').find('.mt-card').exists()).toBeTruthy();
    });
});
