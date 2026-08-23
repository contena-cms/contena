import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

async function createWrapper(
    privileges = [],
    languageId = null,
    stubTranslationIsoField = true,
    languageRepositoryOverrides = {},
) {
    const options = {
        props: {
            languageId,
        },
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $t(translationKey) {
                    return translationKey;
                },
            },
            provide: {
                [routeLocationKey]: {
                    name: 'language-detail',
                    params: {},
                    query: {},
                    meta: { $module: { title: 'ct-settings-language.general.mainMenuItemGeneral' } },
                },
                [routerKey]: {
                    push: jest.fn(),
                    replace: jest.fn(),
                },
                repositoryFactory: {
                    create: (repositoryName) => ({
                        search: () => {
                            switch (repositoryName) {
                                case 'language':
                                    return Promise.resolve({
                                        aggregations: {
                                            usedTranslationIds: {
                                                buckets: [
                                                    {
                                                        key: '018d36e6165671b788b4811b31fdb2be',
                                                    },
                                                ],
                                            },
                                        },
                                    });
                                case 'locale': {
                                    return Promise.resolve([
                                        {
                                            id: '018d36e6165b702e8d73f463e7d38e87',
                                            code: 'nr-ZA',
                                            name: 'Southern Ndebele',
                                            territory: 'South Africa',
                                        },
                                        {
                                            id: '018d36e6165371a4b145cd683bf65869',
                                            code: 'de-DE',
                                            name: 'German',
                                            territory: 'Germany',
                                        },
                                        {
                                            id: '018d36e6165671b788b4811b31fdb2be',
                                            code: 'bs-BA',
                                            name: 'Bosnian',
                                            territory: 'Bosnia and Herzegovina',
                                        },
                                    ]);
                                }
                                default: {
                                    return Promise.resolve();
                                }
                            }
                        },

                        create: () => {
                            return Promise.resolve({
                                isNew: () => true,
                            });
                        },

                        get: (id) => {
                            return Promise.resolve({
                                id,
                                isNew: () => false,
                                parentId: '1234',
                                translationCodeId: '5678',
                            });
                        },

                        save: () => {
                            return Promise.resolve();
                        },

                        ...(repositoryName === 'language' ? languageRepositoryOverrides : {}),
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
                translationService: {
                    getList: jest.fn().mockResolvedValue({ items: [] }),
                    getMeta: jest.fn().mockResolvedValue({
                        builtInLocales: [
                            'zh-CN',
                            'en-GB',
                        ],
                    }),
                    install: jest.fn().mockResolvedValue({ updated: ['fr-FR'], skipped: [], unavailable: [] }),
                },
            },
            stubs: {
                'ct-page': {
                    template: `
                    <div class="ct-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                },
                'ct-card-view': true,
                'ct-container': true,
                'ct-language-switch': true,
                'ct-language-info': true,
                'ct-button-process': true,
                'ct-text-field': true,
                'ct-entity-single-select': true,
                'ct-skeleton': true,
                'ct-inherit-wrapper': await wrapTestComponent('ct-inherit-wrapper'),
                'ct-inheritance-switch': true,
                'ct-highlight-text': true,
                'ct-select-result': true,

                'ct-custom-field-set-renderer': true,
                'ct-product-variant-info': true,
                'ct-loader': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
                'ct-field-error': true,
                'router-link': true,
            },
        },
    };

    if (stubTranslationIsoField === false) {
        options.global.stubs = {
            ...options.global.stubs,
            'ct-entity-single-select': await wrapTestComponent('ct-entity-single-select'),
            'ct-select-base': await wrapTestComponent('ct-select-base'),
            'ct-block-field': await wrapTestComponent('ct-block-field'),
            'ct-base-field': await wrapTestComponent('ct-base-field'),
            'ct-select-result-list': await wrapTestComponent('ct-select-result-list'),
            'ct-highlight-text': await wrapTestComponent('ct-highlight-text'),
            'ct-select-result': await wrapTestComponent('ct-select-result'),
            'mt-floating-ui': {
                template: '<div><slot /></div>',
            },
        };
    }

    return mount(await wrapTestComponent('ct-settings-language-detail', { sync: true }), options);
}

describe('module/ct-settings-language/page/ct-settings-language-detail', () => {
    it('should return identifier', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.identifier).toBe('');

        wrapper.vm.language = {
            name: 'English',
        };

        expect(wrapper.vm.identifier).toBe('English');
    });

    it('should not be possible to inherit with no system language', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.inheritanceTooltipText).toBe('ct-settings-language.detail.tooltipLanguageNotChoosable');

        wrapper.vm.language = {
            id: Contena.Context.api.systemLanguageId,
        };
        expect(wrapper.vm.inheritanceTooltipText).toBe('ct-settings-language.detail.tooltipInheritanceNotPossible');
    });

    it('should load entity data', async () => {
        const wrapper = await createWrapper([], Contena.Context.api.systemLanguageId);
        expect(wrapper.vm.languageId).toBe(Contena.Context.api.systemLanguageId);
        await flushPromises();

        expect(wrapper.vm.language.id).toBe(Contena.Context.api.systemLanguageId);
    });

    it('should be able to save the language', async () => {
        const wrapper = await createWrapper([
            'language.editor',
            null,
            false,
        ]);
        await flushPromises();

        const saveButton = wrapper.find('.ct-settings-language-detail__save-action');
        const languageNameField = wrapper.find('input[aria-label="ct-settings-language.detail.labelName"]');
        const languageParentIdField = wrapper.find(
            'ct-entity-single-select-stub[label="ct-settings-language.detail.labelParent"]',
        );
        const languageTranslationCodeIdField = wrapper.find('#iso-codes');
        const languageLocaleIdField = wrapper.find(
            'ct-entity-single-select-stub[label="ct-settings-language.detail.labelLocale"]',
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(languageNameField.attributes().disabled).toBeUndefined();
        expect(languageParentIdField.attributes().disabled).toBeUndefined();
        expect(languageTranslationCodeIdField.attributes().disabled).toBeUndefined();
        expect(languageLocaleIdField.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save the language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.ct-settings-language-detail__save-action');
        const languageNameField = wrapper.find('input[aria-label="ct-settings-language.detail.labelName"]');
        const languageParentIdField = wrapper.find(
            'ct-entity-single-select-stub[label="ct-settings-language.detail.labelParent"]',
        );
        const languageTranslationCodeIdField = wrapper.find('#iso-codes');
        const languageLocaleIdField = wrapper.find(
            'ct-entity-single-select-stub[label="ct-settings-language.detail.labelLocale"]',
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(languageNameField.attributes().disabled).toBeDefined();
        expect(languageParentIdField.attributes().disabled).toBeTruthy();
        expect(languageTranslationCodeIdField.attributes().disabled).toBeTruthy();
        expect(languageLocaleIdField.attributes().disabled).toBeTruthy();
    });

    it('should add an asterix to used iso codes', async () => {
        const wrapper = await createWrapper(['language.editor'], Contena.Context.api.systemLanguageId, false);
        await flushPromises();

        const languageTranslationCodeIdField = wrapper.find('#iso-codes');

        await languageTranslationCodeIdField.find('.ct-entity-single-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.find('.ct-select-option--0').classes()).not.toContain('is--disabled');

        await wrapper.find('.ct-select-option--0').trigger('click');
        await flushPromises();

        await languageTranslationCodeIdField.find('.ct-entity-single-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.find('.ct-select-option--2').text()).toContain('*');

        await languageTranslationCodeIdField.find('.ct-select-option--2').trigger('click');
        await flushPromises();

        expect(wrapper.find('.ct-field__hint').text()).toContain('textIsoCodeIsInUse');
    });

    it('should load language data again after create new language', async () => {
        const get = jest.fn((id) =>
            Promise.resolve({
                id,
                isNew: () => false,
                parentId: null,
                translationCodeId: '5678',
            }),
        );
        const wrapper = await createWrapper(
            [
                'language.editor',
            ],
            null,
            false,
            { get },
        );
        await flushPromises();

        expect(get).not.toHaveBeenCalled();

        await wrapper.setProps({
            languageId: 'language-id-1',
        });
        await flushPromises();

        expect(get).toHaveBeenCalledWith('language-id-1', Contena.Context.api, expect.any(Object));
    });

    it('derives the snippet update state from the locale and metadata', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.builtInLocales = [
            'zh-CN',
            'en-GB',
        ];
        wrapper.vm.language = { locale: { code: 'en-GB' } };
        expect(wrapper.vm.snippetUpdateState).toBe('builtIn');

        wrapper.vm.language = { locale: { code: 'fr-FR' } };
        wrapper.vm.snippetMetadata = null;
        expect(wrapper.vm.snippetUpdateState).toBe('notAvailable');

        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: null, updateAvailable: false };
        expect(wrapper.vm.snippetUpdateState).toBe('notLinked');

        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: '2026-07-06T22:29:10+00:00', updateAvailable: true };
        expect(wrapper.vm.snippetUpdateState).toBe('updateAvailable');

        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: '2026-07-06T22:29:10+00:00', updateAvailable: false };
        expect(wrapper.vm.snippetUpdateState).toBe('upToDate');

        wrapper.vm.isUpdatingSnippets = true;
        expect(wrapper.vm.snippetUpdateState).toBe('updating');

        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: null, updateAvailable: false };
        expect(wrapper.vm.snippetUpdateState).toBe('linking');
    });

    it('offers the link action for a supported but not linked language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.language = { locale: { code: 'fr-FR' } };
        wrapper.vm.snippetMetadata = { locale: 'fr-FR', lastUpdate: null, updateAvailable: false };

        expect(wrapper.vm.showSnippetUpdateButton).toBe(true);
        expect(wrapper.vm.showSnippetAutoUpdate).toBe(false);
        expect(wrapper.vm.snippetUpdateButtonLabel).toBe('ct-settings-language.detail.snippetUpdates.linkButton');

        wrapper.vm.isUpdatingSnippets = true;
        expect(wrapper.vm.snippetUpdateButtonLabel).toBe('ct-settings-language.detail.snippetUpdates.linkingButton');
    });

    it('installs the snippets for the current language and reloads the metadata', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.language = { locale: { code: 'fr-FR' } };

        await wrapper.vm.onUpdateSnippets();
        await flushPromises();

        expect(wrapper.vm.translationService.install).toHaveBeenCalledWith({ locales: ['fr-FR'], activate: true });
        expect(wrapper.vm.translationService.getList).toHaveBeenCalled();
        expect(wrapper.vm.isUpdatingSnippets).toBe(false);
    });
});
