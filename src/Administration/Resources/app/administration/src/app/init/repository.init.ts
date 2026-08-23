const RepositoryFactory = Contena.Classes._private.RepositoryFactory;
const { EntityHydrator, ChangesetGenerator, EntityFactory } = Contena.Data;
const ErrorResolverError = Contena.Data.ErrorResolver;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initializeRepositoryFactory(container: InitContainer) {
    const httpClient = container.httpClient;
    const factoryContainer = Contena.Application.getContainer('factory');
    const serviceContainer = Contena.Application.getContainer('service');

    return httpClient
        .get('_info/entity-schema.json', {
            headers: {
                Authorization: `Bearer ${serviceContainer.loginService.getToken()}`,
            },
        })
        .then(({ data }) => {
            const entityDefinitionFactory = factoryContainer.entityDefinition;

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            Object.entries(data).forEach(
                ([
                    key,
                    value,
                ]) => {
                    entityDefinitionFactory.add(key, value);
                },
            );

            const hydrator = new EntityHydrator();
            const changesetGenerator = new ChangesetGenerator();
            const entityFactory = new EntityFactory();
            const errorResolver = new ErrorResolverError();

            Contena.Application.addServiceProvider('repositoryFactory', () => {
                return new RepositoryFactory(hydrator, changesetGenerator, entityFactory, httpClient, errorResolver);
            });
            Contena.Application.addServiceProvider('entityHydrator', () => {
                return hydrator;
            });
            Contena.Application.addServiceProvider('entityFactory', () => {
                return entityFactory;
            });
        });
}
