/**
 * @sw-package framework
 */
import initializeApiServices from 'src/app/init-pre/api-services.init';

function removeServiceProvider(serviceName) {
    const container = Contena.Application.$container;
    const serviceContainer = container.nested.service;

    delete container.providerMap[`service.${serviceName}`];
    delete container.originalProviders[`service.${serviceName}`];
    delete serviceContainer.providerMap[serviceName];
    delete serviceContainer.originalProviders[serviceName];
    delete serviceContainer.container[serviceName];
}

function registerUserConfigServiceMock() {
    Contena.Service().register('admin_userConfigService', () => ({
        search: jest.fn(() => Promise.resolve({ data: {} })),
        upsert: jest.fn(() => Promise.resolve()),
    }));
}

describe('src/app/init-pre/api-services.init.ts', () => {
    beforeEach(() => {
        removeServiceProvider('admin_userConfigService');

        Contena._private.ApiServices = jest.fn(() => {
            const services = [];
            const serviceNames = [
                'aclApiService',
                'appActionButtonService',
                'appCmsBlocks',
                'appModulesService',
                'appUrlChangeService',
                'businessEventService',
                'cacheApiService',
                'calculate-price',
                'cartStoreService',
                'checkoutStoreService',
                'configService',
                'customSnippetApiService',
                'userGroupRegistrationService',
                'userValidationService',
                'documentService',
                'documentV2Service',
                'excludedSearchTermService',
                'extensionSdkService',
                'firstRunWizardService',
                'flowActionService',
                'importExportService',
                'integrationService',
                'knownIpsService',
                'languagePluginService',
                'mailService',
                'mediaFolderService',
                'mediaService',
                'messageQueueService',
                'notificationsService',
                'numberRangeService',
                'orderDocumentApiService',
                'orderStateMachineService',
                'orderService',
                'blogExportService',
                'contentStreamPreviewService',
                'promotionSyncService',
                'recommendationsService',
                'ruleConditionsConfigApiService',
                'channelService',
                'scheduledTaskService',
                'searchService',
                'seoUrlTemplateService',
                'seoUrlService',
                'snippetSetService',
                'snippetService',
                'stateMachineService',
                'contextStoreService',
                'storeService',
                'syncService',
                'systemConfigApiService',
                'tagApiService',
                'updateService',
                'admin_userActivityApiService',
                'admin_userConfigService',
                'admin_userInputSanitizeService',
                'admin_userRecoveryService',
                'admin_userValidationService',
                'admin_userService',
                'shopIdChangeService',
            ];

            serviceNames.forEach((serviceName) => {
                const MockApiClass = class {
                    constructor() {
                        this.name = serviceName;
                    }
                };
                services.push(MockApiClass);
            });

            return services;
        });
    });

    afterEach(() => {
        removeServiceProvider('admin_userConfigService');
        registerUserConfigServiceMock();
    });

    it('should initialize the api services', async () => {
        expect(Contena.Service('aclApiService')).toBeUndefined();
        expect(Contena.Service('appActionButtonService')).toBeUndefined();
        expect(Contena.Service('appCmsBlocks')).toBeUndefined();
        expect(Contena.Service('appModulesService')).toBeUndefined();
        expect(Contena.Service('appUrlChangeService')).toBeUndefined();
        expect(Contena.Service('businessEventService')).toBeUndefined();
        expect(Contena.Service('cacheApiService')).toBeUndefined();
        expect(Contena.Service('calculate-price')).toBeUndefined();
        expect(Contena.Service('cartStoreService')).toBeUndefined();
        expect(Contena.Service('checkoutStoreService')).toBeUndefined();
        expect(Contena.Service('configService')).toBeUndefined();
        expect(Contena.Service('customSnippetApiService')).toBeUndefined();
        expect(Contena.Service('userGroupRegistrationService')).toBeUndefined();
        expect(Contena.Service('userValidationService')).toBeUndefined();
        expect(Contena.Service('documentService')).toBeUndefined();
        expect(Contena.Service('documentV2Service')).toBeUndefined();
        expect(Contena.Service('excludedSearchTermService')).toBeUndefined();
        expect(Contena.Service('extensionSdkService')).toBeUndefined();
        expect(Contena.Service('firstRunWizardService')).toBeUndefined();
        expect(Contena.Service('flowActionService')).toBeUndefined();
        expect(Contena.Service('importExportService')).toBeUndefined();
        expect(Contena.Service('integrationService')).toBeUndefined();
        expect(Contena.Service('knownIpsService')).toBeUndefined();
        expect(Contena.Service('languagePluginService')).toBeUndefined();
        expect(Contena.Service('mailService')).toBeUndefined();
        expect(Contena.Service('mediaFolderService')).toBeUndefined();
        expect(Contena.Service('mediaService')).toBeUndefined();
        expect(Contena.Service('messageQueueService')).toBeUndefined();
        expect(Contena.Service('notificationsService')).toBeUndefined();
        expect(Contena.Service('numberRangeService')).toBeUndefined();
        expect(Contena.Service('orderDocumentApiService')).toBeUndefined();
        expect(Contena.Service('orderStateMachineService')).toBeUndefined();
        expect(Contena.Service('orderService')).toBeUndefined();
        expect(Contena.Service('blogExportService')).toBeUndefined();
        expect(Contena.Service('contentStreamPreviewService')).toBeUndefined();
        expect(Contena.Service('promotionSyncService')).toBeUndefined();
        expect(Contena.Service('recommendationsService')).toBeUndefined();
        expect(Contena.Service('ruleConditionsConfigApiService')).toBeUndefined();
        expect(Contena.Service('channelService')).toBeUndefined();
        expect(Contena.Service('scheduledTaskService')).toBeUndefined();
        expect(Contena.Service('searchService')).toBeUndefined();
        expect(Contena.Service('seoUrlTemplateService')).toBeUndefined();
        expect(Contena.Service('seoUrlService')).toBeUndefined();
        expect(Contena.Service('snippetSetService')).toBeUndefined();
        expect(Contena.Service('snippetService')).toBeUndefined();
        expect(Contena.Service('stateMachineService')).toBeUndefined();
        expect(Contena.Service('contextStoreService')).toBeUndefined();
        expect(Contena.Service('storeService')).toBeUndefined();
        expect(Contena.Service('syncService')).toBeUndefined();
        expect(Contena.Service('systemConfigApiService')).toBeUndefined();
        expect(Contena.Service('tagApiService')).toBeUndefined();
        expect(Contena.Service('updateService')).toBeUndefined();
        expect(Contena.Service('admin_userActivityApiService')).toBeUndefined();
        expect(Contena.Service('admin_userConfigService')).toBeUndefined();
        expect(Contena.Service('admin_userInputSanitizeService')).toBeUndefined();
        expect(Contena.Service('admin_userRecoveryService')).toBeUndefined();
        expect(Contena.Service('admin_userValidationService')).toBeUndefined();
        expect(Contena.Service('admin_userService')).toBeUndefined();
        expect(Contena.Service('shopIdChangeService')).toBeUndefined();

        await initializeApiServices();

        expect(Contena.Service('aclApiService')).toBeDefined();
        expect(Contena.Service('appActionButtonService')).toBeDefined();
        expect(Contena.Service('appCmsBlocks')).toBeDefined();
        expect(Contena.Service('appModulesService')).toBeDefined();
        expect(Contena.Service('appUrlChangeService')).toBeDefined();
        expect(Contena.Service('businessEventService')).toBeDefined();
        expect(Contena.Service('cacheApiService')).toBeDefined();
        expect(Contena.Service('calculate-price')).toBeDefined();
        expect(Contena.Service('cartStoreService')).toBeDefined();
        expect(Contena.Service('checkoutStoreService')).toBeDefined();
        expect(Contena.Service('configService')).toBeDefined();
        expect(Contena.Service('customSnippetApiService')).toBeDefined();
        expect(Contena.Service('userGroupRegistrationService')).toBeDefined();
        expect(Contena.Service('userValidationService')).toBeDefined();
        expect(Contena.Service('documentService')).toBeDefined();
        expect(Contena.Service('documentV2Service')).toBeDefined();
        expect(Contena.Service('excludedSearchTermService')).toBeDefined();
        expect(Contena.Service('extensionSdkService')).toBeDefined();
        expect(Contena.Service('firstRunWizardService')).toBeDefined();
        expect(Contena.Service('flowActionService')).toBeDefined();
        expect(Contena.Service('importExportService')).toBeDefined();
        expect(Contena.Service('integrationService')).toBeDefined();
        expect(Contena.Service('knownIpsService')).toBeDefined();
        expect(Contena.Service('languagePluginService')).toBeDefined();
        expect(Contena.Service('mailService')).toBeDefined();
        expect(Contena.Service('mediaFolderService')).toBeDefined();
        expect(Contena.Service('mediaService')).toBeDefined();
        expect(Contena.Service('messageQueueService')).toBeDefined();
        expect(Contena.Service('notificationsService')).toBeDefined();
        expect(Contena.Service('numberRangeService')).toBeDefined();
        expect(Contena.Service('orderDocumentApiService')).toBeDefined();
        expect(Contena.Service('orderStateMachineService')).toBeDefined();
        expect(Contena.Service('orderService')).toBeDefined();
        expect(Contena.Service('blogExportService')).toBeDefined();
        expect(Contena.Service('contentStreamPreviewService')).toBeDefined();
        expect(Contena.Service('promotionSyncService')).toBeDefined();
        expect(Contena.Service('recommendationsService')).toBeDefined();
        expect(Contena.Service('ruleConditionsConfigApiService')).toBeDefined();
        expect(Contena.Service('channelService')).toBeDefined();
        expect(Contena.Service('scheduledTaskService')).toBeDefined();
        expect(Contena.Service('searchService')).toBeDefined();
        expect(Contena.Service('seoUrlTemplateService')).toBeDefined();
        expect(Contena.Service('seoUrlService')).toBeDefined();
        expect(Contena.Service('snippetSetService')).toBeDefined();
        expect(Contena.Service('snippetService')).toBeDefined();
        expect(Contena.Service('stateMachineService')).toBeDefined();
        expect(Contena.Service('contextStoreService')).toBeDefined();
        expect(Contena.Service('storeService')).toBeDefined();
        expect(Contena.Service('syncService')).toBeDefined();
        expect(Contena.Service('systemConfigApiService')).toBeDefined();
        expect(Contena.Service('tagApiService')).toBeDefined();
        expect(Contena.Service('updateService')).toBeDefined();
        expect(Contena.Service('admin_userActivityApiService')).toBeDefined();
        expect(Contena.Service('admin_userConfigService')).toBeDefined();
        expect(Contena.Service('admin_userInputSanitizeService')).toBeDefined();
        expect(Contena.Service('admin_userRecoveryService')).toBeDefined();
        expect(Contena.Service('admin_userValidationService')).toBeDefined();
        expect(Contena.Service('admin_userService')).toBeDefined();
        expect(Contena.Service('shopIdChangeService')).toBeDefined();
    });
});
