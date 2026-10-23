/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import swSettingsFrontendIndex from './index';

Contena.Component.register('sw-settings-frontend-index', swSettingsFrontendIndex);

describe('sw-settings-frontend-index', () => {
    async function createWrapper({ getValues = null, saveValues = null } = {}) {
        const component = await Contena.Component.build('sw-settings-frontend-index');
        component.methods.createdComponent = jest.fn();

        return shallowMount(component, {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                            <div>
                                <slot name="smart-bar-actions"></slot>
                                <slot name="content"></slot>
                            </div>
                        `,
                    },
                    'sw-button-process': {
                        props: ['processSuccess'],
                        emits: ['click', 'update:processSuccess'],
                        template: `
                            <button class="sw-button-process" @click="$emit('click')"><slot></slot></button>
                        `,
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-skeleton': true,
                    'mt-card': {
                        template: '<div><slot></slot><slot name="toolbar"></slot></div>',
                    },
                    'mt-switch': {
                        name: 'mt-switch',
                        props: [
                            'modelValue',
                            'inheritedValue',
                            'disabled',
                            'isInheritanceField',
                            'isInherited',
                        ],
                        template: '<div class="mt-switch"></div>',
                    },
                    'sw-inherit-wrapper': {
                        props: ['value', 'inheritedValue', 'hasParent'],
                        emits: ['update:value'],
                        computed: {
                            isInherited() {
                                return this.hasParent && (this.value === null || this.value === undefined);
                            },
                            currentValue() {
                                return this.isInherited ? this.inheritedValue : this.value;
                            },
                        },
                        methods: {
                            updateCurrentValue(value) {
                                this.$emit('update:value', value);
                            },
                            restoreInheritance() {
                                this.$emit('update:value', null);
                            },
                            removeInheritance() {
                                this.$emit('update:value', this.currentValue);
                            },
                        },
                        template: `
                            <div class="sw-inherit-wrapper">
                                <slot
                                    name="content"
                                    v-bind="{
                                        currentValue,
                                        updateCurrentValue,
                                        isInherited,
                                        isInheritField: hasParent,
                                        restoreInheritance,
                                        removeInheritance
                                    }"
                                ></slot>
                            </div>
                        `,
                    },
                    'sw-sales-channel-switch': true,
                    'mt-icon': true,
                },
                provide: {
                    systemConfigApiService: {
                        getValues: getValues || jest.fn(() => Promise.resolve({})),
                        saveValues: saveValues || jest.fn(() => Promise.resolve()),
                    },
                },
                mocks: {
                    $createTitle: jest.fn(() => 'title'),
                },
            },
        });
    }

    it('loads default frontend settings when config is empty', async () => {
        const getValues = jest.fn(() => Promise.resolve({}));
        const wrapper = await createWrapper({ getValues });

        await wrapper.vm.loadPageContent();

        expect(wrapper.vm.frontendSettings).toEqual({
            'core.frontendSettings.iconCache': true,
            'core.frontendSettings.asyncThemeCompilation': false,
            'core.frontendSettings.speculationRules': false,
        });
        expect(wrapper.vm.currentSalesChannelFrontendSettings).toEqual({
            'core.frontendSettings.iconCache': true,
            'core.frontendSettings.asyncThemeCompilation': false,
            'core.frontendSettings.speculationRules': false,
        });
        expect(getValues).toHaveBeenCalledWith('core.frontendSettings');
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('loads stored default settings', async () => {
        const stored = {
            'core.frontendSettings.iconCache': false,
            'core.frontendSettings.asyncThemeCompilation': true,
            'core.frontendSettings.speculationRules': true,
        };
        const wrapper = await createWrapper({
            getValues: jest.fn(() => Promise.resolve(stored)),
        });

        await wrapper.vm.loadPageContent();

        expect(wrapper.vm.frontendSettings).toEqual({
            'core.frontendSettings.iconCache': false,
            'core.frontendSettings.asyncThemeCompilation': true,
            'core.frontendSettings.speculationRules': true,
        });
        expect(wrapper.vm.currentSalesChannelFrontendSettings).toEqual({
            'core.frontendSettings.iconCache': false,
            'core.frontendSettings.asyncThemeCompilation': true,
            'core.frontendSettings.speculationRules': true,
        });
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('loads selected sales channel settings as inheritable values', async () => {
        const getValues = jest.fn((_domain, channelId = null) => {
            if (channelId === 'sales-channel-id') {
                return Promise.resolve({
                    'core.frontendSettings.iconCache': false,
                });
            }

            return Promise.resolve({});
        });
        const wrapper = await createWrapper({ getValues });

        await wrapper.vm.loadPageContent();
        await wrapper.vm.onSalesChannelChanged('sales-channel-id');

        expect(wrapper.vm.selectedSalesChannelId).toBe('sales-channel-id');
        expect(wrapper.vm.isGlobalConfig).toBe(false);
        expect(wrapper.vm.currentSalesChannelFrontendSettings).toEqual({
            'core.frontendSettings.iconCache': false,
            'core.frontendSettings.speculationRules': null,
        });
    });

    it('passes inherited global toggle values to sales channel switches', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            selectedSalesChannelId: 'sales-channel-id',
            frontendSettings: {
                'core.frontendSettings.iconCache': true,
                'core.frontendSettings.asyncThemeCompilation': false,
                'core.frontendSettings.speculationRules': true,
            },
            channelFrontendSettings: {
                'core.frontendSettings.iconCache': null,
                'core.frontendSettings.speculationRules': null,
            },
        });

        const switches = wrapper.findAllComponents({ name: 'mt-switch' });

        expect(switches.at(0).props()).toEqual(
            expect.objectContaining({
                disabled: true,
                inheritedValue: true,
                isInherited: true,
                modelValue: true,
            }),
        );
        expect(switches.at(1).props()).toEqual(
            expect.objectContaining({
                disabled: true,
                inheritedValue: true,
                isInherited: true,
                modelValue: true,
            }),
        );
    });

    it('normalizes empty values before saving default scoped and global settings', async () => {
        const saveValues = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ saveValues });

        wrapper.vm.frontendSettings = {
            'core.frontendSettings.iconCache': '',
            'core.frontendSettings.asyncThemeCompilation': '',
            'core.frontendSettings.speculationRules': '',
        };

        await wrapper.vm.saveFrontendSettings();

        expect(saveValues).toHaveBeenCalledWith({
            'core.frontendSettings.asyncThemeCompilation': false,
        });
        expect(saveValues).toHaveBeenCalledWith(
            {
                'core.frontendSettings.iconCache': true,
                'core.frontendSettings.speculationRules': false,
            },
            null,
        );
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    /**
     * @deprecated tag:v6.8.0 - This test will be removed with `loadfrontendSettings`.
     */
    it('keeps loadPageContent delegated to the deprecated loadfrontendSettings method', async () => {
        const wrapper = await createWrapper();
        const loadSpy = jest.spyOn(wrapper.vm, 'loadfrontendSettings').mockImplementation(() => Promise.resolve());

        await wrapper.vm.loadPageContent();

        expect(loadSpy).toHaveBeenCalled();
    });

    /**
     * @deprecated tag:v6.8.0 - This test will be removed with `savefrontendSettings`.
     */
    it('keeps saveFrontendSettings delegated to the deprecated savefrontendSettings method', async () => {
        const wrapper = await createWrapper();
        const saveSpy = jest.spyOn(wrapper.vm, 'savefrontendSettings').mockImplementation(() => Promise.resolve());

        await wrapper.vm.saveFrontendSettings();

        expect(saveSpy).toHaveBeenCalled();
    });

    it('keeps inheritance values when saving selected sales channel settings', async () => {
        const saveValues = jest.fn(() => Promise.resolve());
        const wrapper = await createWrapper({ saveValues });

        wrapper.vm.selectedSalesChannelId = 'sales-channel-id';
        wrapper.vm.frontendSettings = {
            'core.frontendSettings.iconCache': true,
            'core.frontendSettings.asyncThemeCompilation': true,
            'core.frontendSettings.speculationRules': false,
        };
        wrapper.vm.channelFrontendSettings = {
            'core.frontendSettings.iconCache': null,
            'core.frontendSettings.speculationRules': '',
        };

        await wrapper.vm.saveFrontendSettings();

        expect(saveValues).toHaveBeenCalledWith({
            'core.frontendSettings.asyncThemeCompilation': true,
        });
        expect(saveValues).toHaveBeenCalledWith(
            {
                'core.frontendSettings.iconCache': null,
                'core.frontendSettings.speculationRules': null,
            },
            'sales-channel-id',
        );
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('resets the save button after the success animation without reloading the page', async () => {
        const wrapper = await createWrapper();
        const loadSpy = jest.spyOn(wrapper.vm, 'loadPageContent').mockImplementation(() => Promise.resolve());

        wrapper.vm.isSaveSuccessful = true;

        wrapper.getComponent('.sw-button-process').vm.$emit('update:processSuccess', false);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(loadSpy).not.toHaveBeenCalled();
    });
});
