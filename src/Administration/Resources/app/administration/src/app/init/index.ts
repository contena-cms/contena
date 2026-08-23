/**
 *
 * These types of initializers are called in the middle of the initialization process.
 * They are not allowed to depend on another initializers to suppress circular references.
 */
import initComponentHelper from 'src/app/init/component-helper.init';
import initHttpClient from 'src/app/init/http.init';
import initRepository from 'src/app/init/repository.init';
import initMixin from 'src/app/init/mixin.init';
import initCoreModules from 'src/app/init/modules.init';
import initLogin from 'src/app/init/login.init';
import initRouter from 'src/app/init/router.init';
import initFilter from 'src/app/init/filter.init';
import initDirectives from 'src/app/init/directive.init';
import initLocale from 'src/app/init/locale.init';
import initComponents from 'src/app/init/component.init';
import initFilterFactory from 'src/app/init/filter-factory.init';
import initializeTheme from 'src/app/init/theme.init';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default {
    coreMixin: initMixin,
    coreDirectives: initDirectives,
    coreFilter: initFilter,
    baseComponents: initComponents,
    coreModuleRoutes: initCoreModules,
    login: initLogin,
    router: initRouter,
    locale: initLocale,
    repositoryFactory: initRepository,
    httpClient: initHttpClient,
    componentHelper: initComponentHelper,
    filterFactory: initFilterFactory,
    theme: initializeTheme,
};
