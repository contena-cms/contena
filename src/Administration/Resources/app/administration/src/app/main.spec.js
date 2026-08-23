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
        StateStyleService: undefined,
        CustomFieldService: undefined,
        LanguageAutoFetchingService: undefined,
        SearchTypeService: undefined,
        LocaleToLanguageService: undefined,
        LocaleHelperService: undefined,
        FilterService: undefined,
        MediaDefaultFolderService: undefined,
        SearchRankingService: undefined,
        SearchPreferencesService: undefined,
        RecentlySearchService: undefined,
        EntityValidationService: undefined,
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

        jest.mock('src/app/service/state-style.service');
        serviceMocks.StateStyleService = (await import('src/app/service/state-style.service')).default;

        jest.mock('src/app/service/custom-field.service');
        serviceMocks.CustomFieldService = (await import('src/app/service/custom-field.service')).default;

        jest.mock('src/app/service/language-auto-fetching.service');
        serviceMocks.LanguageAutoFetchingService = (await import('src/app/service/language-auto-fetching.service')).default;

        jest.mock('src/app/service/search-type.service');
        serviceMocks.SearchTypeService = (await import('src/app/service/search-type.service')).default;

        jest.mock('src/app/service/locale-to-language.service');
        serviceMocks.LocaleToLanguageService = (await import('src/app/service/locale-to-language.service')).default;

        jest.mock('src/app/service/locale-helper.service');
        serviceMocks.LocaleHelperService = (await import('src/app/service/locale-helper.service')).default;

        jest.mock('src/app/service/filter.service');
        serviceMocks.FilterService = (await import('src/app/service/filter.service')).default;

        jest.mock('src/app/service/media-default-folder.service');
        serviceMocks.MediaDefaultFolderService = (await import('src/app/service/media-default-folder.service')).default;

        jest.mock('src/app/service/search-ranking.service');
        serviceMocks.SearchRankingService = (await import('src/app/service/search-ranking.service')).default;

        jest.mock('src/app/service/search-preferences.service');
        serviceMocks.SearchPreferencesService = (await import('src/app/service/search-preferences.service')).default;

        jest.mock('src/app/service/recently-search.service');
        serviceMocks.RecentlySearchService = (await import('src/app/service/recently-search.service')).default;

        jest.mock('src/app/service/entity-validation.service');
        serviceMocks.EntityValidationService = (await import('src/app/service/entity-validation.service')).default;

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
            'coreMixin',
            'coreDirectives',
            'coreFilter',
            'baseComponents',
            'coreModuleRoutes',
            'login',
            'router',
            'locale',
            'repositoryFactory',
            'httpClient',
            'componentHelper',
            'filterFactory',
            'language',
            'userInformation',
            'worker',
            'store',
            'theme',
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
        expect(services).toContain('menuService');
        expect(services).toContain('privileges');
        expect(services).toContain('acl');
        expect(services).toContain('loginService');
        expect(services).toContain('jsonApiParserService');
        expect(services).toContain('validationService');
        expect(services).toContain('entityValidationService');
        expect(services).toContain('timezoneService');
        expect(services).toContain('customFieldDataProviderService');
        expect(services).toContain('languageAutoFetchingService');
        expect(services).toContain('stateStyleDataProviderService');
        expect(services).toContain('searchTypeService');
        expect(services).toContain('localeToLanguageService');
        expect(services).toContain('entityMappingService');
        expect(services).toContain('localeHelper');
        expect(services).toContain('filterService');
        expect(services).toContain('mediaDefaultFolderService');
        expect(services).toContain('searchRankingService');
        expect(services).toContain('recentlySearchService');
        expect(services).toContain('searchPreferencesService');
        expect(services).toContain('fileValidationService');
    });

    it('should create imported services on usage', () => {
        // Check if all services get executed correctly
        expect(serviceMocks.FeatureService).not.toHaveBeenCalled();
        Contena.Service('feature');
        expect(serviceMocks.FeatureService).toHaveBeenCalled();

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

        expect(serviceMocks.CustomFieldService).not.toHaveBeenCalled();
        Contena.Service('customFieldDataProviderService');
        expect(serviceMocks.CustomFieldService).toHaveBeenCalled();

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

        expect(serviceMocks.LocaleHelperService).not.toHaveBeenCalled();
        Contena.Service('localeHelper');
        expect(serviceMocks.LocaleHelperService).toHaveBeenCalled();

        expect(serviceMocks.FilterService).not.toHaveBeenCalled();
        Contena.Service('filterService');
        expect(serviceMocks.FilterService).toHaveBeenCalled();

        expect(serviceMocks.MediaDefaultFolderService).not.toHaveBeenCalled();
        Contena.Service('mediaDefaultFolderService');
        expect(serviceMocks.MediaDefaultFolderService).toHaveBeenCalled();

        expect(serviceMocks.SearchRankingService).not.toHaveBeenCalled();
        Contena.Service('searchRankingService');
        expect(serviceMocks.SearchRankingService).toHaveBeenCalled();

        expect(serviceMocks.RecentlySearchService).not.toHaveBeenCalled();
        Contena.Service('recentlySearchService');
        expect(serviceMocks.RecentlySearchService).toHaveBeenCalled();

        expect(serviceMocks.SearchPreferencesService).not.toHaveBeenCalled();
        Contena.Service('searchPreferencesService');
        expect(serviceMocks.SearchPreferencesService).toHaveBeenCalled();

        expect(serviceMocks.FileValidationService).not.toHaveBeenCalled();
        Contena.Service('fileValidationService');
        expect(serviceMocks.FileValidationService).toHaveBeenCalled();
    });
});
