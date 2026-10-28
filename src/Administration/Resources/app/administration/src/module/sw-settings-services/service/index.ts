/**
 * @sw-package framework
 */
import type { SubContainer } from '../../../global.types';
import ContenaServicesService from './contena-services.service';
import ServiceRegistryClient from './service-registry-client';

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        contenaServicesService: ContenaServicesService;
        serviceRegistryClient: ServiceRegistryClient;
    }
}

/**
 * @private
 */
Contena.Service().register('contenaServicesService', () => {
    return new ContenaServicesService(
        Contena.Application.getContainer('init').httpClient,
        Contena.Service('loginService'),
        Contena.Service('systemConfigApiService'),
    );
});

/**
 * @private
 */
Contena.Service().register('serviceRegistryClient', () => {
    return new ServiceRegistryClient(Contena.Context.api.serviceRegistryUrl!);
});
