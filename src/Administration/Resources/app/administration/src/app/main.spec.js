/**
 * @sw-package framework
 */
describe('src/app/main.ts', () => {
    let VueAdapter;

    const serviceMocks = {
        FeatureService: undefined,
        MenuService: undefined,
        PrivilegesService: undefined,
        AclService: undefined,
        LoginService: undefined,
        EntityMappingService: undefined,
        JsonApiParser: undefined,
        ValidationService: undefined,
        TimezoneService: undefined,
        RuleConditionService: undefined,
        ProductStreamConditionService: undefined,
        StateStyleService: undefined,
        CustomFieldService: undefined,
        ExtensionHelperService: undefined,
        LanguageAutoFetchingService: undefined,
        SearchTypeService: undefined,
        LicenseViolationsService: undefined,
        ShortcutService: undefined,
        LocaleToLanguageService: undefined,
        addPluginUpdatesListener: undefined,
        addContenaUpdatesListener: undefined,
        addCustomerGroupRegistrationListener: undefined,
        LocaleHelperService: undefined,
        FilterService: undefined,
        AppCmsService: undefined,
        MediaDefaultFolderService: undefined,
        AppAclService: undefined,
        ContenaDiscountCampaignService: undefined,
        SearchRankingService: undefined,
        SearchPreferencesService: undefined,
        RecentlySearchService: undefined,
        UserActivityService: undefined,
        EntityValidationService: undefined,
        CustomEntityDefinitionService: undefined,
        FileValidationService: undefined,
    };

    beforeAll(async () => {
        // Start with a clean state
        jest.resetModules();

        // Mock some initializers
        jest.mock('src/app/init/http.init', () => {
            return jest.fn(() => {
                return jest.fn({});
            });
        });

        // Mock all service imports
        jest.mock('src/app/service/feature.service');
        serviceMocks.FeatureService = (await import('src/app/service/feature.service')).default;

        jest.mock('src/app/service/menu.service');
        serviceMocks.MenuService = (await import('src/app/service/menu.service')).default;

        jest.mock('src/app/service/privileges.service');
        serviceMocks.PrivilegesService = (await import('src/app/service/privileges.service')).default;

        jest.mock('src/app/service/acl.service');
        serviceMocks.AclService = (await import('src/app/service/acl.service')).default;

        jest.mock('src/core/service/login.service', () => {
            return jest.fn(() => {});
        });
        serviceMocks.LoginService = (await import('src/core/service/login.service')).default;

        jest.mock('src/core/service/entity-mapping.service');
        serviceMocks.EntityMappingService = (await import('src/core/service/entity-mapping.service')).default;

        jest.mock('src/core/service/jsonapi-parser.service');
        serviceMocks.JsonApiParser = (await import('src/core/service/jsonapi-parser.service')).default;

        jest.mock('src/core/service/validation.service');
        serviceMocks.ValidationService = (await import('src/core/service/validation.service')).default;

        jest.mock('src/core/service/timezone.service');
        serviceMocks.TimezoneService = (await import('src/core/service/timezone.service')).default;

        jest.mock('src/app/service/rule-condition.service', () => {
            return jest.fn(() => {
                return {
                    addCondition: jest.fn(() => {}),
                    getRestrictionsByGroup: jest.fn(() => []),
                    addAwarenessConfiguration: jest.fn(() => {}),
                    registerDeprecation: jest.fn(() => {}),
                };
            });
        });
        serviceMocks.RuleConditionService = (await import('src/app/service/rule-condition.service')).default;

        jest.mock('src/app/service/product-stream-condition.service');
        serviceMocks.ProductStreamConditionService = (
            await import('src/app/service/product-stream-condition.service')
        ).default;

        jest.mock('src/app/service/state-style.service');
        serviceMocks.StateStyleService = (await import('src/app/service/state-style.service')).default;

        jest.mock('src/app/service/custom-field.service');
        serviceMocks.CustomFieldService = (await import('src/app/service/custom-field.service')).default;

        jest.mock('src/app/service/extension-helper.service');
        serviceMocks.ExtensionHelperService = (await import('src/app/service/extension-helper.service')).default;

        jest.mock('src/app/service/language-auto-fetching.service');
        serviceMocks.LanguageAutoFetchingService = (await import('src/app/service/language-auto-fetching.service')).default;

        jest.mock('src/app/service/search-type.service');
        serviceMocks.SearchTypeService = (await import('src/app/service/search-type.service')).default;

        jest.mock('src/app/service/license-violations.service');
        serviceMocks.LicenseViolationsService = (await import('src/app/service/license-violations.service')).default;

        jest.mock('src/app/service/shortcut.service');
        serviceMocks.ShortcutService = (await import('src/app/service/shortcut.service')).default;

        jest.mock('src/app/service/locale-to-language.service');
        serviceMocks.LocaleToLanguageService = (await import('src/app/service/locale-to-language.service')).default;

        jest.mock('src/core/service/plugin-updates-listener.service');
        serviceMocks.PluginUpdatesListener = (await import('src/core/service/plugin-updates-listener.service')).default;

        jest.mock('src/core/service/contena-updates-listener.service');
        serviceMocks.ContenaUpdatesListener = (await import('src/core/service/contena-updates-listener.service')).default;

        jest.mock('src/core/service/customer-group-registration-listener.service');
        serviceMocks.CustomerGroupRegistrationListener = (
            await import('src/core/service/customer-group-registration-listener.service')
        ).default;

        jest.mock('src/app/service/locale-helper.service');
        serviceMocks.LocaleHelperService = (await import('src/app/service/locale-helper.service')).default;

        jest.mock('src/app/service/filter.service');
        serviceMocks.FilterService = (await import('src/app/service/filter.service')).default;

        jest.mock('src/app/service/app-cms.service');
        serviceMocks.AppCmsService = (await import('src/app/service/app-cms.service')).default;

        jest.mock('src/app/service/media-default-folder.service');
        serviceMocks.MediaDefaultFolderService = (await import('src/app/service/media-default-folder.service')).default;

        jest.mock('src/app/service/app-acl.service');
        serviceMocks.AppAclService = (await import('src/app/service/app-acl.service')).default;

        jest.mock('src/app/service/discount-campaign.service');
        serviceMocks.ContenaDiscountCampaignService = (await import('src/app/service/discount-campaign.service')).default;

        jest.mock('src/app/service/search-ranking.service');
        serviceMocks.SearchRankingService = (await import('src/app/service/search-ranking.service')).default;

        jest.mock('src/app/service/search-preferences.service');
        serviceMocks.SearchPreferencesService = (await import('src/app/service/search-preferences.service')).default;

        jest.mock('src/app/service/recently-search.service');
        serviceMocks.RecentlySearchService = (await import('src/app/service/recently-search.service')).default;

        jest.mock('src/app/service/user-activity.service');
        serviceMocks.UserActivityService = (await import('src/app/service/user-activity.service')).default;

        jest.mock('src/app/service/entity-validation.service');
        serviceMocks.EntityValidationService = (await import('src/app/service/entity-validation.service')).default;

        jest.mock('src/app/service/custom-entity-definition.service');
        serviceMocks.CustomEntityDefinitionService = (
            await import('src/app/service/custom-entity-definition.service')
        ).default;

        jest.mock('src/app/service/file-validation.service');
        serviceMocks.FileValidationService = (await import('src/app/service/file-validation.service')).default;

        // Reset the Contena object to make sure that the application is not already initialized
        Contena = undefined;
        // Import the Contena object
        Contena = (await import('src/core/contena')).ContenaInstance;
        // Initialize the main application
        await import('src/app/main');
        // Import the VueAdapter to check if it is set in the application
        VueAdapter = (await import('src/app/adapter/view/vue.adapter')).default;

        // Mock services from other places
        Contena.Service().register('repositoryFactory', () => {
            return {
                create: () => {},
            };
        });

        jest.spyOn(Contena, 'Context', 'get').mockReturnValue({ api: {} });
    });

    it('should create the global application DI container in the Contena object', () => {
        expect(Contena.Application).toBeDefined();
    });

    it('should set the VueAdapter into the application', () => {
        expect(Contena.Application.view).toBeInstanceOf(VueAdapter);
    });

    it('should add all initializer to Application', () => {
        const expectedInitializers = [
            'apiServices',
            'state',
            'coreMixin',
            'coreDirectives',
            'coreFilter',
            'baseComponents',
            'coreModuleRoutes',
            'login',
            'router',
            'locale',
            'repositoryFactory',
            'shortcut',
            'httpClient',
            'componentHelper',
            'filterFactory',
            'notification',
            'context',
            'window',
            'extensionComponentSections',
            'tabs',
            'cms',
            'menu',
            'settingItems',
            'modals',
            'mainModules',
            'actionButton',
            'actions',
            'extensionDataHandling',
            'language',
            'userInformation',
            'worker',
            'inAppPurchaseCheckout',
            'store',
            'topbarButton',
            'teaserPopover',
        ];

        const initializers = Contena.Application.getContainer('init').$list();
        initializers.push(...Contena.Application.getContainer('init-pre').$list());
        initializers.push(...Contena.Application.getContainer('init-post').$list());

        expectedInitializers.forEach((initializer) => {
            expect(initializers).toContain(initializer);
        });
    });

    it('should add all services to Application', () => {
        const services = Contena.Application.getContainer('service').$list();

        expect(services).toContain('feature');
        expect(services).toContain('customEntityDefinitionService');
        expect(services).toContain('menuService');
        expect(services).toContain('privileges');
        expect(services).toContain('acl');
        expect(services).toContain('loginService');
        expect(services).toContain('jsonApiParserService');
        expect(services).toContain('validationService');
        expect(services).toContain('entityValidationService');
        expect(services).toContain('timezoneService');
        expect(services).toContain('ruleConditionDataProviderService');
        expect(services).toContain('productStreamConditionService');
        expect(services).toContain('customFieldDataProviderService');
        expect(services).toContain('extensionHelperService');
        expect(services).toContain('languageAutoFetchingService');
        expect(services).toContain('stateStyleDataProviderService');
        expect(services).toContain('searchTypeService');
        expect(services).toContain('localeToLanguageService');
        expect(services).toContain('entityMappingService');
        expect(services).toContain('shortcutService');
        expect(services).toContain('licenseViolationService');
        expect(services).toContain('localeHelper');
        expect(services).toContain('filterService');
        expect(services).toContain('mediaDefaultFolderService');
        expect(services).toContain('appAclService');
        expect(services).toContain('appCmsService');
        expect(services).toContain('contenaDiscountCampaignService');
        expect(services).toContain('searchRankingService');
        expect(services).toContain('recentlySearchService');
        expect(services).toContain('searchPreferencesService');
        expect(services).toContain('userActivityService');
        expect(services).toContain('fileValidationService');
    });

    it('should create imported services on usage', () => {
        // Initialize needed initializers
        const preInitializers = Contena.Application.getContainer('init-pre');
        expect(preInitializers.state).toBeDefined();

        // Check if all services get executed correctly
        expect(serviceMocks.FeatureService).not.toHaveBeenCalled();
        Contena.Service('feature');
        expect(serviceMocks.FeatureService).toHaveBeenCalled();

        expect(serviceMocks.CustomEntityDefinitionService).not.toHaveBeenCalled();
        Contena.Service('customEntityDefinitionService');
        expect(serviceMocks.CustomEntityDefinitionService).toHaveBeenCalled();

        expect(serviceMocks.MenuService).not.toHaveBeenCalled();
        Contena.Service('menuService');
        expect(serviceMocks.MenuService).toHaveBeenCalled();

        expect(serviceMocks.PrivilegesService).not.toHaveBeenCalled();
        Contena.Service('privileges');
        expect(serviceMocks.PrivilegesService).toHaveBeenCalled();

        expect(serviceMocks.AclService).not.toHaveBeenCalled();
        Contena.Service('acl');
        expect(serviceMocks.AclService).toHaveBeenCalled();

        expect(serviceMocks.LoginService).not.toHaveBeenCalled();
        Contena.Service('loginService');
        expect(serviceMocks.LoginService).toHaveBeenCalled();

        expect(serviceMocks.JsonApiParser).not.toHaveBeenCalled();
        const jsonApiParserService = Contena.Service('jsonApiParserService');
        expect(jsonApiParserService).toBe(serviceMocks.JsonApiParser);

        const validationService = Contena.Service('validationService');
        expect(validationService).toEqual(serviceMocks.ValidationService);

        expect(serviceMocks.EntityValidationService).not.toHaveBeenCalled();
        Contena.Service('entityValidationService');
        expect(serviceMocks.EntityValidationService).toHaveBeenCalled();

        expect(serviceMocks.TimezoneService).not.toHaveBeenCalled();
        Contena.Service('timezoneService');
        expect(serviceMocks.TimezoneService).toHaveBeenCalled();

        expect(serviceMocks.RuleConditionService).not.toHaveBeenCalled();
        Contena.Service('ruleConditionDataProviderService');
        expect(serviceMocks.RuleConditionService).toHaveBeenCalled();

        expect(serviceMocks.ProductStreamConditionService).not.toHaveBeenCalled();
        Contena.Service('productStreamConditionService');
        expect(serviceMocks.ProductStreamConditionService).toHaveBeenCalled();

        expect(serviceMocks.CustomFieldService).not.toHaveBeenCalled();
        Contena.Service('customFieldDataProviderService');
        expect(serviceMocks.CustomFieldService).toHaveBeenCalled();

        expect(serviceMocks.ExtensionHelperService).not.toHaveBeenCalled();
        Contena.Service('extensionHelperService');
        expect(serviceMocks.ExtensionHelperService).toHaveBeenCalled();

        expect(serviceMocks.LanguageAutoFetchingService).not.toHaveBeenCalled();
        Contena.Service('languageAutoFetchingService');
        expect(serviceMocks.LanguageAutoFetchingService).toHaveBeenCalled();

        expect(serviceMocks.StateStyleService).not.toHaveBeenCalled();
        Contena.Service('stateStyleDataProviderService');
        expect(serviceMocks.StateStyleService).toHaveBeenCalled();

        expect(serviceMocks.SearchTypeService).not.toHaveBeenCalled();
        Contena.Service('searchTypeService');
        expect(serviceMocks.SearchTypeService).toHaveBeenCalled();

        expect(serviceMocks.LocaleToLanguageService).not.toHaveBeenCalled();
        Contena.Service('localeToLanguageService');
        expect(serviceMocks.LocaleToLanguageService).toHaveBeenCalled();

        const entityMappingService = Contena.Service('entityMappingService');
        expect(entityMappingService).toEqual(serviceMocks.EntityMappingService);

        expect(serviceMocks.ShortcutService).not.toHaveBeenCalled();
        Contena.Service('shortcutService');
        expect(serviceMocks.ShortcutService).toHaveBeenCalled();

        expect(serviceMocks.LicenseViolationsService).not.toHaveBeenCalled();
        Contena.Service('licenseViolationService');
        expect(serviceMocks.LicenseViolationsService).toHaveBeenCalled();

        expect(serviceMocks.LocaleHelperService).not.toHaveBeenCalled();
        Contena.Service('localeHelper');
        expect(serviceMocks.LocaleHelperService).toHaveBeenCalled();

        expect(serviceMocks.FilterService).not.toHaveBeenCalled();
        Contena.Service('filterService');
        expect(serviceMocks.FilterService).toHaveBeenCalled();

        expect(serviceMocks.MediaDefaultFolderService).not.toHaveBeenCalled();
        Contena.Service('mediaDefaultFolderService');
        expect(serviceMocks.MediaDefaultFolderService).toHaveBeenCalled();

        expect(serviceMocks.AppAclService).not.toHaveBeenCalled();
        Contena.Service('appAclService');
        expect(serviceMocks.AppAclService).toHaveBeenCalled();

        expect(serviceMocks.AppCmsService).not.toHaveBeenCalled();
        Contena.Service('appCmsService');
        expect(serviceMocks.AppCmsService).toHaveBeenCalled();

        expect(serviceMocks.ContenaDiscountCampaignService).not.toHaveBeenCalled();
        Contena.Service('contenaDiscountCampaignService');
        expect(serviceMocks.ContenaDiscountCampaignService).toHaveBeenCalled();

        expect(serviceMocks.SearchRankingService).not.toHaveBeenCalled();
        Contena.Service('searchRankingService');
        expect(serviceMocks.SearchRankingService).toHaveBeenCalled();

        expect(serviceMocks.RecentlySearchService).not.toHaveBeenCalled();
        Contena.Service('recentlySearchService');
        expect(serviceMocks.RecentlySearchService).toHaveBeenCalled();

        expect(serviceMocks.SearchPreferencesService).not.toHaveBeenCalled();
        Contena.Service('searchPreferencesService');
        expect(serviceMocks.SearchPreferencesService).toHaveBeenCalled();

        expect(serviceMocks.UserActivityService).not.toHaveBeenCalled();
        Contena.Service('userActivityService');
        expect(serviceMocks.UserActivityService).toHaveBeenCalled();

        expect(serviceMocks.FileValidationService).not.toHaveBeenCalled();
        Contena.Service('fileValidationService');
        expect(serviceMocks.FileValidationService).toHaveBeenCalled();
    });
});
