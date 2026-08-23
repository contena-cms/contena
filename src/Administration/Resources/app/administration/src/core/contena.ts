/**
 *
 * Contena End Developer API
 * @module Contena
 * @ignore
 */
import Bottle from 'bottlejs';

import ModuleFactory from 'src/core/factory/module.factory';
import AsyncComponentFactory from 'src/core/factory/async-component.factory';
import ServiceFactory from 'src/core/factory/service.factory';
import ClassesFactory from 'src/core/factory/classes-factory';
import MixinFactory from 'src/core/factory/mixin.factory';
import FilterFactory from 'src/core/factory/filter.factory';
import DirectiveFactory from 'src/core/factory/directive.factory';
import LocaleFactory from 'src/core/factory/locale.factory';
import PluginBootFactory from 'src/core/factory/plugin-boot.factory';
import ApiServiceFactory from 'src/core/factory/api-service.factory';
import EntityDefinitionFactory from 'src/core/factory/entity-definition.factory';

import Feature from 'src/core/feature';
import ContenaError from 'src/core/data/ContenaError';
import ApiService from 'src/core/service/api.service';
import utils from 'src/core/service/util.service';
import FlatTreeHelper from 'src/core/helper/flattree.helper';
import SanitizerHelper from 'src/core/helper/sanitizer.helper';
import DeviceHelper from 'src/core/helper/device.helper';
import MiddlewareHelper from 'src/core/helper/middleware.helper';
import data from 'src/core/data/index';
import ApplicationBootstrapper from 'src/core/application';

import HttpFactory from 'src/core/factory/http.factory';
import RepositoryFactory from 'src/core/data/repository-factory.data';
import ApiContextFactory from 'src/core/factory/api-context.factory';
import AppContextFactory from 'src/core/factory/app-context.factory';
import RouterFactory from 'src/core/factory/router.factory';
import ApiServices from 'src/core/service/api';
import ModuleFilterFactory from 'src/core/data/filter-factory.data';
import Store from 'src/app/store';
import {
    attachOverrides,
    createExtendableSetup,
    overrideComponentSetup,
} from 'src/app/adapter/composition-extension-system';
import * as Vue from 'vue';
import * as VueI18n from 'vue-i18n';
import * as VueRouter from 'vue-router';
import type { Component, Ref } from 'vue';
import Telemetry from './telemetry';
import useContext from '../app/composables/use-context';

/** Initialize feature flags at the beginning */
if (window.hasOwnProperty('_features_')) {
    Feature.init(_features_);
}

// strict mode was set to false because it was defined wrong previously
Bottle.config = { strict: false };
const container = new Bottle();

const application = new ApplicationBootstrapper(container);

application
    .addFactory('component', () => {
        return AsyncComponentFactory;
    })
    .addFactory('module', () => {
        return ModuleFactory;
    })
    .addFactory('serviceFactory', () => {
        return ServiceFactory;
    })
    .addFactory('classesFactory', () => {
        return ClassesFactory;
    })
    .addFactory('mixin', () => {
        return MixinFactory;
    })
    .addFactory('filter', () => {
        return FilterFactory;
    })
    .addFactory('directive', () => {
        return DirectiveFactory;
    })
    .addFactory('locale', () => {
        return LocaleFactory;
    })
    .addFactory('plugin', () => {
        return PluginBootFactory;
    })
    .addFactory('apiService', () => {
        return ApiServiceFactory;
    })
    .addFactory('entityDefinition', () => {
        return EntityDefinitionFactory;
    });

class ContenaClass implements CustomContenaProperties {
    /**
     * @private
     */
    static #overrideComponents: Ref<Component[]> = Vue.ref([]);

    public Module = {
        register: ModuleFactory.registerModule,
        getModuleRegistry: ModuleFactory.getModuleRegistry,
        getModuleRoutes: ModuleFactory.getModuleRoutes,
        getModuleByEntityName: ModuleFactory.getModuleByEntityName,
    };

