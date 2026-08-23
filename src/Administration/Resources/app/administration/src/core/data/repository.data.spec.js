import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import RepositoryData from 'src/core/data/repository.data';
import IdCollection from 'test/_helper_/id.collection';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import CacheService from 'src/app/service/cache.service';

const clientMock = global.repositoryFactoryMock.clientMock;
const responses = global.repositoryFactoryMock.responses;
const repositoryFactory = Contena.Service('repositoryFactory');

function mockContext() {
    return {
        apiPath: 'http://contena.local/api',
        apiResourcePath: 'http://contena.local/api/v2',
        assetsPath: 'http://contena.local/bundles/',
        basePath: '',
        host: 'contena.local',
        inheritance: false,
        installationPath: 'http://contena.local',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        liveVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        pathInfo: '/admin',
        port: 80,
        scheme: 'http',
        schemeAndHttpHost: 'http://contena.local',
        uri: 'http://contena.local/admin',
        authToken: {
            access: 'BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco',
        },
    };
}

function createRepositoryData() {
    return new RepositoryData(undefined, undefined, undefined, undefined, undefined, undefined, undefined, {});
}

if (!Contena.Service('cacheService')) {
    Contena.Service().register('cacheService', () => new CacheService());
}

describe('repository.data.ts', () => {
    beforeEach(async () => {
        clientMock.resetHistory();
        Contena.Service('cacheService').clear();
    });

    it('should search with the criteria title', async () => {
        responses.addResponse({
            method: 'POST',
            url: '/search/media',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search-ids/media',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search/media?title=ImmaTest',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search-ids/media?title=ImmaTest',
            status: 200,
            response: {
                data: [],
            },
        });

        const repository = repositoryFactory.create('media');

        const criteriaWithoutTitle = new Criteria();
        const criteriaWithTitle = new Criteria();
        criteriaWithTitle.setTitle('ImmaTest');

        repository.search(criteriaWithoutTitle);
        repository.searchIds(criteriaWithoutTitle);

        expect(clientMock.history.post[0].url).toBe('/search/media');
        expect(clientMock.history.post[1].url).toBe('/search-ids/media');

        repository.search(criteriaWithTitle);
        repository.searchIds(criteriaWithTitle);

        expect(clientMock.history.post[2].url).toBe('/search/media?title=ImmaTest');
        expect(clientMock.history.post[3].url).toBe('/search-ids/media?title=ImmaTest');
    });

    it('should build the correct headers', async () => {
        const repositoryData = createRepositoryData('language');
        const actualHeaders = repositoryData.buildHeaders(mockContext());
        const exptectedHeaders = {
            'ct-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            Accept: 'application/vnd.api+json',
            Authorization: 'Bearer BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco',
            'Content-Type': 'application/json',
            'ct-api-compatibility': true,
        };

        expect(actualHeaders).toEqual(exptectedHeaders);
    });

    it('should pass repository reads through the cache service when cache options are provided', async () => {
        responses.addResponse({
            method: 'POST',
            url: '/search/media',
            status: 200,
            response: {
                data: [],
            },
        });

        const cacheService = Contena.Service('cacheService');
        const querySpy = jest.spyOn(cacheService, 'query');
        const repository = repositoryFactory.create('media');

        await repository.search(new Criteria(), {
            cacheKey: ['media'],
        });

        expect(querySpy).toHaveBeenCalledWith(
            expect.objectContaining({
                key: ['media'],
                fn: expect.any(Function),
            }),
        );
        expect(clientMock.history.post).toHaveLength(1);
    });

    it('should create one delete operation for multiple deletes', async () => {
        const ids = new IdCollection();

        responses.addResponse({
            method: 'Post',
            url: '_action/sync',
            status: 200,
            response: {},
        });

        const repository = repositoryFactory.create('tag', null, {
            useSync: true,
        });
        const context = Contena.Context.api;
        const tag = repository.create(context, ids.get('tag'));

        tag.name = 'test';

        const media = new EntityCollection(tag.media.source, tag.media.entity, tag.media.context, tag.media.criteria);

        const mediaRepository = repositoryFactory.create('media');
        media.add(mediaRepository.create(context, ids.get('media-1')));
        media.add(mediaRepository.create(context, ids.get('media-2')));
        media.add(mediaRepository.create(context, ids.get('media-3')));

        tag.getOrigin().media = media;

        const changesetGenerator = new ChangesetGenerator();
        const changes = changesetGenerator.generate(tag);

        expect(changes.deletionQueue).toHaveLength(3);

        // send the tag to the server
        await repository.save(tag);

        // expect that one request get send
        expect(clientMock.history.post).toHaveLength(1);

        // check that the request was created correctly
        const request = clientMock.history.post[0];

        expect(request.url).toBe('_action/sync');
        // axios-mock-adapter stores custom header values as strings in request history.
        expect(request.headers['single-operation']).toBe('true');

        expect(request.data).toEqual(
            JSON.stringify([
                {
                    action: 'delete',
                    payload: [
                        {
                            tagId: ids.get('tag'),
                            mediaId: ids.get('media-1'),
                        },
                        {
                            tagId: ids.get('tag'),
                            mediaId: ids.get('media-2'),
                        },
                        {
                            tagId: ids.get('tag'),
                            mediaId: ids.get('media-3'),
                        },
                    ],
                    entity: 'media_tag',
                },
                {
                    key: 'write',
                    action: 'upsert',
                    entity: 'tag',
                    payload: [
                        {
                            id: ids.get('tag'),
                            name: 'test',
                        },
                    ],
                },
            ]),
        );
    });

    it('should throw an 400 error when httpClient post call fails with error without source property', async () => {
        const mediaRepository = repositoryFactory.create('media');
        const media = mediaRepository.create();
        media.fileName = 'example';

        responses.filterResponses((response) => {
            return response.url !== '_action/sync';
        });

        responses.addResponse({
            method: 'POST',
            url: '_action/sync',
            status: 400,
            response: {
                errors: [
                    {
                        status: '400',
                        code: 'CONTENT__DUPLICATE_MEDIA_FILE_NAME',
                        title: 'Bad Request',
                        detail: 'Media with this file name already exists.',
                        meta: {
                            parameters: {
                                number: 'SW10000',
                            },
                        },
                    },
                ],
            },
        });

        let thrownError;

        try {
            await mediaRepository.saveWithSync(media);
        } catch (e) {
            thrownError = e;
        }

        expect(thrownError.message).toBe('Request failed with status code 400');
    });
});
