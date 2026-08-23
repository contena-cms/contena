/**
 */
import { config, shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { createMemoryHistory, createRouter, routeLocationKey, routerKey } from 'vue-router';
import swThemeManagerDetail from './index';

Contena.Component.register('ct-theme-manager-detail', swThemeManagerDetail);

describe('ct-theme-manager-detail', () => {
    async function createWrapper({ aclCan = true, themeServiceOverrides = {}, themeOverrides = {} } = {}) {
        const component = await Contena.Component.build('ct-theme-manager-detail');
        const themeEntity = {
            id: 'theme-id',
            name: 'My theme',
            technicalName: 'MyTheme',
            channels: [],
            configValues: {},
            getOrigin: () => ({ channels: new Map() }),
            ...themeOverrides,
        };

        const themeRepository = {
            schema: { entity: 'theme' },
            get: jest.fn(() => Promise.resolve(themeEntity)),
            search: jest.fn(() => Promise.resolve({ first: () => null })),
            save: jest.fn(() => Promise.resolve()),
            getSyncChangeset: jest.fn(() => ({ changeset: [], deletions: [] })),
        };

        const defaultFolderRepository = {
            search: jest.fn(() =>
                Promise.resolve({
                    first: () => ({ folder: { id: 'default-folder-id' } }),
                }),
            ),
        };

        const channelRepository = {
            search: jest.fn(() =>
                Promise.resolve({
                    getIds: () => ['sc-1'],
                }),
            ),
        };

        const themeService = {
            validateFields: jest.fn(() => Promise.resolve()),
            updateTheme: jest.fn(() => Promise.resolve()),
            assignTheme: jest.fn(() => Promise.resolve()),
            resetTheme: jest.fn(() => Promise.resolve()),
            getStructuredFields: jest.fn(() => Promise.resolve({ tabs: {}, configInheritance: [] })),
            getConfiguration: jest.fn(() =>
                Promise.resolve({
                    currentFields: {},
                    fields: {},
                    baseThemeFields: {},
                }),
            ),
            ...themeServiceOverrides,
        };
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    name: 'ct.theme.manager.detail',
                    path: '/themes/:id',
                    component: { template: '<div />' },
                },
            ],
        });
        await router.push({
            name: 'ct.theme.manager.detail',
            params: { id: 'theme-id' },
        });

        const wrapper = shallowMount(component, {
            global: {
                stubs: {
                    'ct-alert': true,
                    'ct-button-group': true,
                    'ct-button-process': true,
                    'ct-card': true,
                    'ct-card-section': true,
                    'ct-colorpicker': true,
                    'ct-container': true,
                    'ct-context-button': true,
                    'ct-context-menu-item': true,
                    'ct-entity-multi-select': true,
                    'ct-form-field-renderer': true,
                    'ct-icon': true,
                    'ct-inherit-wrapper': true,
                    'ct-media-modal-v2': true,
                    'ct-media-upload-v2': true,
                    'ct-modal': true,
                    'ct-page': {
                        template: `
                            <div class="ct-page">
                                <slot name="search-bar"></slot>
                                <slot name="smart-bar-header"></slot>
                                <slot name="smart-bar-actions"></slot>
                                <slot name="content"></slot>
                                <slot name="sidebar"></slot>
                            </div>
                        `,
                    },
                    'ct-search-bar': true,
                    'ct-select-field': true,
                    'ct-sidebar': true,
                    'ct-sidebar-media-item': true,
                    'ct-skeleton': true,
                    'ct-tabs': {
                        name: 'ct-tabs',
                        template: '<div class="ct-tabs"></div>',
                        emits: ['new-item-active'],
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: false,
                                default: () => [],
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                    },
                    'ct-tabs-item': true,
                    'ct-text-field': true,
                    'ct-upload-listener': true,
                    'ct-url-field': true,
                    'mt-button': true,
                    'mt-card': true,
                    'mt-colorpicker': true,
                    'mt-icon': true,
                    'mt-select': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        template: '<div class="mt-tabs"></div>',
                        emits: ['new-item-active'],
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                    },
                    'mt-text-field': true,
                    'mt-url-field': true,
                },
                provide: {
                    [routerKey]: router,
                    [routeLocationKey]: router.currentRoute.value,
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'theme') {
                                return themeRepository;
                            }
                            if (entity === 'media_default_folder') {
                                return defaultFolderRepository;
                            }
                            if (entity === 'channel') {
                                return channelRepository;
                            }
                            return { get: jest.fn() };
                        },
                    },
                    themeService,
                    acl: {
                        can: jest.fn(() => aclCan),
                    },
                },
                mocks: {
                    $createTitle: jest.fn(() => 'title'),
                },
            },
        });

        await flushPromises();

        return wrapper;
    }

    function setThemeSnippets(messages) {
        const i18n = config.global.plugins.find((plugin) => plugin?.global?.mergeLocaleMessage);
        i18n.global.mergeLocaleMessage('en', messages);
    }

    async function showContentWithTabs(wrapper) {
        setThemeSnippets({
            'ct-theme.MyTheme.default': 'Default',
            'ct-theme.MyTheme.layout': 'Layout',
        });
        wrapper.vm.inheritedSnippetPrefixes = ['MyTheme'];
        wrapper.vm.defaultTheme = {
            id: 'default-theme-id',
            name: 'Frontend',
        };
        wrapper.vm.structuredThemeFields = {
            tabs: {
                default: {
                    labelSnippetKey: 'default',
                    blocks: {},
                },
                layout: {
                    labelSnippetKey: 'layout',
                    blocks: {},
                },
            },
        };

        await nextTick();
    }

    beforeEach(() => {
        Contena.Store.get('session').currentLocale = 'en-GB';
    });

    it('determines derived state based on theme inheritance', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.theme = null;
        expect(wrapper.vm.isDerived).toBe(false);

        wrapper.vm.theme = { technicalName: 'Frontend' };
        expect(wrapper.vm.isDerived).toBe(false);

        wrapper.vm.theme = {
            technicalName: 'Custom',
            baseConfig: { configInheritance: ['@Frontend'] },
        };
        wrapper.vm.parentTheme = null;
        expect(wrapper.vm.isDerived).toBe(true);

        wrapper.vm.theme = {
            technicalName: 'Custom',
            baseConfig: { configInheritance: ['@Other'] },
        };
        expect(wrapper.vm.isDerived).toBe(false);

        wrapper.vm.parentTheme = { id: 'parent' };
        expect(wrapper.vm.isDerived).toBe(true);
    });

    it('should keep default tab first without reordering other tabs', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.getTabLabel = jest.fn((key) => key);

        wrapper.vm.structuredThemeFields = {
            tabs: {
                layout: { labelSnippetKey: 'layout' },
                advanced: { labelSnippetKey: 'advanced' },
                default: { labelSnippetKey: 'default' },
            },
        };

        expect(Object.keys(wrapper.vm.orderedTabs)).toEqual([
            'default',
            'layout',
            'advanced',
        ]);
    });

    it('renders mt-tabs with the item API', async () => {
        const wrapper = await createWrapper();
        await showContentWithTabs(wrapper);

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('defaultItem')).toBe('default');
        expect(tabs.props('positionIdentifier')).toBe('theme-manager-detail-tabs');
        expect(tabs.props('items')).toEqual([
            {
                name: 'default',
                label: 'Default',
            },
            {
                name: 'layout',
                label: 'Layout',
            },
        ]);
        expect(wrapper.findComponent({ name: 'ct-tabs' }).exists()).toBe(false);
    });

    it('hides mt-tabs when only a single tab is available', async () => {
        const wrapper = await createWrapper();
        setThemeSnippets({ 'ct-theme.MyTheme.default': 'Default' });
        wrapper.vm.inheritedSnippetPrefixes = ['MyTheme'];
        wrapper.vm.defaultTheme = {
            id: 'default-theme-id',
            name: 'Frontend',
        };
        wrapper.vm.structuredThemeFields = {
            tabs: {
                default: {
                    labelSnippetKey: 'default',
                    blocks: {},
                },
            },
        };
        await nextTick();

        expect(wrapper.vm.tabItems).toHaveLength(1);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('updates active content when mt-tabs emits a new active item', async () => {
        const wrapper = await createWrapper();
        await showContentWithTabs(wrapper);

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        await tabs.vm.$emit('new-item-active', 'layout');

        expect(wrapper.vm.activeTab).toBe('layout');
    });

    it('sanitizes CSS values', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.cssValue(null)).toBe('');
        expect(wrapper.vm.cssValue('`foo´bar`')).toBe('foobar');
    });

    it('exposes the truncate filter for theme descriptions', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.truncateFilter('123456', 5)).toBe('12...');
    });

    it('builds clean changeset without invalid config entries', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.theme.configValues = { existing: 'value' };
        wrapper.vm.currentThemeConfigInitial = {
            foo: { value: 'a' },
            bar: { value: 'b' },
        };
        wrapper.vm.currentThemeConfig = {
            foo: { value: 'changed' },
            bar: { value: 'b' },
        };
        wrapper.vm.themeConfig = {
            foo: { type: 'text' },
            bar: { type: null },
        };

        const changeset = wrapper.vm.getCurrentChangeset(true);

        expect(changeset).toEqual({
            foo: { value: 'changed' },
        });
    });

    it('removes inherited values from changeset', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.inheritanceChanged = {
            'wrapper-foo': true,
            'wrapper-bar': true,
        };

        const changes = { foo: 'value', bar: 'value', baz: 'value' };
        wrapper.vm.removeInheritedFromChangeset(changes);

        expect(changes).toEqual({ baz: 'value' });
    });

    it('builds field bindings with component-specific config', async () => {
        const wrapper = await createWrapper();
        setThemeSnippets({ 'ct-theme.MyTheme.label.key': 'Translated' });
        wrapper.vm.inheritedSnippetPrefixes = ['MyTheme'];

        const bind = wrapper.vm.getBind({
            type: 'text',
            label: 'Label',
            custom: { componentName: 'ct-text-field' },
        });

        expect(bind).toEqual({
            type: 'text',
            config: expect.objectContaining({
                componentName: 'ct-text-field',
            }),
        });

        const selectBind = wrapper.vm.getBind({
            type: 'select',
            label: 'Label',
            custom: {
                componentName: 'ct-single-select',
                options: [{ labelSnippetKey: 'label.key', label: 'Fallback' }],
            },
        });

        expect(selectBind.config.options[0].label).toBe('Translated');
        expect(selectBind.config.custom).toBeUndefined();
        expect(selectBind.config.componentName).toBe('ct-single-select');
    });

    it('builds meteor inheritance config for fields handling their own label and help text', async () => {
        const wrapper = await createWrapper();
        const removeInheritance = jest.fn();
        const restoreInheritance = jest.fn();

        const bind = wrapper.vm.getBind(
            {
                type: 'checkbox',
                custom: { componentName: 'ct-checkbox-field' },
            },
            {
                isInheritField: true,
                isInherited: true,
                removeInheritance,
                restoreInheritance,
            },
            'parent',
        );

        expect(bind.config).toEqual(
            expect.objectContaining({
                isInheritanceField: true,
                isInherited: true,
                inheritanceRemove: removeInheritance,
                inheritanceRestore: restoreInheritance,
                inheritedValue: 'parent',
            }),
        );
    });

    it('passes inheritance bindings to boolean theme config fields', async () => {
        const wrapper = await createWrapper();
        const inheritance = {
            currentValue: true,
            isInheritField: true,
            isInherited: true,
            removeInheritance: jest.fn(),
            restoreInheritance: jest.fn(),
        };

        const bind = wrapper.vm.getBind(
            {
                type: 'switch',
                label: 'Switch',
                helpText: 'Switch help',
            },
            inheritance,
            false,
        );

        expect(bind).toEqual({
            type: 'switch',
            config: expect.objectContaining({
                label: 'Switch',
                helpText: 'Switch help',
                mapInheritance: inheritance,
                isInheritanceField: true,
                isInherited: true,
                inheritanceRemove: inheritance.removeInheritance,
                inheritanceRestore: inheritance.restoreInheritance,
                inheritedValue: false,
            }),
        });
    });

    it('attaches inheritance event listeners to fields handling inheritance themselves', async () => {
        const wrapper = await createWrapper();
        const inheritance = {
            removeInheritance: jest.fn(),
            restoreInheritance: jest.fn(),
        };

        const eventListeners = wrapper.vm.getElementEventListeners({ type: 'checkbox' }, inheritance);

        expect(eventListeners).toEqual({
            'inheritance-remove': inheritance.removeInheritance,
            'inheritance-restore': inheritance.restoreInheritance,
        });
    });

    it('does not pass inheritance bindings to non-meteor theme config fields', async () => {
        const wrapper = await createWrapper();
        const inheritance = {
            currentValue: 'parent',
            isInheritField: true,
            isInherited: true,
            removeInheritance: jest.fn(),
            restoreInheritance: jest.fn(),
        };

        const textBind = wrapper.vm.getBind({ type: 'text' }, inheritance, 'parent');

        expect(textBind.config.mapInheritance).toBeUndefined();
    });

    it('does not attach inheritance event listeners to regular theme config fields', async () => {
        const wrapper = await createWrapper();
        const inheritance = {
            removeInheritance: jest.fn(),
            restoreInheritance: jest.fn(),
        };

        expect(wrapper.vm.getElementEventListeners({ type: 'text' }, inheritance)).toEqual({});
    });

    it('gets snippets with prefix inheritance and returns null when missing', async () => {
        const wrapper = await createWrapper();
        setThemeSnippets({ 'ct-theme.MyTheme.label.key': 'Translated' });
        wrapper.vm.inheritedSnippetPrefixes = ['MyTheme'];

        expect(wrapper.vm.getSnippet('label.key')).toBe('Translated');
        expect(wrapper.vm.getSnippet('missing.key')).toBeNull();
    });

    it('returns field labels with fallback to field name', async () => {
        const wrapper = await createWrapper();
        setThemeSnippets({ 'ct-theme.MyTheme.foo': 'Name' });
        wrapper.vm.inheritedSnippetPrefixes = ['MyTheme'];

        expect(wrapper.vm.getFieldLabel({ labelSnippetKey: 'foo', label: 'Label' }, 'field')).toBe('Name');

        expect(wrapper.vm.getFieldLabel({ labelSnippetKey: 'missing', label: '' }, 'field')).toBe('field');
    });

    it('returns help text from snippets', async () => {
        const wrapper = await createWrapper();
        setThemeSnippets({ 'ct-theme.MyTheme.foo': 'Help text' });
        wrapper.vm.inheritedSnippetPrefixes = ['MyTheme'];

        expect(wrapper.vm.getHelpText({ helpTextSnippetKey: 'foo', helpText: '' })).toBe('Help text');

        expect(wrapper.vm.getHelpText({ helpTextSnippetKey: 'missing' })).toBeNull();
    });

    it('returns default tab label when snippet is empty', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.getTabLabel('tab.key')).toBe('ct-theme-manager.general.defaultTab');
    });

    it('disables selection when default theme and channel already assigned', async () => {
        const wrapper = await createWrapper({
            themeOverrides: {
                getOrigin: () => ({
                    channels: new Map([
                        [
                            'sc-1',
                            {},
                        ],
                    ]),
                }),
            },
        });
        wrapper.vm.defaultTheme = { id: 'theme-id' };
        wrapper.vm.theme = {
            id: 'theme-id',
            getOrigin: wrapper.vm.theme.getOrigin,
        };

        expect(wrapper.vm.selectionDisablingMethod({ id: 'sc-1' })).toBe(true);

        wrapper.vm.defaultTheme = { id: 'other' };
        expect(wrapper.vm.selectionDisablingMethod({ id: 'sc-1' })).toBe(false);
    });

    it('validates theme fields and handles invalid scss errors', async () => {
        const error = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'THEME__INVALID_SCSS_VAR',
                            detail: 'Bad var',
                        },
                    ],
                },
            },
        };
        const themeService = {
            validateFields: jest.fn(() => Promise.reject(error)),
        };
        const createNotification = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockReturnValue('notification-id');
        const wrapper = await createWrapper({
            themeServiceOverrides: themeService,
        });

        await wrapper.vm.onValidate();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                title: 'ct-theme-manager.detail.validate.failed',
                autoClose: false,
            }),
        );
    });

    it('saves theme config via API with reset and validation', async () => {
        const themeService = {
            updateTheme: jest.fn(() => Promise.resolve()),
        };
        const wrapper = await createWrapper({
            themeServiceOverrides: themeService,
        });
        wrapper.vm.currentThemeConfigInitial = { foo: { value: 'old' } };
        wrapper.vm.currentThemeConfig = { foo: { value: 'new' } };

        await wrapper.vm.saveThemeConfig();

        expect(themeService.updateTheme).toHaveBeenCalledWith(
            'theme-id',
            { config: { foo: { value: 'new' } } },
            { reset: true, validate: true },
        );
    });

    it('handles compiling error on save', async () => {
        const error = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'THEME__COMPILING_ERROR',
                            detail: 'Compile error',
                        },
                    ],
                },
            },
        };
        const createNotification = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockReturnValue('notification-id');
        const wrapper = await createWrapper({
            themeServiceOverrides: {
                updateTheme: jest.fn(() => Promise.reject(error)),
            },
        });

        await wrapper.vm.onSaveTheme();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                title: 'ct-theme-manager.detail.error.themeCompile.title',
                autoClose: false,
            }),
        );
    });

    it('handles invalid configuration errors on save', async () => {
        const error = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'THEME__INVALID_SCSS_VAR',
                            detail: 'Invalid var',
                            meta: { parameters: { name: 'config-field' } },
                        },
                    ],
                },
            },
        };
        const createNotification = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockReturnValue('notification-id');
        const wrapper = await createWrapper({
            themeServiceOverrides: {
                updateTheme: jest.fn(() => Promise.reject(error)),
            },
        });

        await wrapper.vm.onSaveTheme();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                title: 'ct-theme-manager.detail.error.invalidConfiguration.title',
                autoClose: true,
            }),
        );
        expect(wrapper.vm.themeConfigErrors['config-field']).toBeDefined();
    });

    it('uses first media item when changing media selection', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.activeMediaField = 'field';
        wrapper.vm.currentThemeConfig = {
            field: { value: null },
        };

        wrapper.vm.onMediaChange([{ id: 'media-id' }]);

        expect(wrapper.vm.currentThemeConfig.field.value).toBe('media-id');
    });
});
