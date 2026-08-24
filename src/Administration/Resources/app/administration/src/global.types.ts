/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable @typescript-eslint/no-empty-object-type */
import type { default as Bottle, Decorator } from 'bottlejs';
import type { NavigationGuardNext, RouteLocationNormalized, RouteLocationNormalizedLoaded, Router } from 'vue-router';
// Import explicitly global types from meteor-admin-sdk
import '@contena/meteor-admin-sdk';
import type FeatureService from 'src/app/service/feature.service';
import type CacheService from 'src/app/service/cache.service';
import type { LoginService } from 'src/core/service/login.service';
import type { AxiosInstance } from 'axios';
import type { ContenaClass } from 'src/core/contena';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type LocaleFactory from 'src/core/factory/locale.factory';
import type ModuleFactory from 'src/core/factory/module.factory';
import type DirectiveFactory from 'src/core/factory/directive.factory';
import type EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';
import type FilterFactoryData from 'src/core/data/filter-factory.data';
import type UserApiService from 'src/core/service/api/user.api.service';
import type UserConfigService from 'src/core/service/api/user-config.api.service';
import type ApiServiceFactory from 'src/core/factory/api-service.factory';
import type { ComponentInternalInstance, PropType as VuePropType } from 'vue';
import type { I18n } from 'vue-i18n';
import type { mapActions, mapState } from 'pinia';
import type * as mapErrors from 'src/app/service/map-errors.service';
import type JsonApiParserService from 'src/core/service/jsonapi-parser.service';
/* eslint-disable @typescript-eslint/no-unused-vars */
// Needed for the Editor types
import type { Editor as CoreEditor, EditorOptions } from '@tiptap/core';
import type Link from '@tiptap/extension-link';
/* eslint-enable @typescript-eslint/no-unused-vars */
import type { ComponentConfig } from './core/factory/async-component.factory';
import type AclService from './app/service/acl.service';
import type EntityValidationService from './app/service/entity-validation.service';
import type AsyncComponentFactory from './core/factory/async-component.factory';
import type FilterFactory from './core/factory/filter.factory';
import type StateStyleService from './app/service/state-style.service';
import type SystemConfigApiService from './core/service/api/system-config.api.service';
import type UpdateApiService from './core/service/api/update.api.service';
import type UserRecoveryApiService from './core/service/api/user-recovery.api.service';
import type ConfigApiService from './core/service/api/config.api.service';
import type NotificationMixin from './app/mixin/notification.mixin';
import type SwInlineSnippetMixin from './app/mixin/ct-inline-snippet.mixin';
import type TranslateWithFallbackMixin from './app/mixin/translate-with-fallback.mixin';
import type PlaceholderMixin from './app/mixin/placeholder.mixin';
import type SwExtensionErrorMixin from './module/ct-extension/mixin/ct-extension-error.mixin';
import type PrivilegesService from './app/service/privileges.service';
import type BusinessEventsApiService from './core/service/api/business-events.api.service';
import type MailApiService from './core/service/api/mail.api.service';
import type FlowActionsApiService from './core/service/api/flow-actions.api.service';
import type RuleConditionsConfigApiService from './core/service/api/rule-conditions-config.api.service';
import type { FileValidationService } from './app/service/file-validation.service';
import type DataDictionaryService from './app/service/data-dictionary.service';
import type { DevtoolComponent } from './app/adapter/view/ct-vue-devtools';
import type { AdminMenuStore } from './app/store/admin-menu.store';
import type { RouteTabsStore } from './app/store/route-tabs.store';
import type { BlockOverrideStore } from './app/store/block-override.store';
import type { ExtensionEntryRoutes } from './app/store/extension-entry-routes.store';
import type { ErrorStore } from './app/store/error.store';
import type { AdminHelpCenterStore } from './app/store/admin-help-center.store';
import type { ContextStore } from './app/store/context.store';
import type { SettingsItems } from './app/store/settings-item.store';
import type { System } from './app/store/system.store';
import type { MediaModalStore } from './app/store/media-modal.store';
import type { NotificationStore } from './app/store/notification.store';
import type { SessionStore } from './app/store/session.store';
import type { ContenaExtensionsStore } from './module/ct-extension/store/extensions.store';
import type { SwCategoryDetailStore } from './module/ct-category/page/ct-category-detail/store';
import type { SwBlogDetailStore } from './module/ct-blog/page/ct-blog-detail/store';
import type { SwSeoUrlStore } from './module/ct-settings-seo/component/ct-seo-url/store';
import type { ExperienceStudioEditorStore } from './module/ct-experience-studio/store/experience-studio-editor.store';
import type { SwProfileStore } from './module/ct-profile/store/ct-profile.store';
import type { SwBulkStore } from './app/store/ct-bulk-edit.store';
import type SnippetApiService from './core/service/api/snippet.api.service';
import type SnippetSetApiService from './core/service/api/snippet-set.api.service';
import type ValidationApiService from './core/service/api/validation.api.service';
import type ChannelApiService from './core/service/api/channel.api.service';
import type MemberGroupRegistrationApiService from './core/service/api/member-group-registration.api.service';
import type BlogTypeApiService from './app/service/blog-type.api.service';
import type MediaService from './core/service/api/media.api.service';
// trick to make it an "external module" to support global type extension