    public Component = {
        register: AsyncComponentFactory.register,
        extend: AsyncComponentFactory.extend,
        override: AsyncComponentFactory.override,
        build: AsyncComponentFactory.build,
        wrapComponentConfig: AsyncComponentFactory.wrapComponentConfig,
        getComponentRegistry: AsyncComponentFactory.getComponentRegistry,
        getComponentHelper: AsyncComponentFactory.getComponentHelper,
        registerComponentHelper: AsyncComponentFactory.registerComponentHelper,
        markComponentAsSync: AsyncComponentFactory.markComponentAsSync,
        isSyncComponent: AsyncComponentFactory.isSyncComponent,
        getExtensionParentSetup: AsyncComponentFactory.getExtensionParentSetup,
        getOverrideRegistry: AsyncComponentFactory.getOverrideRegistry,
        createExtendableSetup: createExtendableSetup,
        attachOverrides: attachOverrides,
        overrideComponentSetup: overrideComponentSetup,

        /**
         */
        registerOverrideComponent: (component: Component) => {
            ContenaClass.#overrideComponents.value.push(component);
        },
        /**
         */
        getOverrideComponents: () => {
            return ContenaClass.#overrideComponents.value;
        },
    };

    public Store = Store.instance;

    public Mixin = {
        register: MixinFactory.register,
        getByName: MixinFactory.getByName,
    };

    public Filter = {
        register: FilterFactory.register,
        getByName: FilterFactory.getByName,
        getRegistry: FilterFactory.getRegistry,
    };

    public Directive = {
        register: DirectiveFactory.registerDirective,
        getByName: DirectiveFactory.getDirectiveByName,
        getDirectiveRegistry: DirectiveFactory.getDirectiveRegistry,
    };

    public Locale = {
        register: LocaleFactory.register,
        extend: LocaleFactory.extend,
        getByName: LocaleFactory.getLocaleByName,
        getLocaleRegistry: LocaleFactory.getLocaleRegistry,
    };

    public Plugin = {
        addBootPromise: PluginBootFactory.addBootPromise,
        getBootPromises: PluginBootFactory.getBootPromises,
    };

    public Service = ServiceFactory;

    public Utils = utils;

    public Application = application;

    public Feature = Feature;

    public Vue = Vue;

    public VueI18n = VueI18n;

    public VueRouter = VueRouter;

    public ApiService = {
        register: ApiServiceFactory.register,
        getByName: ApiServiceFactory.getByName,
        getRegistry: ApiServiceFactory.getRegistry,
        getServices: ApiServiceFactory.getServices,
        has: ApiServiceFactory.has,
    };

    public EntityDefinition = {
        getScalarTypes: EntityDefinitionFactory.getScalarTypes,
        getJsonTypes: EntityDefinitionFactory.getJsonTypes,
        getDefinitionRegistry: EntityDefinitionFactory.getDefinitionRegistry,
        has: EntityDefinitionFactory.has,
        get: EntityDefinitionFactory.get,
        add: EntityDefinitionFactory.add,
        remove: EntityDefinitionFactory.remove,
        getTranslatedFields: EntityDefinitionFactory.getTranslatedFields,
        getAssociationFields: EntityDefinitionFactory.getAssociationFields,
        getRequiredFields: EntityDefinitionFactory.getRequiredFields,
    };

    public Defaults = {
        systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        defaultLanguageIds: ['2fbb5fe2e29a4d70aa5854ce7ce3e20b'],
        versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        apiChannelTypeId: 'f183ee5650cf4bdb8a774337575067a6',
        webChannelTypeId: '8a243080f92e4c719546314b577cf82b',
    };

    public Data = data;

    public get Snippet() {
        // @ts-expect-error - type is currently not available
        if (!Contena.Application.view?.i18n) {
            return null;
        }

        return {
            // @ts-expect-error - type is currently not available
            ...Contena.Application.view.i18n.global,
            // @ts-expect-error - type is currently not available
            tc: Contena.Application.view.i18n.global.t,
        };
    }

    public Classes = {
        ContenaError,
        ApiService,
        _private: {
            HttpFactory,
            RepositoryFactory,
            ApiContextFactory,
            AppContextFactory,
            RouterFactory,
            FilterFactory: ModuleFilterFactory,
        },
    };

    public Constants: CustomContenaConstants = {};

    public Helper = {
        FlatTreeHelper: FlatTreeHelper,
        MiddlewareHelper: MiddlewareHelper,
        SanitizerHelper: SanitizerHelper,
        DeviceHelper: DeviceHelper,
    };

    public get Context() {
        return useContext();
    }

    public _private = {
        ApiServices: ApiServices,
    };

    public Telemetry = Telemetry;
}

const ContenaInstance = new ContenaClass();

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export { ContenaClass, ContenaInstance };
