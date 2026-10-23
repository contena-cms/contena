import createHttpClient from 'src/core/factory/http.factory';
import createLoginService from 'src/core/service/login.service';
import StoreApiService from 'src/core/service/api/store.api.service';
import ContenaExtensionService from 'src/module/sw-extension/service/contena-extension.service';
import ExtensionStoreActionService from 'src/module/sw-extension/service/extension-store-action.service';
import AppModulesService from 'src/core/service/api/app-modules.service';
import 'src/module/sw-extension/service';
import initState from 'src/module/sw-extension/store';
import appModulesFixtures from '../../../app/service/_mocks/testApps.json';

jest.mock('src/module/sw-extension/service/extension-store-action.service');
jest.mock('src/core/service/api/app-modules.service');

const httpClient = createHttpClient(Contena.Context.api);
Contena.Application.getContainer('init').httpClient = httpClient;
Contena.Service().register('loginService', () => {
    return createLoginService(httpClient, Contena.Context.api);
});

Contena.Service().register('storeService', () => {
    return new StoreApiService(httpClient, Contena.Service('loginService'));
});

Contena.Service().register('contenaDiscountCampaignService', () => {
    return { isDiscountCampaignActive: jest.fn(() => true) };
});

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/service/contena-extension.service', () => {
    let contenaExtensionService;

    beforeAll(() => {
        contenaExtensionService = Contena.Service('contenaExtensionService');

        initState(Contena);

        Contena.Store.get('extensionEntryRoutes').routes = {
            ExamplePlugin: {
                route: 'test.foo',
            },
        };

        if (Contena.Store.get('context')) {
            Contena.Store.unregister('context');
        }

        Contena.Store.register({
            id: 'context',
            state: () => ({
                app: {
                    config: {
                        settings: {
                            disableExtensionManagement: false,
                        },
                    },
                },
                api: {
                    assetPath: 'http://localhost:8000/bundles/administration/',
                    authToken: {
                        token: 'testToken',
                    },
                },
            }),
        });

        // Ensure Contena.Context.api.authToken is set for repository.data.ts
        Contena.Context.api.authToken = {
            access: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImI0MTdkYjQ1MzMwNTY1MGIyY2QxMWVhYTBmZjRjNWJmZTVjZWYxYTI3NzBjY2JmY2M3MGY2Y2FiZDIzYWQyYmZiMzc1NTZhNDFlNGE3M2M5In0.test',
            expiry: 1602840582,
            refresh: 'def50200_test',
        };

        // Also set up installationPath and other required api context values
        Contena.Context.api.installationPath = 'installationPath';
        Contena.Context.api.apiPath = '/api';
        Contena.Context.api.apiResourcePath = '/api/v3';
        Contena.Context.api.assetsPath = '';
        Contena.Context.api.languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
        Contena.Context.api.inheritance = false;
        Contena.Context.api.systemLanguageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
        Contena.Context.api.liveVersionId = '0fa91ce3e96a4bc2be4bd9ce752c3425';
    });

    describe('it delegates lifecycle methods', () => {
        const mockedExtensionStoreActionService = new ExtensionStoreActionService(
            httpClient,
            Contena.Service('loginService'),
        );
        mockedExtensionStoreActionService.getMyExtensions.mockImplementation(() => {
            return ['new extensions'];
        });

        const mockedModuleService = new AppModulesService(httpClient, Contena.Service('loginService'));
        mockedModuleService.fetchAppModules.mockImplementation(() => {
            return ['new app modules'];
        });

        const mockedContenaExtensionService = new ContenaExtensionService(
            mockedModuleService,
            mockedExtensionStoreActionService,
            Contena.Service('contenaDiscountCampaignService'),
            Contena.Service('storeService'),
        );

        function expectUpdateExtensionDataCalled() {
            expect(mockedExtensionStoreActionService.refresh).toHaveBeenCalledTimes(1);
            expect(mockedExtensionStoreActionService.getMyExtensions).toHaveBeenCalledTimes(1);

            expect(Contena.Store.get('contenaExtensions').myExtensions.data).toEqual(['new extensions']);
            expect(Contena.Store.get('contenaExtensions').myExtensions.loading).toBe(false);

            expectUpdateModulesCalled();
        }

        function expectUpdateModulesCalled() {
            expect(mockedModuleService.fetchAppModules).toHaveBeenCalledTimes(1);

            expect(Contena.Store.get('contenaApps').apps).toEqual(['new app modules']);
        }

        beforeEach(() => {
            Contena.Store.get('contenaExtensions').setMyExtensions([]);
            Contena.Store.get('contenaApps').apps = [];
        });

        it.each([
            ['installExtension', ['someExtension', 'app']],
            ['updateExtension', ['someExtension', 'app', true]],
            ['uninstallExtension', ['someExtension', 'app', true]],
            ['removeExtension', ['someExtension', 'app', true]],
        ])('delegates %s correctly', async (lifecycleMethod, parameters) => {
            await mockedContenaExtensionService[lifecycleMethod](...parameters);

            expect(mockedExtensionStoreActionService[lifecycleMethod]).toHaveBeenCalledTimes(1);
            expect(mockedExtensionStoreActionService[lifecycleMethod]).toHaveBeenCalledWith(...parameters);

            expectUpdateExtensionDataCalled();
        });

        it('delegates cancelLicense correctly', async () => {
            await mockedContenaExtensionService.cancelLicense(5);

            expect(mockedExtensionStoreActionService.cancelLicense).toHaveBeenCalledTimes(1);
            expect(mockedExtensionStoreActionService.cancelLicense).toHaveBeenCalledWith(5);
        });

        it.each([['activateExtension'], ['deactivateExtension']])('delegates %s correctly', async (lifecycleMethod) => {
            await mockedContenaExtensionService[lifecycleMethod]('someExtension', 'app');

            expect(mockedExtensionStoreActionService[lifecycleMethod]).toHaveBeenCalledTimes(1);
            expect(mockedExtensionStoreActionService[lifecycleMethod]).toHaveBeenCalledWith('someExtension', 'app');

            expectUpdateModulesCalled();
        });
    });

    describe('checkLogin', () => {
        let checkLoginSpy;

        beforeEach(() => {
            Contena.Store.get('contenaExtensions').admin_userInfo = true;
            const storeService = Contena.Service('storeService');
            checkLoginSpy = jest.spyOn(storeService, 'checkLogin');
        });

        afterEach(() => {
            if (checkLoginSpy) {
                checkLoginSpy.mockRestore();
            }
        });

        it.each([[{ admin_userInfo: { email: 'admin_user@contena.cn' } }], [{ admin_userInfo: null }]])(
            'sets login status depending on checkLogin response',
            async (loginResponse) => {
                checkLoginSpy.mockImplementationOnce(() => Promise.resolve(loginResponse));

                await contenaExtensionService.checkLogin();

                expect(Contena.Store.get('contenaExtensions').admin_userInfo).toStrictEqual(loginResponse.admin_userInfo);
            },
        );

        it('sets login status to false if checkLogin request fails', async () => {
            checkLoginSpy.mockImplementationOnce(() => {
                throw new Error('something went wrong');
            });

            await contenaExtensionService.checkLogin();

            expect(Contena.Store.get('contenaExtensions').loginStatus).toBe(false);
            expect(Contena.Store.get('contenaExtensions').admin_userInfo).toBeNull();
        });
    });

    describe('isVariantDiscounted', () => {
        it('returns true if price is discounted and campaign is active', async () => {
            const variant = {
                netPrice: 100,
                discountCampaign: {
                    discountedPrice: 80,
                },
            };

            expect(contenaExtensionService.isVariantDiscounted(variant)).toBe(true);
        });

        it('returns false if price is discounted but campaign is not active', async () => {
            const variant = {
                netPrice: 100,
                discountCampaign: {
                    discountedPrice: 80,
                },
            };

            Contena.Service('contenaDiscountCampaignService').isDiscountCampaignActive.mockImplementationOnce(() => false);

            expect(contenaExtensionService.isVariantDiscounted(variant)).toBe(false);
        });

        it('returns false if variant is falsy', async () => {
            expect(contenaExtensionService.isVariantDiscounted(null)).toBe(false);
        });

        it('returns false if variant has no discountCampaign', async () => {
            expect(contenaExtensionService.isVariantDiscounted({})).toBe(false);
        });

        it('returns false if discounted price is net price', async () => {
            expect(
                contenaExtensionService.isVariantDiscounted({
                    netPrice: 100,
                    discountCampaign: {
                        discountedPrice: 100,
                    },
                }),
            ).toBe(false);
        });
    });

    describe('orderVariantsByRecommendation', () => {
        it('orders variants by recommendation and discounting', async () => {
            const variants = [
                {
                    netPrice: 100,
                    discountCampaign: { netPrice: 100 },
                    type: 'rent',
                },
                {
                    netPrice: 100,
                    discountCampaign: { netPrice: 80 },
                    type: 'test',
                },
                {
                    netPrice: 100,
                    discountCampaign: { netPrice: 100 },
                    type: 'test',
                },
                {
                    netPrice: 100,
                    discountCampaign: { netPrice: 100 },
                    type: 'buy',
                },
                {
                    netPrice: 100,
                    discountCampaign: { netPrice: 10 },
                    type: 'rent',
                },
            ];

            contenaExtensionService
                .orderVariantsByRecommendation(variants)
                .forEach((current, currentIndex, orderedVariants) => {
                    const isCurrentDiscounted = contenaExtensionService.isVariantDiscounted(current);
                    const currentRecommendation = contenaExtensionService.mapVariantToRecommendation(current);

                    orderedVariants.forEach((comparator, comparatorIndex) => {
                        const isComparatorDiscounted = contenaExtensionService.isVariantDiscounted(comparator);
                        const comparatorRecommendation = contenaExtensionService.mapVariantToRecommendation(comparator);

                        if (isCurrentDiscounted !== !isComparatorDiscounted) {
                            // discounted index is always smaller than undiscounted
                            if (isCurrentDiscounted && !isComparatorDiscounted) {
                                // eslint-disable-next-line jest/no-conditional-expect
                                expect(currentIndex).toBeLessThan(comparatorIndex);
                            }

                            if (!isCurrentDiscounted && isComparatorDiscounted) {
                                // eslint-disable-next-line jest/no-conditional-expect
                                expect(currentIndex).toBeGreaterThan(comparatorIndex);
                            }
                        } else {
                            // variants are ordered by recommendation
                            if (currentRecommendation < comparatorRecommendation) {
                                // eslint-disable-next-line jest/no-conditional-expect
                                expect(currentIndex).toBeLessThan(comparatorIndex);
                            }

                            if (currentIndex > comparatorRecommendation) {
                                // eslint-disable-next-line jest/no-conditional-expect
                                expect(currentIndex).toBeGreaterThan(comparatorIndex);
                            }
                        }
                    });
                });
        });
    });

    describe('getPriceFromVariant', () => {
        it('returns discounted price if variant is discounted', async () => {
            expect(
                contenaExtensionService.getPriceFromVariant({
                    netPrice: 100,
                    discountCampaign: {
                        discountedPrice: 80,
                    },
                }),
            ).toBe(80);
        });

        it('returns net price if variant is not discounted', async () => {
            Contena.Service('contenaDiscountCampaignService').isDiscountCampaignActive.mockImplementationOnce(() => false);

            expect(
                contenaExtensionService.getPriceFromVariant({
                    netPrice: 100,
                    discountCampaign: {
                        discountedPrice: 80,
                    },
                }),
            ).toBe(100);
        });
    });

    describe('mapVariantToRecommendation', () => {
        it.each([
            ['free', 0],
            ['rent', 1],
            ['buy', 2],
            ['test', 3],
        ])('maps variant %s to position %d', (type, expectedRecommendation) => {
            expect(contenaExtensionService.mapVariantToRecommendation({ type })).toBe(expectedRecommendation);
        });
    });

    describe('getOpenLink', () => {
        beforeEach(() => {
            // Ensure authToken is set for repository calls
            Contena.Context.api.authToken = {
                access: 'test-token',
                expiry: 1602840582,
                refresh: 'test-refresh',
            };
        });

        it('returns always a open link for theme', async () => {
            const themeId = Contena.Utils.createId();

            const responses = global.repositoryFactoryMock.responses;
            responses.addResponse({
                method: 'Post',
                url: '/search-ids/theme',
                status: 200,
                response: {
                    data: [themeId],
                },
            });

            const openLink = await contenaExtensionService.getOpenLink({
                isTheme: true,
                type: contenaExtensionService.EXTENSION_TYPES.APP,
                name: 'SwagExampleApp',
            });

            expect(openLink).toEqual({
                name: 'sw.theme.manager.detail',
                params: { id: themeId },
            });
        });

        it('returns valid open link for app with main module', async () => {
            Contena.Store.get('contenaApps').apps = appModulesFixtures;

            expect(
                await contenaExtensionService.getOpenLink({
                    isTheme: false,
                    type: contenaExtensionService.EXTENSION_TYPES.APP,
                    name: 'testAppA',
                }),
            ).toEqual({
                name: 'sw.extension.module',
                params: {
                    appName: 'testAppA',
                },
            });
        });

        it('returns no open link for app without main module', async () => {
            Contena.Store.get('contenaApps').apps = appModulesFixtures;

            expect(
                await contenaExtensionService.getOpenLink({
                    isTheme: false,
                    type: contenaExtensionService.EXTENSION_TYPES.APP,
                    name: 'testAppB',
                }),
            ).toBeNull();
        });

        it('returns no open link if app can not be found', async () => {
            Contena.Store.get('contenaApps').apps = appModulesFixtures;

            expect(
                await contenaExtensionService.getOpenLink({
                    isTheme: false,
                    type: contenaExtensionService.EXTENSION_TYPES.APP,
                    name: 'ThisAppDoesNotExist',
                }),
            ).toBeNull();
        });

        it('returns no open link for plugins not registered', async () => {
            expect(
                await contenaExtensionService.getOpenLink({
                    isTheme: false,
                    type: contenaExtensionService.EXTENSION_TYPES.PLUGIN,
                    name: 'SwagNoModule',
                }),
            ).toBeNull();
        });

        it('returns route for plugins registered', async () => {
            expect(
                await contenaExtensionService.getOpenLink({
                    isTheme: false,
                    type: contenaExtensionService.EXTENSION_TYPES.PLUGIN,
                    name: 'ExamplePlugin',
                    active: true,
                }),
            ).toEqual({
                label: null,
                name: 'test.foo',
            });
        });
    });
});
