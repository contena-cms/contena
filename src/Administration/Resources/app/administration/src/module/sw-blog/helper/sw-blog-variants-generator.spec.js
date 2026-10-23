/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package inventory
 */

import VariantsGenerator from 'src/module/sw-blog/helper/sw-blogs-variants-generator';
import EntityFactory from 'src/core/data/entity-factory.data';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

/** fixtures */
import currencies from './_mocks/testCurriencies.json';
import blog from './_mocks/testProduct.json';

const entityFactory = new EntityFactory();

describe('/src/module/sw-blog/helper/sw-blogs-variants-generator.spec.js', () => {
    let variantsGenerator;

    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Contena.EntityDefinition.add(entityName, entityDefinition);
        });

        Contena.Service().register('syncService', () => {
            return {
                httpClient: {
                    get() {
                        return Promise.resolve({ data: [] });
                    },
                },
                getBasicHeaders() {
                    return {};
                },
                sync() {
                    return true;
                },
            };
        });
        Contena.Service().register('cacheApiService', () => {
            return {
                indexProducts() {},
            };
        });
        variantsGenerator = new VariantsGenerator();
    });

    function createProductEntity(originVariantRestrictions, variantRestrictions = originVariantRestrictions) {
        const blogEntity = entityFactory.create('blog', 'blog-123');

        blogEntity.getOrigin().variantRestrictions = originVariantRestrictions;
        blogEntity.getDraft().variantRestrictions = originVariantRestrictions;
        blogEntity.variantRestrictions = variantRestrictions;

        return blogEntity;
    }

    function createProductEntityWithListingConfig(originConfig, variantListingConfig = originConfig) {
        const blogEntity = entityFactory.create('blog', 'blog-123');

        blogEntity.getOrigin().variantListingConfig = originConfig;
        blogEntity.getDraft().variantListingConfig = originConfig;
        blogEntity.variantListingConfig = variantListingConfig;

        return blogEntity;
    }

    it('should not filter variants with positive or negative prices', async () => {
        const expectedCreateQueue = [
            {
                options: [
                    {
                        id: 'f8081c78bb7b4c72bdd8dda79520f315',
                    },
                ],
                parentId: '0cf18788d25546a3a8dc856329aff57b',
                price: [
                    {
                        currencyId: 'e7d006b51e2d4f9c80de6be68206aba7',
                        gross: 762.586,
                        linked: true,
                        net: 762.586,
                    },
                    {
                        currencyId: '4f0b5be0f0a842218e3a899c66c19691',
                        gross: 18534.306099999998,
                        linked: true,
                        net: 18534.306099999998,
                    },
                    {
                        currencyId: '492a0955a83241c8b859b1c6c371c269',
                        gross: 5178.6521999999995,
                        linked: true,
                        net: 5178.6521999999995,
                    },
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        gross: 703.26,
                        linked: true,
                        net: 703.26,
                    },
                    {
                        currencyId: '42eb2cf984014b8db46309d593e59e7f',
                        gross: 618.0898182,
                        linked: true,
                        net: 618.0898182,
                    },
                    {
                        currencyId: 'c5ec1aad7cb54973bde4207dbe739aa7',
                        gross: 68.63274,
                        linked: true,
                        net: 68.63274,
                    },
                    {
                        currencyId: 'be219239630f4732b9f14d9d54b355cd',
                        gross: 3001.8158,
                        linked: true,
                        net: 3001.8158,
                    },
                    {
                        currencyId: '97623a923ef24f5bafa0aa3343209b38',
                        gross: 7286.1626,
                        linked: true,
                        net: 7286.1626,
                    },
                    {
                        currencyId: '058a99a092604b61a29a41064bb7500d',
                        gross: 811.7034709999999,
                        linked: true,
                        net: 811.7034709999999,
                    },
                ],
                blogNumber: 'fdb84d0397414e03b2ed6f6821e3d945.1',
                stock: 0,
            },
            {
                options: [
                    {
                        id: '9eb83cc0627d43f2bba77b119ed847e9',
                    },
                ],
                parentId: '0cf18788d25546a3a8dc856329aff57b',
                price: [
                    {
                        currencyId: 'e7d006b51e2d4f9c80de6be68206aba7',
                        gross: 762.586,
                        linked: true,
                        net: 762.586,
                    },
                    {
                        currencyId: '4f0b5be0f0a842218e3a899c66c19691',
                        gross: 18534.306099999998,
                        linked: true,
                        net: 18534.306099999998,
                    },
                    {
                        currencyId: '492a0955a83241c8b859b1c6c371c269',
                        gross: 5178.6521999999995,
                        linked: true,
                        net: 5178.6521999999995,
                    },
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        gross: 683.26,
                        linked: true,
                        net: 683.26,
                    },
                    {
                        currencyId: '42eb2cf984014b8db46309d593e59e7f',
                        gross: 618.0898182,
                        linked: true,
                        net: 618.0898182,
                    },
                    {
                        currencyId: 'c5ec1aad7cb54973bde4207dbe739aa7',
                        gross: 68.63274,
                        linked: true,
                        net: 68.63274,
                    },
                    {
                        currencyId: 'be219239630f4732b9f14d9d54b355cd',
                        gross: 3001.8158,
                        linked: true,
                        net: 3001.8158,
                    },
                    {
                        currencyId: '97623a923ef24f5bafa0aa3343209b38',
                        gross: 7286.1626,
                        linked: true,
                        net: 7286.1626,
                    },
                    {
                        currencyId: '058a99a092604b61a29a41064bb7500d',
                        gross: 811.7034709999999,
                        linked: true,
                        net: 811.7034709999999,
                    },
                ],
                blogNumber: 'fdb84d0397414e03b2ed6f6821e3d945.2',
                stock: 0,
            },
        ];

        function getCreateQueue() {
            return new Promise((resolve) => {
                const queueEventHandler = (data) => {
                    resolve(data.createQueue);
                };

                variantsGenerator.on('queues', queueEventHandler);

                variantsGenerator.generateVariants(currencies, blog);
            });
        }

        expect(await getCreateQueue()).toEqual(expectedCreateQueue);
    });

    it('should emit `queues` event when calling generateVariants', async () => {
        function getQueueEventHandler() {
            return new Promise((resolve) => {
                const queueEventHandler = (data) => {
                    resolve(data);
                };

                // eslint-disable-next-line listeners/no-missing-remove-event-listener
                variantsGenerator.on('queues', queueEventHandler);

                variantsGenerator.generateVariants(currencies, blog);
            });
        }

        const data = await getQueueEventHandler();
        expect(data.deleteQueue).toHaveLength(0);
        expect(data.createQueue).toHaveLength(2);
    });

    it('should filter variants correctly', async () => {
        const newVariations = [['e10fed21a07149958427cb5339ee4c31']];

        const variationOnServer = {
            '455ff20cec764a2aab42d2282d08456c': {
                options: ['d6e90b99fe4842d487b53b59e50491a4'],
                blogNumber: 'SW10000.1',
                blogStates: '["is-physical"]',
                blogType: '["physical"]',
            },
            a6ebe32c706b4a16a69041b31df5d7fb: {
                options: ['e10fed21a07149958427cb5339ee4c31'],
                blogNumber: 'SW10000.2',
                blogStates: '["is-download"]',
                blogType: '["digital"]',
            },
        };

        variantsGenerator.blog = blog;
        const variants = await variantsGenerator.filterVariations(newVariations, variationOnServer, currencies);

        expect(variants).toEqual({
            createQueue: [],
            deleteQueue: ['455ff20cec764a2aab42d2282d08456c'],
        });
    });

    it('should delete existing variants matching newly added restrictions', async () => {
        const newVariations = [['d6e90b99fe4842d487b53b59e50491a4'], ['e10fed21a07149958427cb5339ee4c31']];

        const variationOnServer = {
            '455ff20cec764a2aab42d2282d08456c': {
                options: ['d6e90b99fe4842d487b53b59e50491a4'],
                blogNumber: 'SW10000.1',
                blogStates: '["is-physical"]',
                blogType: '["physical"]',
            },
            a6ebe32c706b4a16a69041b31df5d7fb: {
                options: ['e10fed21a07149958427cb5339ee4c31'],
                blogNumber: 'SW10000.2',
                blogStates: '["is-download"]',
                blogType: '["digital"]',
            },
        };

        variantsGenerator.blog = {
            ...blog,
            variantRestrictions: [
                {
                    id: 'restriction1',
                    values: [
                        {
                            id: 'value1',
                            group: 'group1',
                            options: ['e10fed21a07149958427cb5339ee4c31'],
                        },
                    ],
                },
            ],
        };

        const variants = await variantsGenerator.filterVariations(newVariations, variationOnServer, currencies);

        expect(variants).toEqual({
            createQueue: [],
            deleteQueue: ['a6ebe32c706b4a16a69041b31df5d7fb'],
        });
    });

    describe('filterRestrictions', () => {
        const mockCreateQueue = [
            {
                parentId: 'parent1',
                options: [{ id: 'option1' }, { id: 'option2' }],
            },
            {
                parentId: 'parent1',
                options: [{ id: 'option1' }, { id: 'option3' }],
            },
            {
                parentId: 'parent1',
                options: [{ id: 'option2' }, { id: 'option3' }],
            },
        ];

        it('should return createQueue when variantRestrictions is not an array', () => {
            variantsGenerator.blog = { variantRestrictions: null };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);

            variantsGenerator.blog = { variantRestrictions: undefined };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);

            variantsGenerator.blog = { variantRestrictions: {} };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);

            variantsGenerator.blog = { variantRestrictions: 'invalid' };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);
        });

        it('should return createQueue when variantRestrictions is empty array', () => {
            variantsGenerator.blog = { variantRestrictions: [] };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);
        });

        it('should return createQueue when all restrictions have empty options', () => {
            variantsGenerator.blog = {
                variantRestrictions: [
                    {
                        id: 'restriction1',
                        values: [{ id: 'value1', group: 'group1', options: [] }],
                    },
                    {
                        id: 'restriction2',
                        values: [],
                    },
                ],
            };
            expect(variantsGenerator.filterRestrictions(mockCreateQueue)).toEqual(mockCreateQueue);
        });

        it('should filter out malformed restrictions and process valid ones', () => {
            variantsGenerator.blog = {
                variantRestrictions: [
                    // Invalid: empty options
                    {
                        id: 'invalid1',
                        values: [{ id: 'value1', group: 'group1', options: [] }],
                    },
                    // Invalid: no values
                    {
                        id: 'invalid2',
                        values: [],
                    },
                    null,
                    {
                        id: 'valid1',
                        values: [
                            { id: 'value1', group: 'group1', options: ['option1'] },
                            { id: 'value2', group: 'group2', options: ['option2'] },
                        ],
                    },
                ],
            };

            const result = variantsGenerator.filterRestrictions(mockCreateQueue);

            expect(result).toEqual([
                {
                    parentId: 'parent1',
                    options: [{ id: 'option1' }, { id: 'option3' }],
                },
                {
                    parentId: 'parent1',
                    options: [{ id: 'option2' }, { id: 'option3' }],
                },
            ]);
        });

        it('should filter variants matching valid restrictions', () => {
            variantsGenerator.blog = {
                variantRestrictions: [
                    {
                        id: 'restriction1',
                        values: [
                            { id: 'value1', group: 'group1', options: ['option1'] },
                            { id: 'value2', group: 'group2', options: ['option3'] },
                        ],
                    },
                ],
            };

            const result = variantsGenerator.filterRestrictions(mockCreateQueue);

            expect(result).toEqual([
                {
                    parentId: 'parent1',
                    options: [{ id: 'option1' }, { id: 'option2' }],
                },
                {
                    parentId: 'parent1',
                    options: [{ id: 'option2' }, { id: 'option3' }],
                },
            ]);
        });
    });

    describe('saveVariantRestrictions', () => {
        it('should resolve immediately when blog is missing', async () => {
            variantsGenerator.blog = null;
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync');

            const result = await variantsGenerator.saveVariantRestrictions([]);

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should resolve immediately when variant restrictions are undefined', async () => {
            variantsGenerator.blog = {
                id: 'blog-123',
            };
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync');

            const result = await variantsGenerator.saveVariantRestrictions();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should upsert blog variant restrictions', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
            const variantRestrictions = [
                {
                    id: 'restriction-1',
                    values: [
                        {
                            id: 'value-1',
                            group: 'group-1',
                            options: ['option-1'],
                        },
                    ],
                },
            ];

            variantsGenerator.blog = createProductEntity(null, variantRestrictions);

            await variantsGenerator.saveVariantRestrictions();

            expect(syncSpy).toHaveBeenCalledWith(
                [
                    {
                        entity: 'blog',
                        action: 'upsert',
                        payload: [
                            {
                                id: 'blog-123',
                                variantRestrictions,
                            },
                        ],
                    },
                ],
                {},
                { 'single-operation': 1 },
            );

            const calledRestrictions = syncSpy.mock.calls[0][0][0].payload[0].variantRestrictions;
            expect(calledRestrictions).not.toBe(variantRestrictions);

            syncSpy.mockRestore();
        });

        it('should resolve immediately when blog variant restrictions did not change', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
            const variantRestrictions = [
                {
                    id: 'restriction-1',
                    values: [
                        {
                            id: 'value-1',
                            group: 'group-1',
                            options: ['option-1'],
                        },
                    ],
                },
            ];

            variantsGenerator.blog = createProductEntity(variantRestrictions);

            const result = await variantsGenerator.saveVariantRestrictions();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should persist empty variant restrictions to clear previous exclusions', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
            const variantRestrictions = [
                {
                    id: 'restriction-1',
                    values: [
                        {
                            id: 'value-1',
                            group: 'group-1',
                            options: ['option-1'],
                        },
                    ],
                },
            ];

            variantsGenerator.blog = createProductEntity(variantRestrictions, []);

            await variantsGenerator.saveVariantRestrictions();

            expect(syncSpy.mock.calls[0][0][0].payload).toEqual([
                {
                    id: 'blog-123',
                    variantRestrictions: [],
                },
            ]);

            syncSpy.mockRestore();
        });
    });

    describe('saveConfiguratorSettings', () => {
        it('should resolve immediately when configuratorSettings is null', async () => {
            const result = await variantsGenerator.saveConfiguratorSettings(null);
            expect(result).toBeUndefined();
        });

        it('should resolve immediately when configuratorSettings is empty', async () => {
            const result = await variantsGenerator.saveConfiguratorSettings([]);
            expect(result).toBeUndefined();
        });

        it('should call syncService.sync with upsert action and cloned settings', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = {
                id: 'blog-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'option-1',
                    mediaId: 'media-1',
                    position: 1,
                    price: { gross: 10, net: 8.4 },
                    customFields: { foo: 'bar' },
                    isNew: () => false,
                },
                {
                    id: 'setting-2',
                    optionId: 'option-2',
                    isNew: () => false,
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings);

            expect(syncSpy).toHaveBeenCalledWith(
                [
                    {
                        entity: 'blog_configurator_setting',
                        action: 'upsert',
                        payload: expect.arrayContaining([
                            expect.objectContaining({
                                id: 'setting-1',
                                blogId: 'blog-123',
                                optionId: 'option-1',
                            }),
                            expect.objectContaining({
                                id: 'setting-2',
                                blogId: 'blog-123',
                                optionId: 'option-2',
                            }),
                        ]),
                    },
                ],
                {},
                { 'single-operation': 1 },
            );

            syncSpy.mockRestore();
        });

        it('should always set blogId to current blog', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = {
                id: 'current-blog-id',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'option-1',
                    blogId: 'old-blog-id', // Should be overwritten
                    isNew: () => false,
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload[0];
            expect(calledPayload.blogId).toBe('current-blog-id');

            syncSpy.mockRestore();
        });

        it('should clone settings to avoid mutating original data', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = {
                id: 'blog-123',
            };

            const originalSettings = [
                {
                    id: 'setting-1',
                    optionId: 'option-1',
                    blogId: 'original-blog-id',
                    isNew: () => false,
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(originalSettings);

            expect(originalSettings[0].blogId).toBe('original-blog-id');

            syncSpy.mockRestore();
        });

        it('should always include existing settings (isNew returns false) regardless of createQueue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = {
                id: 'blog-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'option-1',
                    isNew: () => false,
                },
                {
                    id: 'setting-2',
                    optionId: 'option-2',
                    isNew: () => false,
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings, []);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload;

            expect(calledPayload).toHaveLength(2);
            expect(calledPayload[0].optionId).toBe('option-1');
            expect(calledPayload[1].optionId).toBe('option-2');

            syncSpy.mockRestore();
        });

        it('should filter out falsely new settings not in createQueue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = {
                id: 'blog-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'existing-option',
                    isNew: () => true,
                },
                {
                    id: 'setting-2',
                    optionId: 'real-existing-option',
                    isNew: () => false,
                },
            ];

            const createQueue = [];

            await variantsGenerator.saveConfiguratorSettings(mockSettings, createQueue);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload;

            expect(calledPayload).toHaveLength(1);
            expect(calledPayload[0].optionId).toBe('real-existing-option');

            syncSpy.mockRestore();
        });

        it('should include truly new settings that are in createQueue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = {
                id: 'blog-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'new-option-1',
                    isNew: () => true,
                },
                {
                    id: 'setting-2',
                    optionId: 'new-option-2',
                    isNew: () => true,
                },
                {
                    id: 'setting-3',
                    optionId: 'existing-option',
                    isNew: () => false,
                },
            ];

            const createQueue = [
                {
                    options: [{ id: 'new-option-1' }, { id: 'new-option-2' }],
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings, createQueue);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload;

            expect(calledPayload).toHaveLength(3);

            syncSpy.mockRestore();
        });

        it('should include existing and new-in-queue settings and filter out new-not-in-queue', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = {
                id: 'blog-123',
            };

            const mockSettings = [
                {
                    id: 'setting-existing',
                    optionId: 'option-existing',
                    isNew: () => false,
                },
                {
                    id: 'setting-new-in-queue',
                    optionId: 'option-new-in-queue',
                    isNew: () => true,
                },
                {
                    id: 'setting-new-not-in-queue',
                    optionId: 'option-new-not-in-queue',
                    isNew: () => true,
                },
            ];

            const createQueue = [
                {
                    options: [{ id: 'option-new-in-queue' }],
                },
            ];

            await variantsGenerator.saveConfiguratorSettings(mockSettings, createQueue);

            const calledPayload = syncSpy.mock.calls[0][0][0].payload;

            expect(calledPayload).toHaveLength(2);
            expect(calledPayload.map((s) => s.optionId).sort()).toEqual(['option-existing', 'option-new-in-queue']);

            syncSpy.mockRestore();
        });

        it('should resolve immediately when all settings are filtered out', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = {
                id: 'blog-123',
            };

            const mockSettings = [
                {
                    id: 'setting-1',
                    optionId: 'existing-option',
                    isNew: () => true,
                },
            ];

            const createQueue = [];

            const result = await variantsGenerator.saveConfiguratorSettings(mockSettings, createQueue);

            expect(syncSpy).not.toHaveBeenCalled();
            expect(result).toBeUndefined();

            syncSpy.mockRestore();
        });
    });
    describe('saveVariantListingConfig', () => {
        it('should apply the main blog default for completely new variant blogs', async () => {
            const newProduct = JSON.parse(JSON.stringify(blog));
            newProduct.variantListingConfig = null;

            await new Promise((resolve) => {
                variantsGenerator.once('queues', resolve);
                variantsGenerator.generateVariants(currencies, newProduct);
            });

            expect(newProduct.variantListingConfig).toEqual({ displayParent: true });
        });

        it('should apply the main blog default when nothing was saved for a blog created in the administration', async () => {
            const newProduct = JSON.parse(JSON.stringify(blog));
            newProduct.variantListingConfig = { displayParent: null, mainVariantId: null, configuratorGroupConfig: null };

            await new Promise((resolve) => {
                variantsGenerator.once('queues', resolve);
                variantsGenerator.generateVariants(currencies, newProduct);
            });

            expect(newProduct.variantListingConfig).toEqual({ displayParent: true });
        });

        it.each([
            ['a variant', { displayParent: false, mainVariantId: null, configuratorGroupConfig: null }],
            ['a main variant', { displayParent: null, mainVariantId: 'variant-id', configuratorGroupConfig: null }],
            ['expanded variants', { displayParent: null, mainVariantId: null, configuratorGroupConfig: [] }],
        ])('should keep the saved presentation of %s when generating variants', async (_, variantListingConfig) => {
            const existingProduct = JSON.parse(JSON.stringify(blog));
            existingProduct.variantListingConfig = { ...variantListingConfig };

            await new Promise((resolve) => {
                variantsGenerator.once('queues', resolve);
                variantsGenerator.generateVariants(currencies, existingProduct);
            });

            expect(existingProduct.variantListingConfig).toEqual(variantListingConfig);
        });

        it('should resolve immediately when blog is missing', async () => {
            variantsGenerator.blog = null;
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync');

            const result = await variantsGenerator.saveVariantListingConfig();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should resolve immediately when the variant listing config is not set', async () => {
            variantsGenerator.blog = createProductEntityWithListingConfig(null);
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync');

            const result = await variantsGenerator.saveVariantListingConfig();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });

        it('should upsert the variant listing config default applied during the generation', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});
            const variantListingConfig = { displayParent: true };

            variantsGenerator.blog = createProductEntityWithListingConfig(null, variantListingConfig);

            await variantsGenerator.saveVariantListingConfig();

            expect(syncSpy).toHaveBeenCalledWith(
                [
                    {
                        entity: 'blog',
                        action: 'upsert',
                        payload: [
                            {
                                id: 'blog-123',
                                variantListingConfig,
                            },
                        ],
                    },
                ],
                {},
                { 'single-operation': 1 },
            );

            const calledConfig = syncSpy.mock.calls[0][0][0].payload[0].variantListingConfig;
            expect(calledConfig).not.toBe(variantListingConfig);

            syncSpy.mockRestore();
        });

        it('should resolve immediately when the variant listing config did not change', async () => {
            const syncSpy = jest.spyOn(variantsGenerator.syncService, 'sync').mockResolvedValue({});

            variantsGenerator.blog = createProductEntityWithListingConfig({
                displayParent: null,
                mainVariantId: null,
                configuratorGroupConfig: [
                    {
                        id: 'group-1',
                        representation: 'box',
                        expressionForListings: true,
                    },
                ],
            });

            const result = await variantsGenerator.saveVariantListingConfig();

            expect(result).toBeUndefined();
            expect(syncSpy).not.toHaveBeenCalled();

            syncSpy.mockRestore();
        });
    });
});
