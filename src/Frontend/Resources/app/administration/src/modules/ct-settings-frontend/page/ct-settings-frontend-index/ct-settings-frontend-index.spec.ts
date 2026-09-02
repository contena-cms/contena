import { flushPromises, mount } from '@vue/test-utils';
import type { PropType } from 'vue';
import { computed, defineComponent } from 'vue';
import { routeLocationKey } from 'vue-router';
import FrontendSettingsPage from './ct-settings-frontend-index.vue';

type Settings = Record<string, boolean | null | ''>;

interface FrontendSettingsPageVm {
    isLoading: boolean;
    isSaveSuccessful: boolean;
    selectedChannelId: string | null;
    frontendSettings: Settings;
    channelFrontendSettings: Settings;
    isGlobalConfig: boolean;
    currentChannelFrontendSettings: Settings;
    saveFrontendSettings: () => Promise<void>;
    onChannelChanged: (channelId?: string | null) => Promise<void>;
}

interface WrapperOptions {
    getValues?: jest.Mock;
    saveValues?: jest.Mock;
}

const InheritWrapperStub = defineComponent({
    props: {
        value: {
            type: [
                Boolean,
                String,
            ] as PropType<boolean | null | ''>,
            default: null,
        },
        inheritedValue: {
            type: [
                Boolean,
                String,
            ] as PropType<boolean | null | ''>,
            default: null,
        },
        hasParent: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:value'],
    setup(props, { emit }) {
        const isInherited = computed(() => props.hasParent && props.value === null);
        const currentValue = computed(() => (isInherited.value ? props.inheritedValue : props.value));
        const updateCurrentValue = (value: boolean | null): void => emit('update:value', value);
        const restoreInheritance = (): void => emit('update:value', null);
        const removeInheritance = (): void => emit('update:value', currentValue.value);

        return {
            currentValue,
            isInherited,
            updateCurrentValue,
            restoreInheritance,
            removeInheritance,
        };
    },
    template: `
        <div>
            <slot
                name="content"
                v-bind="{
                    currentValue,
                    updateCurrentValue,
                    isInherited,
                    isInheritField: hasParent,
                    restoreInheritance,
                    removeInheritance,
                }"
            />
        </div>
    `,
});

async function createWrapper({ getValues, saveValues }: WrapperOptions = {}) {
    const getValuesMock = getValues ?? jest.fn().mockResolvedValue({});
    const saveValuesMock = saveValues ?? jest.fn().mockResolvedValue(undefined);
    const wrapper = mount(FrontendSettingsPage, {
        global: {
            provide: {
                [routeLocationKey as symbol]: {
                    meta: {
                        $module: {
                            title: 'ct-settings-frontend.general.mainMenuItemGeneral',
                        },
                    },
                },
                systemConfigApiService: {
                    getValues: getValuesMock,
                    saveValues: saveValuesMock,
                },
            },
            stubs: {
                'ct-page': {
                    name: 'CtPageStub',
                    props: { showSearchBar: Boolean },
                    template:
                        '<div><slot name="smart-bar-header" /><slot name="smart-bar-actions" /><slot name="content" /></div>',
                },
                'ct-card-view': {
                    template: '<div><slot /></div>',
                },
                'ct-skeleton': true,
                'ct-channel-switch': true,
                'mt-icon': true,
                'mt-button': {
                    emits: ['click'],
                    template: '<button @click="$emit(\'click\')"><slot /></button>',
                },
                'mt-card': {
                    props: [
                        'positionIdentifier',
                        'title',
                    ],
                    template: '<div><slot name="toolbar" /><slot /></div>',
                },
                'mt-switch': {
                    name: 'MtSwitchStub',
                    props: [
                        'modelValue',
                        'inheritedValue',
                        'disabled',
                        'isInheritanceField',
                        'isInherited',
                    ],
                    template: '<div class="mt-switch" />',
                },
                'ct-inherit-wrapper': InheritWrapperStub,
            },
        },
    });

    await flushPromises();

    return { wrapper, getValuesMock, saveValuesMock };
}

describe('modules/ct-settings-frontend/page/ct-settings-frontend-index', () => {
    it('loads default frontend settings when config is empty', async () => {
        const { wrapper, getValuesMock } = await createWrapper();
        const vm = wrapper.vm as unknown as FrontendSettingsPageVm;

        expect(vm.frontendSettings).toEqual({
            'core.frontendSettings.iconCache': true,
            'core.frontendSettings.asyncThemeCompilation': false,
            'core.frontendSettings.speculationRules': false,
        });
        expect(vm.currentChannelFrontendSettings).toEqual(vm.frontendSettings);
        expect(getValuesMock).toHaveBeenCalledWith('core.frontendSettings');
        expect(vm.isLoading).toBe(false);
    });

    it('loads stored default settings', async () => {
        const stored = {
            'core.frontendSettings.iconCache': false,
            'core.frontendSettings.asyncThemeCompilation': true,
            'core.frontendSettings.speculationRules': true,
        };
        const { wrapper } = await createWrapper({ getValues: jest.fn().mockResolvedValue(stored) });
        const vm = wrapper.vm as unknown as FrontendSettingsPageVm;

        expect(vm.frontendSettings).toEqual(stored);
        expect(vm.currentChannelFrontendSettings).toEqual(stored);
        expect(vm.isLoading).toBe(false);
    });

    it('loads selected channel settings as inheritable values', async () => {
        const getValues = jest.fn((_domain: string, channelId: string | null = null) => {
            if (channelId === 'channel-id') {
                return Promise.resolve({
                    'core.frontendSettings.iconCache': false,
                });
            }

            return Promise.resolve({});
        });
        const { wrapper } = await createWrapper({ getValues });
        const vm = wrapper.vm as unknown as FrontendSettingsPageVm;

        await vm.onChannelChanged('channel-id');

        expect(vm.selectedChannelId).toBe('channel-id');
        expect(vm.isGlobalConfig).toBe(false);
        expect(vm.currentChannelFrontendSettings).toEqual({
            'core.frontendSettings.iconCache': false,
            'core.frontendSettings.speculationRules': null,
        });
    });

    it('passes inherited global toggle values to channel switches', async () => {
        const { wrapper } = await createWrapper();
        const vm = wrapper.vm as unknown as FrontendSettingsPageVm;

        vm.selectedChannelId = 'channel-id';
        Object.assign(vm.frontendSettings, {
            'core.frontendSettings.iconCache': true,
            'core.frontendSettings.asyncThemeCompilation': false,
            'core.frontendSettings.speculationRules': true,
        });
        Object.assign(vm.channelFrontendSettings, {
            'core.frontendSettings.iconCache': null,
            'core.frontendSettings.speculationRules': null,
        });
        await wrapper.vm.$nextTick();

        const switches = wrapper.findAllComponents({ name: 'MtSwitchStub' });

        expect(switches.at(0)?.props()).toEqual(
            expect.objectContaining({
                disabled: true,
                inheritedValue: true,
                isInherited: true,
                modelValue: true,
            }),
        );
        expect(switches.at(1)?.props()).toEqual(
            expect.objectContaining({
                disabled: true,
                inheritedValue: true,
                isInherited: true,
                modelValue: true,
            }),
        );
    });

    it('normalizes empty values before saving default-scoped and global settings', async () => {
        const saveValues = jest.fn().mockResolvedValue(undefined);
        const { wrapper } = await createWrapper({ saveValues });
        const vm = wrapper.vm as unknown as FrontendSettingsPageVm;

        Object.assign(vm.frontendSettings, {
            'core.frontendSettings.iconCache': '',
            'core.frontendSettings.asyncThemeCompilation': '',
            'core.frontendSettings.speculationRules': '',
        });

        await vm.saveFrontendSettings();

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
        expect(vm.isSaveSuccessful).toBe(true);
        expect(vm.isLoading).toBe(false);
    });

    it('keeps inheritance values when saving selected channel settings', async () => {
        const saveValues = jest.fn().mockResolvedValue(undefined);
        const { wrapper } = await createWrapper({ saveValues });
        const vm = wrapper.vm as unknown as FrontendSettingsPageVm;

        vm.selectedChannelId = 'channel-id';
        Object.assign(vm.frontendSettings, {
            'core.frontendSettings.iconCache': true,
            'core.frontendSettings.asyncThemeCompilation': true,
            'core.frontendSettings.speculationRules': false,
        });
        Object.assign(vm.channelFrontendSettings, {
            'core.frontendSettings.iconCache': null,
            'core.frontendSettings.speculationRules': '',
        });

        await vm.saveFrontendSettings();

        expect(saveValues).toHaveBeenCalledWith({
            'core.frontendSettings.asyncThemeCompilation': true,
        });
        expect(saveValues).toHaveBeenCalledWith(
            {
                'core.frontendSettings.iconCache': null,
                'core.frontendSettings.speculationRules': null,
            },
            'channel-id',
        );
        expect(vm.isLoading).toBe(false);
    });
});
