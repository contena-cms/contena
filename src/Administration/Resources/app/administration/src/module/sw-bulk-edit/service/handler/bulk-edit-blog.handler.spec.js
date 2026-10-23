/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package inventory
 */
import BulkEditApiFactory from 'src/module/sw-bulk-edit/service/bulk-edit.api.factory';
import BulkEditProductHandler from 'src/module/sw-bulk-edit/service/handler/bulk-edit-blog.handler';

const EntityDefinitionFactory = require('src/core/factory/entity-definition.factory').default;

const highAssociationCount = 750;

function getBulkEditApiFactory() {
    return new BulkEditApiFactory();
}

function getBulkEditProductHandler() {
    const factory = getBulkEditApiFactory();

    const handler = factory.getHandler('blog');

    handler.syncService = {
        sync: () => {
            return true;
        },
    };

    return handler;
}

function paginate(data, criteria) {
    return data.slice((criteria.page - 1) * criteria.limit, criteria.page * criteria.limit);
}

describe('module/sw-bulk-edit/service/handler/bulk-edit-blog.handler', () => {
    it('is registered correctly', async () => {
        const factory = getBulkEditApiFactory();

        const handler = factory.getHandler('blog');

        expect(handler).toBeInstanceOf(BulkEditProductHandler);
        expect(handler.name).toBe('bulkEditProductHandler');
    });

    it('should call buildBulkSyncPayload when using bulkEdit', async () => {
        const handler = getBulkEditProductHandler();

        const bulkEditProductHandler = jest.spyOn(handler, 'buildBulkSyncPayload').mockImplementation(() =>
            Promise.resolve({
                upsert: {
                    entity: 'order',
                },
            }),
        );

        const result = await handler.bulkEdit(['abc', 'xyz'], []);

        expect(bulkEditProductHandler).toHaveBeenCalledTimes(1);
        expect(bulkEditProductHandler).toHaveBeenCalledWith([]);
        expect(handler.entityName).toBe('blog');
        expect(handler.entityIds).toEqual(['abc', 'xyz']);
        expect(result).toBe(true);
    });

    it('should call syncService sync when using bulkEditProductHandler', async () => {
        const handler = getBulkEditProductHandler();
        const payload = {
            blog: { operation: 'upsert', entity: 'blog', payload: [] },
        };

        const buildBulkSyncPayloadMethod = jest
            .spyOn(handler, 'buildBulkSyncPayload')
            .mockImplementation(() => Promise.resolve(payload));
        const syncMethod = jest.spyOn(handler.syncService, 'sync').mockImplementation(() => Promise.resolve(true));

        const changes = [{ type: 'overwrite', field: 'description', value: 'test' }];

        const result = await handler.bulkEdit([], changes);

        expect(buildBulkSyncPayloadMethod).toHaveBeenCalledTimes(1);
        expect(buildBulkSyncPayloadMethod).toHaveBeenCalledWith(changes);

        expect(syncMethod).toHaveBeenCalledTimes(1);
        expect(syncMethod).toHaveBeenCalledWith(
            payload,
            {},
            {
                'single-operation': 1,
                'sw-language-id': Contena.Context.api.languageId,
            },
        );
        expect(result).toBe(true);
    });

    it('should preserve base price fields (gross/net/linked) when only listPrice is changed', async () => {
        const currencyId = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
        const handler = getBulkEditProductHandler();
        const originalBasePrice = {
            currencyId,
            gross: 50,
            net: 42.02,
            linked: true,
            listPrice: { currencyId, gross: 80, net: 67.23, linked: true },
        };

        handler.getProducts = jest.fn().mockResolvedValue(undefined);
        handler.blogs = [
            {
                id: 'blog_1',
                price: [originalBasePrice],
            },
        ];

        const syncSpy = jest.spyOn(handler.syncService, 'sync').mockResolvedValue({ data: [] });

        await handler.bulkEdit(
            ['blog_1'],
            [
                {
                    field: 'price',
                    type: 'overwrite',
                    value: [
                        {
                            currencyId,
                            gross: null,
                            net: null,
                            linked: true,
                            listPrice: { currencyId, gross: 100, net: 84.03, linked: true },
                            regulationPrice: null,
                        },
                    ],
                },
            ],
        );

        const syncPayload = syncSpy.mock.calls[0][0];
        const blogPayload = syncPayload['upsert-blog'].payload[0];

        expect(blogPayload.price[0].gross).toBe(50);
        expect(blogPayload.price[0].net).toBe(42.02);
        expect(blogPayload.price[0].linked).toBe(true);
        expect(blogPayload.price[0].listPrice.gross).toBe(100);
    });

    it('should preserve base price fields (gross/net/linked) when only regulationPrice is changed', async () => {
        const currencyId = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
        const handler = getBulkEditProductHandler();
        const originalBasePrice = {
            currencyId,
            gross: 75,
            net: 63.03,
            linked: true,
            regulationPrice: { currencyId, gross: 90, net: 75.63, linked: true },
        };

        handler.getProducts = jest.fn().mockResolvedValue(undefined);
        handler.blogs = [
            {
                id: 'blog_1',
                price: [originalBasePrice],
            },
        ];

        const syncSpy = jest.spyOn(handler.syncService, 'sync').mockResolvedValue({ data: [] });

        await handler.bulkEdit(
            ['blog_1'],
            [
                {
                    field: 'price',
                    type: 'overwrite',
                    value: [
                        {
                            currencyId,
                            gross: null,
                            net: null,
                            linked: true,
                            listPrice: null,
                            regulationPrice: { currencyId, gross: 150, net: 126.05, linked: true },
                        },
                    ],
                },
            ],
        );

        const syncPayload = syncSpy.mock.calls[0][0];
        const blogPayload = syncPayload['upsert-blog'].payload[0];

        expect(blogPayload.price[0].gross).toBe(75);
        expect(blogPayload.price[0].net).toBe(63.03);
        expect(blogPayload.price[0].linked).toBe(true);
        expect(blogPayload.price[0].regulationPrice.gross).toBe(150);
    });

    describe('test buildBulkSyncPayload', () => {
        let handler = null;

        beforeEach(async () => {
            handler = getBulkEditProductHandler();

            handler.groupedPayload = {
                upsert: {},
                delete: {},
            };
            handler.entityName = 'blog';
            handler.entityIds = ['blog_1', 'blog_2'];
        });

        const cases = [
            ['empty changes', [], {}],
            [
                'invalid field',
                [
                    {
                        type: 'overwrite',
                        field: 'invalid-field',
                        value: 'test',
                    },
                    {
                        type: 'clear',
                        field: 'invalid-field-2',
                        value: 'test',
                    },
                ],
                {},
            ],
            [
                'unsupported type',
                [
                    {
                        type: 'not-support-type',
                        field: 'description',
                        value: 'test',
                    },
                ],
                {},
            ],
            [
                'overwrite single field',
                [{ type: 'overwrite', field: 'description', value: 'test' }],
                {
                    'upsert-blog': {
                        action: 'upsert',
                        entity: 'blog',
                        payload: [
                            {
                                id: 'blog_1',
                                description: 'test',
                            },
                            {
                                id: 'blog_2',
                                description: 'test',
                            },
                        ],
                    },
                },
            ],
            [
                'overwrite custom field',
                [
                    {
                        type: 'overwrite',
                        field: 'customFields',
                        value: {
                            custom_health_nostrum_facere_quo: 'lorem ipsum',
                        },
                    },
                ],
                {
                    'upsert-blog': {
                        action: 'upsert',
                        entity: 'blog',
                        payload: [
                            {
                                id: 'blog_1',
                                customFields: {
                                    custom_health_nostrum_facere_quo: 'lorem ipsum',
                                },
                            },
                            {
                                id: 'blog_2',
                                customFields: {
                                    custom_health_nostrum_facere_quo: 'lorem ipsum',
                                },
                            },
                        ],
                    },
                },
            ],
            [
                'clear single string field',
                [{ type: 'clear', field: 'description' }],
                {
                    'upsert-blog': {
                        action: 'upsert',
                        entity: 'blog',
                        payload: [
                            {
                                id: 'blog_1',
                                description: null,
                            },
                            {
                                id: 'blog_2',
                                description: null,
                            },
                        ],
                    },
                },
            ],
            [
                'clear multiple scalar fields',
                [
                    { type: 'clear', field: 'description' },
                    { type: 'clear', field: 'stock' },
                ],
                {
                    'upsert-blog': {
                        action: 'upsert',
                        entity: 'blog',
                        payload: [
                            {
                                id: 'blog_1',
                                description: null,
                                stock: 0,
                            },
                            {
                                id: 'blog_2',
                                description: null,
                                stock: 0,
                            },
                        ],
                    },
                },
            ],
            [
                'overwrite multiple fields',
                [
                    { type: 'overwrite', field: 'description', value: 'test' },
                    {
                        type: 'overwrite',
                        field: 'stock',
                        value: 10,
                    },
                ],
                {
                    'upsert-blog': {
                        action: 'upsert',
                        entity: 'blog',
                        payload: [
                            {
                                id: 'blog_1',
                                description: 'test',
                                stock: 10,
                            },
                            {
                                id: 'blog_2',
                                description: 'test',
                                stock: 10,
                            },
                        ],
                    },
                },
            ],
            [
                'changes with invalid field and unsupported type',
                [
                    { type: 'overwrite', field: 'description', value: 'test' },
                    {
                        type: 'overwrite',
                        field: 'invalid-field',
                        value: 10,
                    },
                    { type: 'un-support-type', field: 'name', value: 10 },
                ],
                {
                    'upsert-blog': {
                        action: 'upsert',
                        entity: 'blog',
                        payload: [
                            {
                                id: 'blog_1',
                                description: 'test',
                            },
                            {
                                id: 'blog_2',
                                description: 'test',
                            },
                        ],
                    },
                },
            ],
            [
                'change association with invalid field',
                [
                    {
                        type: 'overwrite',
                        field: 'invalidField',
                        value: ['category_1', 'category_2'],
                    },
                ],
                {},
            ],
            [
                'overwrite an association with no duplicated',
                [
                    {
                        type: 'overwrite',
                        field: 'categories',
                        value: [{ id: 'category_1' }, { id: 'category_2' }],
                    },
                ],
                {
                    'upsert-blog_category': {
                        action: 'upsert',
                        entity: 'blog_category',
                        payload: [
                            {
                                blogId: 'blog_1',
                                categoryId: 'category_1',
                            },
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_1',
                            },
                            {
                                blogId: 'blog_1',
                                categoryId: 'category_2',
                            },
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_2',
                            },
                        ],
                    },
                },
                {
                    blog_category: [],
                },
            ],
            [
                'overwrite an association with some duplicated',
                [
                    {
                        type: 'overwrite',
                        field: 'categories',
                        value: [{ id: 'category_1' }, { id: 'category_2' }],
                    },
                ],
                {
                    'upsert-blog_category': {
                        action: 'upsert',
                        entity: 'blog_category',
                        payload: [
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_1',
                            },
                            {
                                blogId: 'blog_1',
                                categoryId: 'category_2',
                            },
                        ],
                    },
                    'delete-blog_category': {
                        action: 'delete',
                        entity: 'blog_category',
                        payload: [
                            {
                                blogId: 'blog_1',
                                categoryId: 'category_3',
                            },
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_4',
                            },
                        ],
                    },
                },
                {
                    blog_category: [
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_1',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_2',
                        },
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_3',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_4',
                        },
                    ],
                },
            ],
            [
                'overwrite an oneToMany association',
                [
                    {
                        type: 'overwrite',
                        field: 'media',
                        mappingReferenceField: 'mediaId',
                        value: [{ mediaId: 'media_1' }, { mediaId: 'media_2' }],
                    },
                ],
                {
                    'upsert-blog_media': {
                        action: 'upsert',
                        entity: 'blog_media',
                        payload: [
                            {
                                blogId: 'blog_1',
                                mediaId: 'media_1',
                            },
                            {
                                blogId: 'blog_2',
                                mediaId: 'media_1',
                            },
                        ],
                    },
                    'delete-blog_media': {
                        action: 'delete',
                        entity: 'blog_media',
                        payload: [
                            {
                                id: 'blog_media_3',
                            },
                        ],
                    },
                },
                {
                    blog_media: [
                        {
                            id: 'blog_media_1',
                            blogId: 'blog_1',
                            mediaId: 'media_2',
                        },
                        {
                            id: 'blog_media_2',
                            blogId: 'blog_2',
                            mediaId: 'media_2',
                        },
                        {
                            id: 'blog_media_3',
                            blogId: 'blog_2',
                            mediaId: 'media_3',
                        },
                    ],
                },
            ],
            [
                'overwrite an oneToMany association with extra field',
                [
                    {
                        type: 'overwrite',
                        field: 'visibilities',
                        mappingReferenceField: 'channelId',
                        value: [
                            { channelId: 'scn_1', visibility: 20 },
                            { channelId: 'scn_2', visibility: 30 },
                        ],
                    },
                ],
                {
                    'upsert-blog_visibility': {
                        action: 'upsert',
                        entity: 'blog_visibility',
                        payload: [
                            {
                                blogId: 'blog_2',
                                channelId: 'scn_1',
                                visibility: 20,
                            },
                            {
                                blogId: 'blog_1',
                                channelId: 'scn_2',
                                visibility: 30,
                            },
                            {
                                id: 'blog_scn_2',
                                visibility: 30,
                            },
                        ],
                    },
                    'delete-blog_visibility': {
                        action: 'delete',
                        entity: 'blog_visibility',
                        payload: [
                            {
                                id: 'blog_scn_3',
                            },
                            {
                                id: 'blog_scn_4',
                            },
                        ],
                    },
                },
                {
                    blog_visibility: [
                        {
                            id: 'blog_scn_1',
                            blogId: 'blog_1',
                            visibility: 20,
                            channelId: 'scn_1',
                        },
                        {
                            id: 'blog_scn_2',
                            blogId: 'blog_2',
                            visibility: 20,
                            channelId: 'scn_2',
                        },
                        {
                            id: 'blog_scn_3',
                            blogId: 'blog_1',
                            channelId: 'scn_3',
                        },
                        {
                            id: 'blog_scn_4',
                            blogId: 'blog_2',
                            channelId: 'scn_4',
                        },
                    ],
                },
            ],
            [
                'remove a variant visibility per variant (inheriting vs overriding)',
                [
                    {
                        type: 'remove',
                        field: 'visibilities',
                        mappingReferenceField: 'channelId',
                        // The kept selection is irrelevant for the handler; it routes on the flags below.
                        value: [
                            { channelId: 'scn_1', visibility: 30 },
                            { channelId: 'scn_2', visibility: 30 },
                        ],
                        removedSalesChannelIds: ['scn_3'],
                        inheritedVisibilities: [
                            { channelId: 'scn_1', visibility: 30 },
                            { channelId: 'scn_2', visibility: 30 },
                            { channelId: 'scn_3', visibility: 20 },
                        ],
                    },
                ],
                {
                    // blog_1 inherits → materialize the inherited set minus scn_3.
                    'upsert-blog_visibility': {
                        action: 'upsert',
                        entity: 'blog_visibility',
                        payload: [
                            { blogId: 'blog_1', channelId: 'scn_1', visibility: 30 },
                            { blogId: 'blog_1', channelId: 'scn_2', visibility: 30 },
                        ],
                    },
                    // blog_2 overrides (scn_g, scn_3) → only its own scn_3 row is dropped, scn_g stays.
                    'delete-blog_visibility': {
                        action: 'delete',
                        entity: 'blog_visibility',
                        payload: [{ id: 'pv_2_3' }],
                    },
                },
                {
                    blog_visibility: [
                        {
                            id: 'pv_2_g',
                            blogId: 'blog_2',
                            channelId: 'scn_g',
                            visibility: 30,
                        },
                        {
                            id: 'pv_2_3',
                            blogId: 'blog_2',
                            channelId: 'scn_3',
                            visibility: 20,
                        },
                    ],
                },
            ],
            [
                'remove a variant visibility leaves variants untouched when nothing is removed',
                [
                    {
                        type: 'remove',
                        field: 'visibilities',
                        mappingReferenceField: 'channelId',
                        value: [{ channelId: 'scn_1', visibility: 30 }],
                        removedSalesChannelIds: [],
                        inheritedVisibilities: [{ channelId: 'scn_1', visibility: 30 }],
                    },
                ],
                {},
            ],
            [
                'add a variant visibility keeps the inherited set per variant',
                [
                    {
                        type: 'add',
                        field: 'visibilities',
                        mappingReferenceField: 'channelId',
                        value: [{ channelId: 'scn_1', visibility: 30 }],
                        removedSalesChannelIds: [],
                        // Adding scn_1 which is already inherited must not drop scn_2.
                        addedVisibilities: [{ channelId: 'scn_1', visibility: 30 }],
                        inheritedVisibilities: [
                            { channelId: 'scn_1', visibility: 30 },
                            { channelId: 'scn_2', visibility: 30 },
                        ],
                    },
                ],
                {
                    'upsert-blog_visibility': {
                        action: 'upsert',
                        entity: 'blog_visibility',
                        payload: [
                            // blog_1 inherits → materialize the whole inherited set.
                            { blogId: 'blog_1', channelId: 'scn_1', visibility: 30 },
                            { blogId: 'blog_1', channelId: 'scn_2', visibility: 30 },
                            // blog_2 overrides (owns scn_2) → only scn_1 is added, scn_2 kept.
                            { blogId: 'blog_2', channelId: 'scn_1', visibility: 30 },
                        ],
                    },
                },
                {
                    blog_visibility: [
                        {
                            id: 'pv_2_2',
                            blogId: 'blog_2',
                            channelId: 'scn_2',
                            visibility: 30,
                        },
                    ],
                },
            ],
            [
                'add a brand-new sales channel to a variant keeps its effective set',
                [
                    {
                        type: 'add',
                        field: 'visibilities',
                        mappingReferenceField: 'channelId',
                        value: [{ channelId: 'scn_9', visibility: 10 }],
                        removedSalesChannelIds: [],
                        addedVisibilities: [{ channelId: 'scn_9', visibility: 10 }],
                        inheritedVisibilities: [
                            { channelId: 'scn_1', visibility: 30 },
                            { channelId: 'scn_2', visibility: 30 },
                        ],
                    },
                ],
                {
                    // Both variants inherit → materialize the inherited set plus the new channel.
                    'upsert-blog_visibility': {
                        action: 'upsert',
                        entity: 'blog_visibility',
                        payload: [
                            { blogId: 'blog_1', channelId: 'scn_1', visibility: 30 },
                            { blogId: 'blog_1', channelId: 'scn_2', visibility: 30 },
                            { blogId: 'blog_1', channelId: 'scn_9', visibility: 10 },
                            { blogId: 'blog_2', channelId: 'scn_1', visibility: 30 },
                            { blogId: 'blog_2', channelId: 'scn_2', visibility: 30 },
                            { blogId: 'blog_2', channelId: 'scn_9', visibility: 10 },
                        ],
                    },
                },
                {
                    blog_visibility: [],
                },
            ],
            [
                'remove all channels makes a variant inherit the parent again',
                [
                    {
                        type: 'remove',
                        field: 'visibilities',
                        mappingReferenceField: 'channelId',
                        value: [
                            { channelId: 'scn_1', visibility: 30 },
                            { channelId: 'scn_2', visibility: 30 },
                        ],
                        removedSalesChannelIds: ['scn_1', 'scn_2'],
                        inheritedVisibilities: [
                            { channelId: 'scn_1', visibility: 30 },
                            { channelId: 'scn_2', visibility: 30 },
                        ],
                    },
                ],
                {
                    // The final set is empty for every variant. An inheriting variant
                    // (blog_1) already has no own rows, so nothing happens and it keeps
                    // inheriting. An overriding variant (blog_2) has its own rows deleted,
                    // dropping to zero rows — which means it inherits the parent again.
                    'delete-blog_visibility': {
                        action: 'delete',
                        entity: 'blog_visibility',
                        payload: [{ id: 'pv_2_1' }, { id: 'pv_2_2' }],
                    },
                },
                {
                    blog_visibility: [
                        {
                            id: 'pv_2_1',
                            blogId: 'blog_2',
                            channelId: 'scn_1',
                            visibility: 30,
                        },
                        {
                            id: 'pv_2_2',
                            blogId: 'blog_2',
                            channelId: 'scn_2',
                            visibility: 30,
                        },
                    ],
                },
            ],
            [
                'add an oneToMany association with mapping reference field',
                [
                    {
                        type: 'add',
                        field: 'media',
                        mappingReferenceField: 'mediaId',
                        value: [{ mediaId: 'media_1' }, { mediaId: 'media_2' }],
                    },
                ],
                {
                    'upsert-blog_media': {
                        action: 'upsert',
                        entity: 'blog_media',
                        payload: [
                            {
                                blogId: 'blog_1',
                                mediaId: 'media_1',
                                position: 1,
                            },
                            {
                                blogId: 'blog_2',
                                mediaId: 'media_1',
                                position: 0,
                            },
                            {
                                blogId: 'blog_2',
                                mediaId: 'media_2',
                                position: 1,
                            },
                        ],
                    },
                },
                {
                    blog_media: [
                        {
                            id: 'blog_media_1',
                            blogId: 'blog_1',
                            mediaId: 'media_2',
                        },
                    ],
                },
            ],
            [
                'add an oneToMany association without mapping reference field',
                [
                    {
                        type: 'add',
                        field: 'blogLocations',
                        value: [{ name: 'location 2' }, { name: 'location 3' }],
                    },
                ],
                {
                    'upsert-blog_location': {
                        action: 'upsert',
                        entity: 'blog_location',
                        payload: [
                            {
                                blogId: 'blog_1',
                                name: 'location 2',
                            },
                            {
                                blogId: 'blog_1',
                                name: 'location 3',
                            },
                            {
                                blogId: 'blog_2',
                                name: 'location 2',
                            },
                            {
                                blogId: 'blog_2',
                                name: 'location 3',
                            },
                        ],
                    },
                },
                {
                    blog_location: [
                        {
                            id: 'blog_location_1',
                            blogId: 'blog_1',
                            name: 'location 1',
                        },
                    ],
                },
            ],
            [
                'remove an oneToMany association',
                [
                    {
                        type: 'clear',
                        field: 'media',
                        mappingReferenceField: 'mediaId',
                        value: [{ mediaId: 'media_1' }, { mediaId: 'media_2' }],
                    },
                ],
                {
                    'delete-blog_media': {
                        action: 'delete',
                        entity: 'blog_media',
                        payload: [
                            {
                                id: 'blog_media_1',
                            },
                        ],
                    },
                },
                {
                    blog_media: [
                        {
                            id: 'blog_media_1',
                            blogId: 'blog_1',
                            mediaId: 'media_2',
                        },
                    ],
                },
            ],
            [
                'clear an oneToMany association',
                [
                    {
                        type: 'clear',
                        field: 'media',
                        mappingReferenceField: 'mediaId',
                    },
                ],
                {
                    'delete-blog_media': {
                        action: 'delete',
                        entity: 'blog_media',
                        payload: [
                            {
                                id: 'blog_media_1',
                            },
                            {
                                id: 'blog_media_2',
                            },
                        ],
                    },
                },
                {
                    blog_media: [
                        {
                            id: 'blog_media_1',
                            blogId: 'blog_1',
                            mediaId: 'media_1',
                        },
                        {
                            id: 'blog_media_2',
                            blogId: 'blog_1',
                            mediaId: 'media_2',
                        },
                    ],
                },
            ],
            [
                'overwrite an association with all duplicated',
                [
                    {
                        type: 'overwrite',
                        field: 'categories',
                        value: [{ id: 'category_1' }, { id: 'category_2' }],
                    },
                ],
                {},
                {
                    blog_category: [
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_1',
                        },
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_2',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_1',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_2',
                        },
                    ],
                },
            ],
            [
                'overwrite an association with duplicated',
                [
                    {
                        type: 'overwrite',
                        field: 'categories',
                        value: [{ id: 'category_1' }, { id: 'category_2' }],
                    },
                ],
                {
                    'upsert-blog_category': {
                        action: 'upsert',
                        entity: 'blog_category',
                        payload: [
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_1',
                            },
                            {
                                blogId: 'blog_1',
                                categoryId: 'category_2',
                            },
                        ],
                    },
                },
                {
                    blog_category: [
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_1',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_2',
                        },
                    ],
                },
            ],
            [
                'add an association',
                [
                    {
                        type: 'add',
                        field: 'categories',
                        value: [{ id: 'category_1' }, { id: 'category_2' }, { id: 'category_3' }],
                    },
                ],
                {
                    'upsert-blog_category': {
                        action: 'upsert',
                        entity: 'blog_category',
                        payload: [
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_1',
                            },
                            {
                                blogId: 'blog_1',
                                categoryId: 'category_2',
                            },
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_3',
                            },
                        ],
                    },
                },
                {
                    blog_category: [
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_1',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_2',
                        },
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_3',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_4',
                        },
                    ],
                },
            ],
            [
                'remove an association',
                [
                    {
                        type: 'remove',
                        field: 'categories',
                        value: [{ id: 'category_1' }, { id: 'category_2' }],
                    },
                ],
                {
                    'delete-blog_category': {
                        action: 'delete',
                        entity: 'blog_category',
                        payload: [
                            {
                                blogId: 'blog_1',
                                categoryId: 'category_1',
                            },
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_2',
                            },
                        ],
                    },
                },
                {
                    blog_category: [
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_1',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_2',
                        },
                    ],
                },
            ],
            [
                'all operators at once',
                [
                    { type: 'overwrite', field: 'description', value: 'test' },
                    { type: 'clear', field: 'stock' },
                    {
                        type: 'remove',
                        mappingReferenceField: 'mediaId',
                        field: 'media',
                        value: { mediaId: 'media_1' },
                    },
                    {
                        type: 'add',
                        field: 'categories',
                        value: [{ id: 'category_1' }, { id: 'category_2' }],
                    },
                ],
                {
                    'upsert-blog': {
                        action: 'upsert',
                        entity: 'blog',
                        payload: [
                            {
                                id: 'blog_1',
                                description: 'test',
                                stock: 0,
                            },
                            {
                                id: 'blog_2',
                                description: 'test',
                                stock: 0,
                            },
                        ],
                    },
                    'upsert-blog_category': {
                        action: 'upsert',
                        entity: 'blog_category',
                        payload: [
                            {
                                blogId: 'blog_2',
                                categoryId: 'category_1',
                            },
                            {
                                blogId: 'blog_1',
                                categoryId: 'category_2',
                            },
                        ],
                    },
                    'delete-blog_media': {
                        action: 'delete',
                        entity: 'blog_media',
                        payload: [
                            {
                                id: 'blog_media_1',
                            },
                        ],
                    },
                },
                {
                    blog_category: [
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_1',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_2',
                        },
                        {
                            blogId: 'blog_1',
                            categoryId: 'category_3',
                        },
                        {
                            blogId: 'blog_2',
                            categoryId: 'category_4',
                        },
                    ],
                    blog_media: [
                        {
                            blogId: 'blog_2',
                            mediaId: 'media_1',
                            id: 'blog_media_1',
                        },
                    ],
                },
            ],
            [
                'add more than 500 oneToMany association',
                [
                    {
                        type: 'add',
                        field: 'media',
                        mappingReferenceField: 'mediaId',
                        value: Array(highAssociationCount)
                            .fill(0)
                            .map((v, k) => ({ mediaId: `media_${k}` })),
                    },
                ],
                {
                    'upsert-blog_media': {
                        action: 'upsert',
                        entity: 'blog_media',
                        payload: Array(highAssociationCount)
                            .fill(0)
                            .map((v, k) => ({
                                blogId: 'blog_1',
                                mediaId: `media_${k}`,
                                position: k,
                            })),
                    },
                },
                {
                    blog_media: Array(highAssociationCount)
                        .fill(0)
                        .map((v, k) => ({
                            id: `blog_media_${k}`,
                            blogId: 'blog_2',
                            mediaId: `media_${k}`,
                        })),
                },
            ],
            [
                'add more than 500 manyToMany association',
                [
                    {
                        type: 'add',
                        field: 'categories',
                        value: Array(highAssociationCount)
                            .fill(0)
                            .map((v, k) => ({ id: `category_${k}` })),
                    },
                ],
                {
                    'upsert-blog_category': {
                        action: 'upsert',
                        entity: 'blog_category',
                        payload: Array(highAssociationCount)
                            .fill(0)
                            .map((v, k) => ({
                                blogId: 'blog_1',
                                categoryId: `category_${k}`,
                            })),
                    },
                },
                {
                    blog_category: Array(highAssociationCount)
                        .fill(0)
                        .map((v, k) => ({
                            id: `blog_category_${k}`,
                            blogId: 'blog_2',
                            categoryId: `category_${k}`,
                        })),
                },
            ],
            [
                'overwrite an oneToOne association',
                [
                    {
                        type: 'overwrite',
                        field: 'blogAI',
                        value: [{ name: 'ai 1' }],
                    },
                ],
                {
                    'upsert-blog_ai': {
                        action: 'upsert',
                        entity: 'blog_ai',
                        payload: [
                            {
                                id: 'blog_ai_1',
                                name: 'ai 1',
                            },
                            {
                                blogId: 'blog_2',
                                name: 'ai 1',
                            },
                        ],
                    },
                },
                {
                    blog_ai: [
                        {
                            id: 'blog_ai_1',
                            blogId: 'blog_1',
                            name: 'b',
                        },
                    ],
                },
            ],
            [
                'add an oneToOne association',
                [
                    {
                        type: 'add',
                        field: 'blogAI',
                        value: [{ name: 'ai 1' }],
                    },
                ],
                {
                    'upsert-blog_ai': {
                        action: 'upsert',
                        entity: 'blog_ai',
                        payload: [
                            {
                                id: 'blog_ai_1',
                                name: 'ai 1',
                            },
                            {
                                blogId: 'blog_2',
                                name: 'ai 1',
                            },
                        ],
                    },
                },
                {
                    blog_ai: [
                        {
                            id: 'blog_ai_1',
                            blogId: 'blog_1',
                            name: 'b',
                        },
                    ],
                },
            ],
            [
                'remove an oneToOne association',
                [
                    {
                        type: 'clear',
                        field: 'blogAI',
                        value: [{ name: 'ai 1' }],
                    },
                ],
                {
                    'delete-blog_ai': {
                        action: 'delete',
                        entity: 'blog_ai',
                        payload: [
                            {
                                id: 'blog_ai_1',
                            },
                        ],
                    },
                },
                {
                    blog_ai: [
                        {
                            id: 'blog_ai_1',
                            blogId: 'blog_1',
                            name: 'b',
                        },
                    ],
                },
            ],
        ];

        it.each(cases)('%s', async (testName, input, output, existAssociations = {}) => {
            const mockEntitySchema = {
                blog: {
                    entity: 'blog',
                    properties: {
                        id: {
                            type: 'uuid',
                        },
                        price: {
                            type: 'json_object',
                            properties: [],
                        },
                        cover: {
                            type: 'association',
                            relation: 'many_to_one',
                            entity: 'blog_media',
                        },
                        name: {
                            type: 'string',
                        },
                        description: {
                            type: 'string',
                        },
                        stock: {
                            type: 'int',
                        },
                        customFields: {
                            type: 'json_object',
                        },
                        media: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'blog_media',
                            localField: 'id',
                            referenceField: 'blogId',
                        },
                        manufacturer: {
                            type: 'association',
                            relation: 'many_to_one',
                            entity: 'blog_manufacturer',
                        },
                        translations: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'blog_translation',
                        },
                        categories: {
                            type: 'association',
                            relation: 'many_to_many',
                            entity: 'category',
                            flags: {},
                            localField: 'id',
                            referenceField: 'id',
                            mapping: 'blog_category',
                            local: 'blogId',
                            reference: 'categoryId',
                        },
                        visibilities: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'blog_visibility',
                            localField: 'id',
                            referenceField: 'blogId',
                        },
                        blogAI: {
                            type: 'association',
                            relation: 'one_to_one',
                            entity: 'blog_ai',
                            localField: 'id',
                            referenceField: 'blogId',
                        },
                        blogLocations: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'blog_location',
                            localField: 'id',
                            referenceField: 'blogId',
                        },
                    },
                },
                blog_category: {
                    entity: 'blog_category',
                    relation: 'many_to_many',
                },
                blog_visibility: {
                    entity: 'blog_visibility',
                    properties: {
                        id: {
                            type: 'uuid',
                        },
                        blogId: {
                            type: 'uuid',
                        },
                        channelId: {
                            type: 'uuid',
                        },
                        visibility: {
                            type: 'int',
                        },
                    },
                },
                blog_manufacturer: {
                    entity: 'blog_manufacturer',
                    properties: {
                        id: {
                            type: 'uuid',
                        },
                        name: {
                            type: 'string',
                        },
                        media: {
                            type: 'association',
                            relation: 'many_to_one',
                            entity: 'media',
                        },
                        blogs: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'blog',
                        },
                    },
                },
                blog_media: {
                    entity: 'blog_media',
                    properties: {
                        id: {
                            type: 'uuid',
                        },
                        position: {
                            type: 'int',
                        },
                        media: {
                            type: 'association',
                            relation: 'many_to_one',
                            entity: 'media',
                        },
                    },
                },
                media: {
                    entity: 'media',
                    properties: {
                        id: {
                            type: 'uuid',
                        },
                        translations: {
                            type: 'association',
                            relation: 'one_to_many',
                            entity: 'media_translation',
                        },
                    },
                },
                blog_ai: {
                    entity: 'blog_ai',
                    relation: 'one_to_one',
                    properties: {
                        id: {
                            type: 'uuid',
                        },
                        blogId: {
                            type: 'uuid',
                        },
                        name: {
                            type: 'string',
                        },
                    },
                },
                blog_location: {
                    entity: 'blog_ai',
                    relation: 'one_to_many',
                    properties: {
                        id: {
                            type: 'uuid',
                        },
                        blogId: {
                            type: 'uuid',
                        },
                        name: {
                            type: 'string',
                        },
                    },
                },
            };

            Contena.EntityDefinition = EntityDefinitionFactory;
            Object.keys(mockEntitySchema).forEach((entity) => {
                Contena.EntityDefinition.add(entity, mockEntitySchema[entity]);
            });

            const spy = jest.spyOn(console, 'warn').mockImplementation();

            const spyRepository = jest.spyOn(handler.repositoryFactory, 'create').mockImplementation((entity) => {
                return {
                    search: async (criteria) => {
                        const response = paginate(existAssociations[entity], criteria);
                        response.total = existAssociations[entity].length;

                        return Promise.resolve(response);
                    },
                    searchIds: async (criteria) => {
                        const response = {
                            data: paginate(existAssociations[entity], criteria),
                            total: existAssociations[entity].length,
                        };

                        return Promise.resolve(response);
                    },
                };
            });

            expect(await handler.buildBulkSyncPayload(input)).toEqual(output);

            spy.mockRestore();

            spyRepository.mockRestore();
        });

        it('appends bulk-added media at each blog own next position', async () => {
            // Scenario: blog_1 & blog_2 have 2 images (positions 1, 2), blog_3 has 3 images
            // (positions 1, 2, 3). Adding one image via bulk edit must append it at position 3 for blog_1 and
            // blog_2, but at position 4 for blog_3 - a per-blog position, not a single shared one.
            const scopedHandler = getBulkEditProductHandler();
            scopedHandler.groupedPayload = { upsert: {}, delete: {} };
            scopedHandler.entityName = 'blog';
            scopedHandler.entityIds = ['blog_1', 'blog_2', 'blog_3'];

            Contena.EntityDefinition = EntityDefinitionFactory;
            Contena.EntityDefinition.add('blog', {
                entity: 'blog',
                properties: {
                    id: { type: 'uuid' },
                    media: {
                        type: 'association',
                        relation: 'one_to_many',
                        entity: 'blog_media',
                        localField: 'id',
                        referenceField: 'blogId',
                    },
                },
            });
            Contena.EntityDefinition.add('blog_media', {
                entity: 'blog_media',
                properties: {
                    id: { type: 'uuid' },
                    position: { type: 'int' },
                    media: { type: 'association', relation: 'many_to_one', entity: 'media' },
                },
            });
            Contena.EntityDefinition.add('media', {
                entity: 'media',
                properties: { id: { type: 'uuid' } },
            });

            const existingMedia = [
                { id: 'pm_1_1', blogId: 'blog_1', mediaId: 'media_a', position: 1 },
                { id: 'pm_1_2', blogId: 'blog_1', mediaId: 'media_b', position: 2 },
                { id: 'pm_2_1', blogId: 'blog_2', mediaId: 'media_a', position: 1 },
                { id: 'pm_2_2', blogId: 'blog_2', mediaId: 'media_b', position: 2 },
                { id: 'pm_3_1', blogId: 'blog_3', mediaId: 'media_a', position: 1 },
                { id: 'pm_3_2', blogId: 'blog_3', mediaId: 'media_b', position: 2 },
                { id: 'pm_3_3', blogId: 'blog_3', mediaId: 'media_c', position: 3 },
            ];

            const spyRepository = jest.spyOn(scopedHandler.repositoryFactory, 'create').mockImplementation(() => {
                return {
                    search: async (criteria) => {
                        const response = paginate(existingMedia, criteria);
                        response.total = existingMedia.length;

                        return Promise.resolve(response);
                    },
                };
            });

            const payload = await scopedHandler.buildBulkSyncPayload([
                {
                    type: 'add',
                    field: 'media',
                    mappingReferenceField: 'mediaId',
                    value: [{ mediaId: 'media_new' }],
                },
            ]);

            expect(payload['upsert-blog_media'].payload).toEqual([
                { blogId: 'blog_1', mediaId: 'media_new', position: 3 },
                { blogId: 'blog_2', mediaId: 'media_new', position: 3 },
                { blogId: 'blog_3', mediaId: 'media_new', position: 4 },
            ]);

            spyRepository.mockRestore();
        });
    });
});
