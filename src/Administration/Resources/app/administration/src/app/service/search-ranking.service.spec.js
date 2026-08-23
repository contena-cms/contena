/* eslint-disable ct-test-rules/test-file-max-lines-warning */

import SearchRankingService, {
    searchRankingPoint,
    KEY_USER_SEARCH_PREFERENCE,
} from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';
import searchRankingModules from './_mocks/searchRankingModules.json';

Contena.Service().register('userConfigService', () => {
    return {
        search: () => Promise.resolve({ data: {} }),
    };
});

Contena.Service().register('loginService', () => {
    return {
        addOnLoginListener: () => {},
    };
});

Contena.Service().register('systemConfigApiService', () => {
    return {
        getValues: jest.fn(),
        saveValues: jest.fn(),
    };
});

describe('app/service/search-ranking.service.js', () => {
    const entity = 'media';
    const defaultModule = {
        name: 'media-module',
        entity: entity,
        routes: {
            index: {
                path: 'index',
                component: 'ct-index',
            },
        },
    };
    const searchFieldsByEntityCases = [
        [
            'two fields with both have searchable is true',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                options: {
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
                'media.options.name': searchRankingPoint.LOW_SEARCH_RANKING,
            },
        ],
        [
            'two fields with the one have searchable is true and the other is false',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                options: {
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        ],
        [
            'two fields with both have searchable is false',
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                options: {
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
            {},
        ],
        [
            'empty search ranking fields',
            {},
            {},
        ],
        [
            'entity is unsearchable',
            {
                _searchable: false,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                options: {
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
            {},
        ],
    ];

    const buildingCriteriaScoreQueryCase = [
        [
            'term has just one word with word has more than 1 character',
            'user',
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            new Criteria(1, 25).addQuery(Criteria.contains('media.name', 'user'), searchRankingPoint.HIGH_SEARCH_RANKING),
        ],
        [
            'term has just one word with word has 1 character',
            'o',
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            new Criteria(1, 25).setTerm('o'),
        ],
        [
            'term has just two words with both have more than 1 character',
            'user folder',
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            new Criteria(1, 25)
                .addQuery(Criteria.contains('media.name', 'user'), searchRankingPoint.HIGH_SEARCH_RANKING)
                .addQuery(Criteria.contains('media.name', 'folder'), searchRankingPoint.HIGH_SEARCH_RANKING),
        ],
        [
            'term has just two words with one of them have less than 2 characters',
            'user c',
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            new Criteria(1, 25).addQuery(Criteria.contains('media.name', 'user'), searchRankingPoint.HIGH_SEARCH_RANKING),
        ],
        [
            'term has just two words with both have less than 2 characters',
            'o c',
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            new Criteria(1, 25).setTerm('o c'),
        ],
        [
            'term has just two words with the same',
            'same same',
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            new Criteria(1, 25).addQuery(Criteria.contains('media.name', 'same'), searchRankingPoint.HIGH_SEARCH_RANKING),
        ],
        [
            'term is undefined',
            undefined,
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            new Criteria(1, 25).setTerm(undefined),
        ],
        [
            'term has only spaces',
            '       ',
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            new Criteria(1, 25).setTerm('       '),
        ],
    ];

    beforeEach(() => {
        Contena.Context.app.config.settings = {
            ...(Contena.Context.app.config.settings ?? {}),
            minSearchTermLength: 2,
        };

        clearModules();
    });

    const userConfigSearchPreferenceCase = [
        [
            'Overwrite the default fields from searchable to unsearchable',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {},
        ],
        [
            'Overwrite the default fields from unsearchable to searchable',
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        ],
        [
            'Overwrite the default score',
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
            {
                'media.name': searchRankingPoint.LOW_SEARCH_RANKING,
            },
        ],
        [
            'Return empty when the module has default search configuration is empty',
            {},
            {
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
            {},
        ],
    ];

    function clearModules() {
        Contena.Module.getModuleRegistry().clear();
    }

    function createModules(modules) {
        modules.forEach((module) => {
            Contena.Module.register(module.name, module);
        });
    }

    function addDataToRegisterUserConfigService(userConfigSearchs) {
        const data = {
            [KEY_USER_SEARCH_PREFERENCE]: userConfigSearchs,
        };

        Contena.Service('userConfigService').search = () => Promise.resolve({ data });
    }

    it('Should get default user search preferences', async () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const actual = await service.getUserSearchPreference();
        const expected = {
            media: {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            tag: {
                'tag.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
            user: {},
            plugin: {},
        };

        expect(actual).toEqual(expected);
    });

    it.each(searchFieldsByEntityCases)(
        'Should get search ranking fields of the entity with %s',
        async (testName, searchFields, expected) => {
            const module = {
                ...defaultModule,
                defaultSearchConfiguration: {
                    _searchable: true,
                    ...searchFields,
                },
            };
            createModules([module]);
            const service = new SearchRankingService();

            const actual = await service.getSearchFieldsByEntity('media');
            expect(actual).toEqual(expected);
        },
    );

    it('Should return empty query when building global search query score with term less than 2 characters', async () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const userSearchPreference = await service.getUserSearchPreference();
        const actual = service.buildGlobalSearchQueries(userSearchPreference, 'd');
        const expected = {};

        expect(actual).toEqual(expected);
    });

    it('Should building global search query score with term more than 2 characters', async () => {
        createModules(searchRankingModules);
        const service = new SearchRankingService();

        const userSearchPreference = await service.getUserSearchPreference();
        const actual = service.buildGlobalSearchQueries(userSearchPreference, 'user');
        const expected = {
            media: {
                page: 1,
                limit: 25,
                query: [
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING,
                        query: {
                            type: 'contains',
                            field: 'media.name',
                            value: 'user',
                        },
                    },
                ],
                'total-count-mode': 1,
            },
            tag: {
                page: 1,
                limit: 25,
                query: [
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING,
                        query: {
                            type: 'contains',
                            field: 'tag.name',
                            value: 'user',
                        },
                    },
                ],
                'total-count-mode': 1,
            },
        };

        expect(actual).toEqual(expected);
    });

    it.each(buildingCriteriaScoreQueryCase)(
        'Should building search query for entity when %',
        (testName, term, queryScores, newCriteria) => {
            const service = new SearchRankingService();

            const criteria = service.buildSearchQueriesForEntity(queryScores, term, new Criteria(1, 25).setTerm(term));
            expect(criteria.parse()).toEqual(newCriteria.parse());
        },
    );

    it('Should cache the result when get search fields by entity', async () => {
        const service = new SearchRankingService();

        // Create module with name._searchable = true
        let module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        const firstResult = await service.getSearchFieldsByEntity('media');

        // Create again module with name._searchable = false
        clearModules();
        module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);

        const secondResult = await service.getSearchFieldsByEntity('media');

        expect(firstResult).toEqual(secondResult);
    });

    it('Should cache the result when get global search fields', async () => {
        const service = new SearchRankingService();

        // Create module with name._searchable = true
        let module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        const firstResult = await service.getUserSearchPreference();

        // Create again module with name._searchable = false
        clearModules();
        module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);

        const secondResult = await service.getUserSearchPreference();

        expect(firstResult).toEqual(secondResult);
    });

    it.each(userConfigSearchPreferenceCase)(
        'Should %s when search ranking fields of the entity along with getting user config',
        async (testName, defaultSearchFields, userConfigSearchFields, expected) => {
            const module = {
                ...defaultModule,
                defaultSearchConfiguration: {
                    _searchable: true,
                    ...defaultSearchFields,
                },
            };

            createModules([module]);
            addDataToRegisterUserConfigService([
                {
                    media: {
                        _searchable: true,
                        ...userConfigSearchFields,
                    },
                },
            ]);
            const newService = new SearchRankingService();
            const actual = await newService.getSearchFieldsByEntity('media');

            expect(actual).toEqual(expected);
        },
    );

    it('Should add default search configuration of a new module to current user search preferences', async () => {
        const commonSearchConfigurations = {
            _searchable: true,
            name: {
                _searchable: true,
                _score: searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        };

        createModules(searchRankingModules);
        addDataToRegisterUserConfigService([
            {
                user: { ...commonSearchConfigurations },
            },
            {
                tag: { ...commonSearchConfigurations },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getUserSearchPreference();

        expect(actual).toEqual({
            media: { 'media.name': searchRankingPoint.HIGH_SEARCH_RANKING },
            user: { 'user.name': searchRankingPoint.HIGH_SEARCH_RANKING },
            tag: {
                'tag.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        });
    });

    it('Should ignore modules without an entity when current user search preferences exist', async () => {
        const commonSearchConfigurations = {
            _searchable: true,
            name: {
                _searchable: true,
                _score: searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        };

        createModules([
            {
                ...defaultModule,
                defaultSearchConfiguration: commonSearchConfigurations,
            },
            {
                name: 'dashboard-module',
                routes: {
                    index: {
                        path: 'index',
                        component: 'ct-index',
                    },
                },
            },
        ]);
        addDataToRegisterUserConfigService([
            {
                media: { ...commonSearchConfigurations },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getUserSearchPreference();

        expect(actual).toEqual({
            media: { 'media.name': searchRankingPoint.HIGH_SEARCH_RANKING },
        });
    });

    it('Should remove stale leaf fields from current user search preferences', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                media: {
                    _searchable: true,
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                    },
                    returnNumber: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getUserSearchPreference();

        expect(actual).toEqual({
            media: {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        });
    });

    it('Should keep valid fields when persisted entity preferences miss the root searchable flag', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                media: {
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                    },
                    returnNumber: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getUserSearchPreference();

        expect(actual).toEqual({
            media: {
                'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        });
    });

    it('Should remove stale nested fields from current user search preferences', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                manufacturer: {
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                    },
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                media: {
                    _searchable: true,
                    manufacturer: {
                        name: {
                            _searchable: true,
                            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                        },
                        customExtension: {
                            returnNumber: {
                                _searchable: true,
                                _score: searchRankingPoint.LOW_SEARCH_RANKING,
                            },
                        },
                    },
                },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getUserSearchPreference();

        expect(actual).toEqual({
            media: {
                'media.manufacturer.name': searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        });
    });

    it('Should ignore stale persisted fields when getting search fields by entity', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                media: {
                    _searchable: true,
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                    },
                    returnNumber: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getSearchFieldsByEntity('media');

        expect(actual).toEqual({
            'media.name': searchRankingPoint.HIGH_SEARCH_RANKING,
        });
    });

    it('Should not build global search queries for stale persisted fields', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                media: {
                    _searchable: true,
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                    },
                    returnNumber: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
        ]);

        const service = new SearchRankingService();
        const userSearchPreference = await service.getUserSearchPreference();
        const actual = service.buildGlobalSearchQueries(userSearchPreference, 'user');

        expect(actual).toEqual({
            media: {
                page: 1,
                limit: 25,
                query: [
                    {
                        score: searchRankingPoint.HIGH_SEARCH_RANKING,
                        query: {
                            type: 'contains',
                            field: 'media.name',
                            value: 'user',
                        },
                    },
                ],
                'total-count-mode': 1,
            },
        });
    });

    it("Should remove an entity's search configurations from current user search preferences when entity's module does not have default search configurations", async () => {
        const commonSearchConfigurations = {
            _searchable: true,
            name: {
                _searchable: true,
                _score: searchRankingPoint.HIGH_SEARCH_RANKING,
            },
        };

        const module = {
            ...defaultModule,
            defaultSearchConfiguration: { ...commonSearchConfigurations },
        };
        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                user: { ...commonSearchConfigurations },
            },
            {
                tag: { ...commonSearchConfigurations },
            },
            {
                media: { ...commonSearchConfigurations },
            },
        ]);

        const service = new SearchRankingService();
        const actual = await service.getUserSearchPreference();

        expect(actual).toEqual({
            media: { 'media.name': searchRankingPoint.HIGH_SEARCH_RANKING },
        });
    });

    it('Should cache the result when getting user search configuration through the API', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                media: {
                    _searchable: true,
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
        ]);
        const newService = new SearchRankingService();
        let actual = await newService.getSearchFieldsByEntity('media');
        const expected = {
            'media.name': searchRankingPoint.LOW_SEARCH_RANKING,
        };

        expect(actual).toEqual(expected);

        // Set the response to return different response
        addDataToRegisterUserConfigService([
            {
                media: {
                    _searchable: false,
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
        ]);

        actual = await newService.getSearchFieldsByEntity('media');
        // expect to still be equal the old one
        expect(actual).toEqual(expected);
    });

    it('Should recall the API get user config after clear the cache', async () => {
        const module = {
            ...defaultModule,
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: false,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
        };

        createModules([module]);
        addDataToRegisterUserConfigService([
            {
                media: {
                    _searchable: true,
                    name: {
                        _searchable: true,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
        ]);
        const newService = new SearchRankingService();
        let actual = await newService.getSearchFieldsByEntity('media');

        expect(actual).toEqual({
            'media.name': searchRankingPoint.LOW_SEARCH_RANKING,
        });

        // Set the response to return different response
        addDataToRegisterUserConfigService([
            {
                media: {
                    _searchable: false,
                    name: {
                        _searchable: false,
                        _score: searchRankingPoint.LOW_SEARCH_RANKING,
                    },
                },
            },
        ]);
        newService.clearCacheUserSearchConfiguration();

        actual = await newService.getSearchFieldsByEntity('media');
        // expect to get different result
        expect(actual).toEqual({});
    });

    it('should validate search terms correctly', async () => {
        const service = new SearchRankingService();
        await service.getMinSearchTermLength();

        expect(service.isValidTerm('ab')).toBe(true);
        expect(service.isValidTerm('a')).toBe(false);
        expect(service.isValidTerm('')).toBe(false);
    });

    it('should get minSearchTermLength from app config', async () => {
        Contena.Context.app.config.settings.minSearchTermLength = 1;

        const service = new SearchRankingService();
        await service.getMinSearchTermLength();

        expect(service.isValidTerm('a')).toBe(true);
    });

    it('should not fetch minSearchTermLength from system config when service is created', () => {
        const originalService = Contena.Service('systemConfigApiService');
        originalService.getValues = jest.fn();

        new SearchRankingService();

        expect(originalService.getValues).not.toHaveBeenCalled();
    });

    it('should save minSearchTermLength to config', async () => {
        const originalService = Contena.Service('systemConfigApiService');
        originalService.saveValues = jest.fn();

        const service = new SearchRankingService();
        await service.saveMinSearchTermLength(3);

        expect(originalService.saveValues).toHaveBeenCalledWith({ 'core.search.minSearchTermLength': 3 });
    });
});
