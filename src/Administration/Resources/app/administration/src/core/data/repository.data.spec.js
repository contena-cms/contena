/**
 * @sw-package framework
 */

import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import RepositoryData from 'src/core/data/repository.data';
import IdCollection from 'test/_helper_/id.collection';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import CacheService from 'src/app/service/cache.service';

const clientMock = global.repositoryFactoryMock.clientMock;
const responses = global.repositoryFactoryMock.responses;
const repositoryFactory = Contena.Service('repositoryFactory');
const DEFAULT_CURRENCY = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';

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
        currencyId: '7924299acc9641bfb8237a06e5aa0fa4',
        liveVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        pathInfo: '/admin',
        port: 80,
        scheme: 'http',
        schemeAndHttpHost: 'http://contena.local',
        uri: 'http://contena.local/admin',
        authToken: {
            access: 'BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco',
        },
        measurementLengthUnit: null,
        measurementWeightUnit: null,
    };
}

function createRepositoryData() {
    return new RepositoryData(undefined, undefined, undefined, undefined, undefined, undefined, undefined, {});
}

if (!Contena.Service('cacheService')) {
    Contena.Service().register('cacheService', () => new CacheService());
}

describe('repository.data.ts', () => {
    const originalFetch = global.fetch;
    const originalBaseUrl = global.repositoryFactoryMock.httpClient.defaults.baseURL;

    beforeEach(async () => {
        clientMock.resetHistory();
        Contena.Service('cacheService').clear();
    });

    afterEach(() => {
        global.fetch = originalFetch;
        global.repositoryFactoryMock.httpClient.defaults.baseURL = originalBaseUrl;
    });

    it('should search with the criteria title', async () => {
        responses.addResponse({
            method: 'POST',
            url: '/search/blog',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search-ids/blog',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search/blog?title=ImmaTest',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search-ids/blog?title=ImmaTest',
            status: 200,
            response: {
                data: [],
            },
        });

        const repository = repositoryFactory.create('blog', null, {
            useAxiosV1: false,
        });

        const criteriaWithoutTitle = new Criteria();
        const criteriaWithTitle = new Criteria();
        criteriaWithTitle.setTitle('ImmaTest');

        repository.search(criteriaWithoutTitle);
        repository.searchIds(criteriaWithoutTitle);

        expect(clientMock.history.post[0].url).toBe('/search/blog');
        expect(clientMock.history.post[1].url).toBe('/search-ids/blog');

        repository.search(criteriaWithTitle);
        repository.searchIds(criteriaWithTitle);

        expect(clientMock.history.post[2].url).toBe('/search/blog?title=ImmaTest');
        expect(clientMock.history.post[3].url).toBe('/search-ids/blog?title=ImmaTest');
    });

    it('should use axios v1 for repository requests regardless of repository options', async () => {
        responses.addResponse({
            method: 'POST',
            url: '/search/blog',
            status: 200,
            response: {
                data: [],
            },
        });

        const repository = repositoryFactory.create('blog');

        await repository.search(new Criteria());

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].useAxiosV1).toBe(true);
    });

    it('should build the correct headers', async () => {
        const repositoryData = createRepositoryData('language');
        const actualHeaders = repositoryData.buildHeaders(mockContext());
        const exptectedHeaders = {
            'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            Accept: 'application/vnd.api+json',
            Authorization: 'Bearer BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco',
            'Content-Type': 'application/json',
            'sw-api-compatibility': true,
            'sw-currency-id': '7924299acc9641bfb8237a06e5aa0fa4',
        };

        expect(actualHeaders).toEqual(exptectedHeaders);
    });

    it('should delete a version with a keepalive request', async () => {
        global.fetch = jest.fn(() => Promise.resolve());
        const repository = repositoryFactory.create('order');
        repository.httpClient.defaults.baseURL = 'http://contena.local/api';

        await repository.deleteVersionWithKeepalive('order-id', 'version-id', mockContext());

        expect(global.fetch).toHaveBeenCalledWith('http://contena.local/api/_action/version/version-id/order/order-id', {
            method: 'POST',
            headers: {
                Accept: 'application/vnd.api+json',
                Authorization: 'Bearer BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco',
                'Content-Type': 'application/json',
                'sw-api-compatibility': 'true',
                'sw-currency-id': '7924299acc9641bfb8237a06e5aa0fa4',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            },
            body: '{}',
            keepalive: true,
        });
    });

    it('should fall back to the HTTP client when fetch is unavailable', async () => {
        global.fetch = undefined;
        const repository = repositoryFactory.create('order');
        responses.addResponse({
            method: 'POST',
            url: '/_action/version/version-id/order/order-id',
            status: 200,
            response: {},
        });

        await repository.deleteVersionWithKeepalive('order-id', 'version-id', mockContext());

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe('/_action/version/version-id/order/order-id');
    });

    it('should include measurement units in headers', async () => {
        const repositoryData = createRepositoryData('language');
        const context = {
            ...mockContext(),
            measurementLengthUnit: 'cm',
            measurementWeightUnit: 'kg',
        };
        const actualHeaders = repositoryData.buildHeaders(context);

        expect(actualHeaders['sw-measurement-length-unit']).toBe('cm');
        expect(actualHeaders['sw-measurement-weight-unit']).toBe('kg');
    });

    it('should pass repository reads through the cache service when cache options are provided', async () => {
        responses.addResponse({
            method: 'POST',
            url: '/search/blog',
            status: 200,
            response: {
                data: [],
            },
        });

        const cacheService = Contena.Service('cacheService');
        const querySpy = jest.spyOn(cacheService, 'query');
        const repository = repositoryFactory.create('blog');

        await repository.search(new Criteria(), {
            cacheKey: ['blogs'],
        });

        expect(querySpy).toHaveBeenCalledWith(
            expect.objectContaining({
                key: ['blogs'],
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

        const repository = repositoryFactory.create('blog', null, {
            useSync: true,
        });
        const context = Contena.Context.api;
        const blog = repository.create(context, ids.get('blog'));

        blog.name = 'test';
        blog.blogNumber = ids.get('blog');
        blog.stock = 10;
        blog.price = [{ currencyId: DEFAULT_CURRENCY, gross: 15, net: 10, linked: false }];
        blog.tax = { name: 'test', taxRate: 15 };

        const categories = new EntityCollection(
            blog.categories.source,
            blog.categories.entity,
            blog.categories.context,
            blog.categories.criteria,
        );

        let factory = repositoryFactory.create('category');
        categories.add(factory.create(context, ids.get('cat-1')));
        categories.add(factory.create(context, ids.get('cat-2')));
        categories.add(factory.create(context, ids.get('cat-3')));

        const properties = new EntityCollection(
            blog.properties.source,
            blog.properties.entity,
            blog.properties.context,
            blog.properties.criteria,
        );

        factory = repositoryFactory.create('property_group_option');
        properties.add(factory.create(context, ids.get('option-1')));
        properties.add(factory.create(context, ids.get('option-2')));
        properties.add(factory.create(context, ids.get('option-3')));

        blog.getOrigin().properties = properties;
        blog.getOrigin().categories = categories;

        const changesetGenerator = new ChangesetGenerator();
        const changes = changesetGenerator.generate(blog);

        expect(changes.deletionQueue).toHaveLength(6);

        // send new blog to the server
        await repository.save(blog);

        // expect that one request get send
        expect(clientMock.history.post).toHaveLength(1);

        // check that request for the blog creation was created correctly
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
                            blogId: ids.get('blog'),
                            optionId: ids.get('option-1'),
                        },
                        {
                            blogId: ids.get('blog'),
                            optionId: ids.get('option-2'),
                        },
                        {
                            blogId: ids.get('blog'),
                            optionId: ids.get('option-3'),
                        },
                    ],
                    entity: 'blog_property',
                },
                {
                    action: 'delete',
                    payload: [
                        {
                            blogId: ids.get('blog'),
                            categoryId: ids.get('cat-1'),
                        },
                        {
                            blogId: ids.get('blog'),
                            categoryId: ids.get('cat-2'),
                        },
                        {
                            blogId: ids.get('blog'),
                            categoryId: ids.get('cat-3'),
                        },
                    ],
                    entity: 'blog_category',
                },
                {
                    key: 'write',
                    action: 'upsert',
                    entity: 'blog',
                    payload: [
                        {
                            id: ids.get('blog'),
                            price: [
                                {
                                    currencyId: DEFAULT_CURRENCY,
                                    gross: 15,
                                    net: 10,
                                    linked: false,
                                },
                            ],
                            blogNumber: ids.get('blog'),
                            stock: 10,
                            name: 'test',
                        },
                    ],
                },
            ]),
        );
    });

    it('should throw an 400 error when httpClient post call fails with error without source property', async () => {
        const blogRepository = repositoryFactory.create('blog');
        const blog = blogRepository.create();
        blog.name = 'Our amazing blog';

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
                        code: 'CONTENT__DUPLICATE_BLOG_NUMBER',
                        title: 'Bad Request',
                        detail: 'Blog with number "SW10000" already exists.',
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
            await blogRepository.saveWithSync(blog);
        } catch (e) {
            thrownError = e;
        }

        expect(thrownError.message).toBe('Request failed with status code 400');
    });

    describe('api errors on delete', () => {
        const oldId = 'a5a4cd8fa3434cc6a8e2a7b7a9d0b3a1';
        const newId = 'b1f6a8d2c4e94d7f9a3b5c7d9e1f2a3b';

        beforeEach(() => {
            Contena.Store.get('error').resetApiErrors();
            Contena.Store.get('error').addApiError({
                expression: `tax.${oldId}`,
                error: { code: 'FRAMEWORK__DELETE_RESTRICTED' },
            });
        });

        it('should clear old api errors when a delete succeeds', async () => {
            responses.addResponse({ method: 'DELETE', url: `/tax/${newId}`, status: 204, response: {} });

            await repositoryFactory.create('tax').delete(newId);

            expect(Contena.Store.get('error').api).toEqual({});
        });

        it('should clear old api errors when a sync delete succeeds', async () => {
            responses.addResponse({ method: 'POST', url: '_action/sync', status: 200, response: { success: true } });

            await repositoryFactory.create('tax').syncDeleted([newId]);

            expect(Contena.Store.get('error').api).toEqual({});
        });

        it('should keep old api errors on delete when keepApiErrors is set', async () => {
            responses.addResponse({ method: 'DELETE', url: `/tax/${newId}`, status: 204, response: {} });

            await repositoryFactory.create('tax', null, { keepApiErrors: true }).delete(newId);

            expect(Contena.Store.get('error').getErrorsForEntity('tax', oldId)).toMatchObject({
                code: 'FRAMEWORK__DELETE_RESTRICTED',
            });
        });

        it('should keep old api errors on sync delete when keepApiErrors is set', async () => {
            responses.addResponse({ method: 'POST', url: '_action/sync', status: 200, response: { success: true } });

            await repositoryFactory.create('tax', null, { keepApiErrors: true }).syncDeleted([newId]);

            expect(Contena.Store.get('error').getErrorsForEntity('tax', oldId)).toMatchObject({
                code: 'FRAMEWORK__DELETE_RESTRICTED',
            });
        });
    });
});
