/** Initializer */
import initializers from 'src/app/init/';
import preInitializer from 'src/app/init-pre/';
import postInitializer from 'src/app/init-post/';

/** View Adapter */
import VueAdapter from 'src/app/adapter/view/vue.adapter';

/** Services */
import FeatureService from 'src/app/service/feature.service';
import MenuService from 'src/app/service/menu.service';
import PrivilegesService from 'src/app/service/privileges.service';
import AclService from 'src/app/service/acl.service';
import LoginService from 'src/core/service/login.service';
import EntityMappingService from 'src/core/service/entity-mapping.service';
import JsonApiParser from 'src/core/service/jsonapi-parser.service';
import ValidationService from 'src/core/service/validation.service';
import TimezoneService from 'src/core/service/timezone.service';
import BlogTypeApiService from 'src/app/service/blog-type.api.service';
import StateStyleService from 'src/app/service/state-style.service';
import CustomFieldService from 'src/app/service/custom-field.service';
import LanguageAutoFetchingService from 'src/app/service/language-auto-fetching.service';
import SearchTypeService from 'src/app/service/search-type.service';
import LocaleToLanguageService from 'src/app/service/locale-to-language.service';
import LocaleHelperService from 'src/app/service/locale-helper.service';
import FilterService from 'src/app/service/filter.service';
import MediaDefaultFolderService from 'src/app/service/media-default-folder.service';
import SearchRankingService from 'src/app/service/search-ranking.service';
import SearchPreferencesService from 'src/app/service/search-preferences.service';
import RecentlySearchService from 'src/app/service/recently-search.service';
import EntityValidationService from 'src/app/service/entity-validation.service';
import FileValidationService from 'src/app/service/file-validation.service';
import DataDictionaryService from 'src/app/service/data-dictionary.service';
import CacheService from 'src/app/service/cache.service';

/** Import Feature */
import Feature from 'src/core/feature';

/** Import decorators */
import 'src/app/decorator';

/** Import Meteor Component Library styles */
import '@contena/meteor-component-library/styles.css';
import '@contena/meteor-component-library/font.css';
import './assets/scss/all.scss';

import ChangesetGenerator from '../core/data/changeset-generator.data';
import ErrorResolver from '../core/data/error-resolver.data';

/** Application Bootstrapper */
const { Application } = Contena;

const factoryContainer = Application.getContainer('factory');

/** Create View Adapter */
const adapter = new VueAdapter(Application);

Application.setViewAdapter(adapter);

// Add pre-initializers to application
Object.keys(preInitializer).forEach((key) => {
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const initializer = preInitializer[key];
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    Application.addInitializer(key, initializer, '-pre');
});

// Add initializers to application
Object.keys(initializers).forEach((key) => {
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const initializer = initializers[key];
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    Application.addInitializer(key, initializer);
});

// Add post-initializers to application
Object.keys(postInitializer).forEach((key) => {
    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const initializer = postInitializer[key];

    // @ts-expect-error
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    Application.addInitializer(key, initializer, '-post');
});

// Add service providers
Application.addServiceProvider('feature', () => {
    return new FeatureService(Feature);
})
    .addServiceProvider('menuService', () => {
        return new MenuService(factoryContainer.module);
    })
    .addServiceProvider('privileges', () => {
        return new PrivilegesService();
    })
    .addServiceProvider('acl', () => {
        return new AclService();
    })
    .addServiceProvider('loginService', () => {
        const initContainer = Application.getContainer('init');

        return LoginService(initContainer.httpClient, Contena.Context.api);
    })
    .addServiceProvider('cacheService', () => {
        return new CacheService();
    })
    .addServiceProvider('jsonApiParserService', () => {
        return JsonApiParser;
    })
    .addServiceProvider('validationService', () => {
        return ValidationService;
    })
    .addServiceProvider('entityValidationService', () => {
        return new EntityValidationService(
            Application.getContainer('factory').entityDefinition,
            new ChangesetGenerator(),
            new ErrorResolver(),
        );
    })
    .addServiceProvider('blogTypeService', () => {
        const initContainer = Contena.Application.getContainer('init');

        return new BlogTypeApiService(initContainer.httpClient, Contena.Service('loginService'));
    })
    .addServiceProvider('timezoneService', () => {
        return new TimezoneService();
    })
    .addServiceProvider('customFieldDataProviderService', () => {
        return new CustomFieldService();
    })
    .addServiceProvider('languageAutoFetchingService', () => {
        return LanguageAutoFetchingService();
    })
    .addServiceProvider('stateStyleDataProviderService', () => {
        return new StateStyleService();
    })
    .addServiceProvider('searchTypeService', () => {
        return new SearchTypeService();
    })
    .addServiceProvider('localeToLanguageService', () => {
        return LocaleToLanguageService();
    })
    .addServiceProvider('entityMappingService', () => {
        return EntityMappingService;
    })
    .addServiceProvider('localeHelper', () => {
        return new LocaleHelperService({
            Contena: Contena,
            localeRepository: Contena.Service('repositoryFactory').create('locale'),
            snippetService: Contena.Service('snippetService'),
            localeFactory: Application.getContainer('factory').locale,
        });
    })
    .addServiceProvider('filterService', () => {
        return new FilterService({
            userConfigRepository: Contena.Service('repositoryFactory').create('user_config'),
        });
    })
    .addServiceProvider('mediaDefaultFolderService', () => {
        return MediaDefaultFolderService();
    })
    .addServiceProvider('searchRankingService', () => {
        return new SearchRankingService();
    })
    .addServiceProvider('recentlySearchService', () => {
        return new RecentlySearchService();
    })
    .addServiceProvider('searchPreferencesService', () => {
        return new SearchPreferencesService({
            userConfigRepository: Contena.Service('repositoryFactory').create('user_config'),
        });
    })
    .addServiceProvider('fileValidationService', () => {
        return FileValidationService();
    })
    .addServiceProvider('dataDictionaryService', () => {
        return new DataDictionaryService(Contena.Service('repositoryFactory'));
    });