// base methods for subContainer
// Export for modules and plugins to extend the service definitions
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export interface SubContainer<ContainerName extends string> {
    $decorator(name: string | Decorator, func?: Decorator): this;
    $register(Obj: Bottle.IRegisterableObject): this;
    $list(): (keyof Bottle.IContainer[ContainerName])[];
}

// declare global types
declare global {
    /**
     * "any" type which looks more awful in the code so that spot easier
     * the places where we need to fix the TS types
     */
    type $TSFixMe = any;
    type $TSFixMeFunction = (...args: any[]) => any;

    /**
     * Dangerous "unknown" types which are specific enough but do not provide type safety.
     * You should avoid using these.
     */
    type $TSDangerUnknownObject = { [key: string | symbol]: unknown };

    /**
     * Mark some properties as required
     */
    type Require<T, K extends keyof T> = T & { [P in K]-?: T[P] };

    /**
     * Mark some properties as optional
     */
    type Optional<T, K extends keyof T> = T & { [P in K]?: T[P] };

    /**
     * Mark some properties as optional
     */
    type Remove<T, K extends keyof T> = T & { [P in K]?: never };

    interface CustomContenaProperties {}

    interface CustomContenaConstants {}

    /**
     * Make the Contena object globally available
     */
    const Contena: ContenaClass & CustomContenaProperties;

    type Entity<EntityName extends keyof EntitySchema.Entities> = EntitySchema.Entity<EntityName>;
    type EntityCollection<EntityName extends keyof EntitySchema.Entities> = EntitySchema.EntityCollection<EntityName>;

    interface Window {
        Contena: ContenaClass & CustomContenaProperties;
        _features_: {
            [featureName: string]: boolean;
        };
        _sw_extension_component_collection: DevtoolComponent[];
        _swLoginOverrides?: Array<() => void>;
        startApplication: () => void;
        removePageLoadingIndicator: () => void;
    }

    const _features_: {
        [featureName: string]: boolean;
    };

    /**
     * Define global container for the bottle.js containers
     */
    interface ServiceContainer extends SubContainer<'service'> {
        acl: AclService;
        businessEventService: BusinessEventsApiService;
        blogTypeService: BlogTypeApiService;
        channelService: ChannelApiService;
        memberGroupRegistrationService: MemberGroupRegistrationApiService;
        mailService: MailApiService;
        cacheService: CacheService;
        contentSystemElementTypeService: $TSFixMe;
        contentSystemEntityTypeService: $TSFixMe;
        contentSystemLayoutDraftMutationService: $TSFixMe;
        contentSystemPreviewService: $TSFixMe;
        contentSystemStyleOptionService: $TSFixMe;
        flowActionService: FlowActionsApiService;
        configService: ConfigApiService;
        customFieldDataProviderService: $TSFixMe;
        entityFactory: $TSFixMe;
        entityHydrator: $TSFixMe;
        entityMappingService: $TSFixMe;
        entityValidationService: EntityValidationService;
        feature: FeatureService;
        fileValidationService: FileValidationService;
        dataDictionaryService: DataDictionaryService;
        filterFactory: FilterFactoryData;
        filterService: $TSFixMe;
        jsonApiParserService: typeof JsonApiParserService;
        languageAutoFetchingService: $TSFixMe;
        localeHelper: $TSFixMe;
        localeToLanguageService: $TSFixMe;
        loginService: LoginService;
        mediaDefaultFolderService: $TSFixMe;
        mediaService: MediaService;
        menuService: $TSFixMe;
        privileges: PrivilegesService;
        recentlySearchService: $TSFixMe;
        repositoryFactory: RepositoryFactory;
        ruleConditionsConfigApiService: RuleConditionsConfigApiService;
        searchPreferencesService: $TSFixMe;
        searchRankingService: $TSFixMe;
        searchTypeService: $TSFixMe;
        seoUrlService: $TSFixMe;
        seoUrlTemplateService: $TSFixMe;
        snippetService: SnippetApiService;
        snippetSetService: SnippetSetApiService;
        stateStyleDataProviderService: StateStyleService;
        systemConfigApiService: SystemConfigApiService;
        timezoneService: $TSFixMe;
        updateService: UpdateApiService;
        userRecoveryService: UserRecoveryApiService;
        userService: UserApiService;
        userConfigService: UserConfigService;
        validationService: $TSFixMe;
        validationApiService: ValidationApiService;
    }

    interface MixinContainer {
        notification: typeof NotificationMixin;
        'ct-inline-snippet': typeof SwInlineSnippetMixin;
        'translate-with-fallback': typeof TranslateWithFallbackMixin;
        placeholder: typeof PlaceholderMixin;
        'ct-extension-error': typeof SwExtensionErrorMixin;
    }

    interface InitContainer extends SubContainer<'init'> {
        router: $TSFixMe;
        httpClient: AxiosInstance;
    }
    interface InitPostContainer extends SubContainer<'init-post'> {}
    interface InitPreContainer extends SubContainer<'init-pre'> {
        apiServices: Promise<typeof ApiServiceFactory>;
    }
    interface FactoryContainer extends SubContainer<'factory'> {
        component: typeof AsyncComponentFactory;
        module: typeof ModuleFactory;
        entity: $TSFixMe;
        serviceFactory: $TSFixMe;
        classesFactory: $TSFixMe;
        mixin: $TSFixMe;
        directive: typeof DirectiveFactory;
        filter: typeof FilterFactory;
        locale: typeof LocaleFactory;
        plugin: $TSFixMe;
        apiService: typeof ApiServiceFactory;
        entityDefinition: typeof EntityDefinitionFactory;
    }

    interface FilterTypes {
        asset: (value: string) => string;
        date: (value: string, options?: Intl.DateTimeFormatOptions) => string;
        'file-size': $TSFixMeFunction;
        'media-name': $TSFixMeFunction;
        striphtml: (value: string) => string;
        'thumbnail-size': $TSFixMeFunction;
        truncate: $TSFixMeFunction;
        'unicode-uri': $TSFixMeFunction;
        [key: string]: ((...args: any[]) => any) | undefined;
    }

    interface ComponentHelper {
        mapState: typeof mapState;
        mapActions: typeof mapActions;
        mapPropertyErrors: typeof mapErrors.mapPropertyErrors;
        mapSystemConfigErrors: typeof mapErrors.mapSystemConfigErrors;
        mapCollectionPropertyErrors: typeof mapErrors.mapCollectionPropertyErrors;
        mapPageErrors: typeof mapErrors.mapPageErrors;
    }

    interface PiniaRootState {
        adminMenu: AdminMenuStore;
        routeTabs: RouteTabsStore;
        blockOverride: BlockOverrideStore;
        extensionEntryRoutes: ExtensionEntryRoutes;
        error: ErrorStore;
        experienceStudioEditor: ExperienceStudioEditorStore;
        context: ContextStore;
        adminHelpCenter: AdminHelpCenterStore;
        settingsItems: SettingsItems;
        system: System;
        notification: NotificationStore;
        session: SessionStore;
        contenaExtensions: ContenaExtensionsStore;
        swCategoryDetail: SwCategoryDetailStore;
        swBlogDetail: SwBlogDetailStore;
        swSeoUrl: SwSeoUrlStore;
        swProfile: SwProfileStore;
        swBulkEdit: SwBulkStore;
        mediaModal: MediaModalStore;
    }

    type PropType<T> = VuePropType<T>;

    /**
     * define global Component
     */
    type VueComponent = ComponentConfig;

    type apiContext = ContextStore['api'];

    type appContext = ContextStore['app'];

    /**
     * @see Contena\Core\Framework\Api\EventListener\ErrorResponseFactory
     */
    interface ContenaHttpError {
        code: string;
        status: string;
        title: string;
        detail: string;
        meta?: {
            file: string;
            line: string;
            trace?: { [key: string]: string };
            parameters?: object;
            previous?: ContenaHttpError;
        };
        trace?: { [key: string]: string };
    }

    interface ContenaErrorMeta {
        parameters: {
            dependency: string;
            dependantNames: string;
            assignments: string;
            [key: string]: unknown;
        };
        [key: string]: unknown;
    }

    interface ContenaError {
        code: string;
        meta: ContenaErrorMeta;
    }

    interface ContenaApiError {
        response: {
            data: {
                errors: ContenaError[];
            };
        };
    }

    const flushPromises: () => Promise<void>;

    /**
     * @private This is a private method and should not be used outside of the test suite
     */
    const wrapTestComponent: (componentName: string, config?: { sync?: boolean }) => Promise<VueComponent>;
}

