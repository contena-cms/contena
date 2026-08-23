type ServiceObject = {
    get: <SN extends keyof ServiceContainer>(serviceName: SN) => ServiceContainer[SN];
    list: () => (keyof ServiceContainer)[];
    register: typeof Contena.Application.addServiceProvider;
    registerMiddleware: typeof Contena.Application.addServiceProviderMiddleware;
    registerDecorator: typeof Contena.Application.addServiceProviderDecorator;
};

/**
 * Return the ServiceObject (Contena.Service().myService)
 * or direct access the services (Contena.Service('myService')
 */
function serviceAccessor<SN extends keyof ServiceContainer>(serviceName: SN): ServiceContainer[SN];
function serviceAccessor(): ServiceObject;
function serviceAccessor<SN extends keyof ServiceContainer>(serviceName?: SN): ServiceContainer[SN] | ServiceObject {
    if (serviceName) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return Contena.Application.getContainer('service')[serviceName];
    }

    const serviceObject: ServiceObject = {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        get: (name) => Contena.Application.getContainer('service')[name],
        list: () => Contena.Application.getContainer('service').$list(),
        register: (name, service) => Contena.Application.addServiceProvider(name, service),
        registerMiddleware: (...args) => Contena.Application.addServiceProviderMiddleware(...args),
        registerDecorator: (...args) => Contena.Application.addServiceProviderDecorator(...args),
    };

    return serviceObject;
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default serviceAccessor;
