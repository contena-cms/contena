import axios from 'axios';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

Contena.Application.view.deleteReactive = () => {};

describe('core/factory/http.factory.js', () => {
    let httpClient;
    let mock;

    beforeEach(async () => {
        /**
         * axios-client-mock does not work with request interceptors. So we enable our interceptor here
         */
        process.env.NODE_ENV = 'prod';
        httpClient = createHTTPClient();
        mock = new MockAdapter(httpClient);
        process.env.NODE_ENV = 'test';
    });

    it('should create a HTTP client with response interceptors', async () => {
        expect(Object.getPrototypeOf(httpClient).isPrototypeOf(axios)).toBeTruthy();
    });

    it('should add current vue route, as http header to trace', async () => {
        Contena.Application.view = {
            router: {
                currentRoute: {
                    value: {
                        name: 'ct-dashboard-index',
                    },
                },
            },
        };

        mock.onGet('/test').reply((request) => {
            expect(request.headers['contena-admin-active-route']).toBe('ct-dashboard-index');

            return [
                200,
                {},
            ];
        });

        await httpClient.get('/test');
    });

    it('should pass snippet params for delete restricted notifications', async () => {
        const notificationStore = Contena.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification').mockImplementation(() => {});
        const snippetSpy = jest.fn((key) => key);
        const originalView = Contena.Application.view;

        Contena.Application.view = {
            ...originalView,
            i18n: {
                ...(originalView?.i18n ?? {}),
                global: {
                    ...(originalView?.i18n?.global ?? {}),
                    t: snippetSpy,
                },
            },
        };

        mock.onDelete('/restricted-delete').reply(409, {
            errors: [
                {
                    code: 'FRAMEWORK__DELETE_RESTRICTED',
                    meta: {
                        parameters: {
                            entity: 'media',
                            usages: [
                                {
                                    count: [
                                        2,
                                        2,
                                    ],
                                    entityName: 'media_folder',
                                },
                            ],
                        },
                    },
                },
            ],
        });

        await httpClient.delete('/restricted-delete').catch(() => {});

        expect(notificationSpy).toHaveBeenCalledTimes(1);
        expect(snippetSpy).toHaveBeenCalledWith(
            'global.notification.messageDeleteFailed',
            { entityName: 'global.entities.media' },
            0,
        );

        Contena.Application.view = originalView;
        notificationSpy.mockRestore();
    });

    it('should have standard axios methods (get, post, etc.)', () => {
        expect(typeof httpClient.get).toBe('function');
        expect(typeof httpClient.post).toBe('function');
        expect(typeof httpClient.put).toBe('function');
        expect(typeof httpClient.patch).toBe('function');
        expect(typeof httpClient.delete).toBe('function');
        expect(typeof httpClient.request).toBe('function');
    });

    it('should use Axios v1', async () => {
        mock.onGet('/test').reply(200, { version: 'v1' });

        const response = await httpClient.get('/test');

        expect(response.data).toEqual({ version: 'v1' });
        expect(mock.history.get).toHaveLength(1);
    });

    it('should expose the Axios CancelToken API', () => {
        expect(httpClient.CancelToken).toBeDefined();
        expect(typeof httpClient.CancelToken.source).toBe('function');
    });

    describe('Cache Interceptor', () => {
        beforeEach(() => {
            jest.useFakeTimers();
            jest.spyOn(global.console, 'warn').mockImplementation();
        });

        afterEach(() => {
            jest.useRealTimers();
            jest.restoreAllMocks();
        });

        it('should cache identical requests', async () => {
            // Enable cache interceptor by setting NODE_ENV to prod
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            clientMock.onGet('/search/media').reply(200, { data: 'media' });

            // First request
            await client.get('/search/media');
            expect(clientMock.history.get).toHaveLength(1);

            // Second identical request within cache timeout
            jest.advanceTimersByTime(1000);
            await client.get('/search/media');

            // Should still be only 1 actual request due to caching
            expect(clientMock.history.get).toHaveLength(1);
            expect(console.warn).toHaveBeenCalledWith(
                expect.anything(),
                expect.stringContaining('Duplicated requests'),
                expect.anything(),
                expect.anything(),
            );
        });

        it('should not cache requests after timeout expires', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            clientMock.onGet('/search/media').reply(200, { data: 'media' });

            // First request
            await client.get('/search/media');
            expect(clientMock.history.get).toHaveLength(1);

            // Wait for cache to expire (1500ms timeout)
            jest.advanceTimersByTime(2000);

            // Second request after cache timeout
            await client.get('/search/media');

            // Should be 2 actual requests since cache expired
            expect(clientMock.history.get).toHaveLength(2);
            expect(console.warn).not.toHaveBeenCalled();
        });

        it('should flush cache on DELETE requests', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            clientMock.onGet('/search/media').reply(200, { data: 'media' });
            clientMock.onDelete('/media/123').reply(204);

            // First GET request
            await client.get('/search/media');
            expect(clientMock.history.get).toHaveLength(1);

            // DELETE request should flush cache
            await client.delete('/media/123');

            // Second GET request should not use cache (cache was flushed)
            await client.get('/search/media');
            expect(clientMock.history.get).toHaveLength(2);
        });

        it('should flush cache on PATCH requests', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            clientMock.onGet('/search/media').reply(200, { data: 'media' });
            clientMock.onPatch('/media/123').reply(200, { data: 'updated' });

            // First GET request
            await client.get('/search/media');
            expect(clientMock.history.get).toHaveLength(1);

            // PATCH request should flush cache
            await client.patch('/media/123', { name: 'Updated' });

            // Second GET request should not use cache (cache was flushed)
            await client.get('/search/media');
            expect(clientMock.history.get).toHaveLength(2);
        });

        it('should only cache allowed URLs', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            // URL not in allow list
            clientMock.onGet('/some/random/endpoint').reply(200, { data: 'test' });

            // First request
            await client.get('/some/random/endpoint');
            expect(clientMock.history.get).toHaveLength(1);

            // Second identical request
            jest.advanceTimersByTime(1000);
            await client.get('/some/random/endpoint');

            // Should be 2 requests since URL is not in allow list
            expect(clientMock.history.get).toHaveLength(2);
            expect(console.warn).not.toHaveBeenCalled();
        });

        it('should cache config endpoints indefinitely', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            // Use _info/me which is in the allow list
            clientMock.onGet('/_info/me').reply(200, { data: 'config' });

            // First request
            await client.get('/_info/me');
            expect(clientMock.history.get).toHaveLength(1);

            // Wait longer than normal cache timeout (1500ms)
            jest.advanceTimersByTime(5000);

            // Second request should still use cache (config endpoints cached indefinitely)
            await client.get('/_info/me');
            expect(clientMock.history.get).toHaveLength(1);
            expect(console.warn).toHaveBeenCalled();
        });
    });

    describe('refreshTokenInterceptor', () => {
        let loginService;
        let originalContenaService;

        beforeEach(() => {
            originalContenaService = Contena.Service;

            loginService = {
                refreshToken: jest.fn().mockResolvedValue('new-token'),
                subscribeToTokenRefresh: jest.fn((successCb) => {
                    successCb('new-token');
                }),
                logout: jest.fn(),
            };

            Contena.Service = jest.fn(() => loginService);
        });

        afterEach(() => {
            Contena.Service = originalContenaService;
        });

        it('should not retry a 401 request more than once after token refresh', async () => {
            mock.onGet('/api/some-endpoint').reply(401, {});

            const getError = async () => {
                try {
                    await httpClient.get('/api/some-endpoint');
                    throw new Error('Expected error to be thrown');
                } catch (error) {
                    return error;
                }
            };

            const error = await getError();
            expect(error.response.status).toBe(401);
            expect(mock.history.get).toHaveLength(2);
            expect(loginService.refreshToken).toHaveBeenCalledTimes(1);
            expect(loginService.subscribeToTokenRefresh).toHaveBeenCalledTimes(1);
        });
    });
});
