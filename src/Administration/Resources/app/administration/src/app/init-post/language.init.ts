// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initLanguageService() {
    Contena.Application.addServiceProviderMiddleware('repositoryFactory', (repositoryFactory) => {
        // load the language when repositoryFactory is created
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        Contena.Application.getContainer('service').languageAutoFetchingService;

        return repositoryFactory;
    });
}