/**
 * Link global bottle.js container to the bottle.js container interface
 */
declare module 'bottlejs' {
    // Use the same module name as the import string
    type IContainerChildren = 'factory' | 'service' | 'init' | 'init-post' | 'init-pre';

    interface IContainer {
        factory: FactoryContainer;
        service: ServiceContainer;
        'init-pre': InitPreContainer;
        init: InitContainer;
        'init-post': InitPostContainer;
    }
}

interface CustomProperties extends ServiceContainer {
    $createTitle: (identifier?: string | null) => string;
    $router: Router;
    $route: RouteLocationNormalizedLoaded;
    $te: I18n<{}, {}, {}, string, true>['global']['te'];
    $t: I18n<{}, {}, {}, string, true>['global']['t'];
    $sanitize: (dirtyHtml: string, config?: Record<string, unknown>) => string;
    $dataScope: ComponentInternalInstance['proxy'];
}

declare module '@vue/runtime-core' {
    interface App extends CustomProperties {}

    interface ComponentCustomProperties extends CustomProperties {}

    interface ComponentCustomOptions {
        extensionApiDevtoolInformation?: {
            property?: string;
            method?: string;
            positionId?: (currentComponent: any) => string;
            helpText?: string;
        };

        beforeRouteEnter?: (
            to: RouteLocationNormalized,
            from: RouteLocationNormalizedLoaded,
            next: NavigationGuardNext,
        ) => void;
        beforeRouteLeave?: (
            to: RouteLocationNormalized,
            from: RouteLocationNormalizedLoaded,
            next: NavigationGuardNext,
        ) => void;
    }

    interface PropOptions {
        validValues?: any[];
    }
}

declare module 'axios' {
    interface AxiosRequestConfig {
        // adds the contena API version to the RequestConfig
        version?: number;
    }
}
