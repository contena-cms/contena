import initializeRepositoryFactory from './repository.init';

const entityDefinitions = {
    media: { entity: 'media' },
    custom_entity_example: {
        entity: 'custom_entity_example',
        flags: {
            'admin-ui': {},
            'cms-aware': {},
        },
    },
};

describe('init/repository', () => {
    let entityDefinitionFactory;
    let addServiceProvider;

    beforeEach(() => {
        entityDefinitionFactory = {
            add: jest.fn(),
        };
        addServiceProvider = jest.fn();

        Contena.Application = {
            getContainer(containerName) {
                if (containerName === 'factory') {
                    return { entityDefinition: entityDefinitionFactory };
                }

                if (containerName === 'service') {
                    return {
                        loginService: {
                            getToken: () => 'token',
                        },
                    };
                }

                throw new Error(`Container for ${containerName} is not mocked`);
            },
            addServiceProvider,
        };
    });

    it('registers every backend entity definition without deriving App or CMS UI metadata', async () => {
        await initializeRepositoryFactory({
            httpClient: {
                get: jest.fn().mockResolvedValue({ data: entityDefinitions }),
            },
        });

        expect(entityDefinitionFactory.add).toHaveBeenCalledTimes(2);
        expect(entityDefinitionFactory.add).toHaveBeenNthCalledWith(1, 'media', entityDefinitions.media);
        expect(entityDefinitionFactory.add).toHaveBeenNthCalledWith(
            2,
            'custom_entity_example',
            entityDefinitions.custom_entity_example,
        );
        expect(addServiceProvider).toHaveBeenCalledWith('repositoryFactory', expect.any(Function));
        expect(addServiceProvider).toHaveBeenCalledWith('entityHydrator', expect.any(Function));
        expect(addServiceProvider).toHaveBeenCalledWith('entityFactory', expect.any(Function));
    });
});
