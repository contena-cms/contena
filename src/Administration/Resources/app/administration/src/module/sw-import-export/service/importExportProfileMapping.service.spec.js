/**
 * @sw-package fundamentals@after-sales
 */
import ImportExportProfileMappingService from 'src/module/sw-import-export/service/importExportProfileMapping.service';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';
import * as mappings from './mocks/mappings.mock';
import withRequiredProductType from 'src/../test/_helper_/withRequiredProductType';

const requiredProductMappingsWithType = [...mappings.blogProfileOnlyRequired, { key: 'type', mappedKey: 'type' }];

describe('module/sw-import-export/service/importExportProfileMapping.service.spec.js', () => {
    let importExportProfileMappingService;

    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Contena.EntityDefinition.add(entityName, entityDefinition);
        });

        importExportProfileMappingService = new ImportExportProfileMappingService(Contena.EntityDefinition);
    });

    it('should contain all public functions', async () => {
        expect(typeof importExportProfileMappingService.validate).toBe('function');
    });

    it('uses the blog type requirement for the active schema', () => {
        expect(Contena.EntityDefinition.get('blog').properties.type.flags.required).toBe(
            Contena.Feature.isActive('v6.8.0.0') ? true : undefined,
        );
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type mapping.
    it.deprecated('v6.8.0.0')('blog: should not find any missing required fields', () => {
        const violations = importExportProfileMappingService.validate('blog', mappings.blogProfileOnlyRequired);

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(0);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'blog: should not find any missing required fields',
        withRequiredProductType(() => {
            const violations = importExportProfileMappingService.validate('blog', requiredProductMappingsWithType);

            expect(violations.missingRequiredFields).toHaveLength(0);
            expect(violations.duplicateMappings).toHaveLength(0);
        }),
    );

    [
        'id',
        'versionId',
        'parentVersionId',
        'stock',
        'blogManufacturerVersionId',
        'taxId',
        'blogNumber',
    ].forEach((fieldName) => {
        // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type mapping.
        it.deprecated('v6.8.0.0')(`blog: should find missing required field ${fieldName}`, () => {
            const mapping = mappings.blogProfileOnlyRequired.filter((field) => field.key !== fieldName);
            const violations = importExportProfileMappingService.validate('blog', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain(fieldName);
        });

        it.activeFeatureFlags(['v6.8.0.0'])(
            `blog: should find missing required field ${fieldName}`,
            withRequiredProductType(() => {
                const mapping = requiredProductMappingsWithType.filter((field) => field.key !== fieldName);
                const violations = importExportProfileMappingService.validate('blog', mapping);

                expect(violations.missingRequiredFields).toHaveLength(1);
                expect(violations.duplicateMappings).toHaveLength(0);

                expect(violations.missingRequiredFields).toContain(fieldName);
            }),
        );
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type mapping.
    it.deprecated('v6.8.0.0')('blog: should find missing required field name', () => {
        const mapping = mappings.blogProfileOnlyRequired.filter((field) => field.key !== 'translations.DEFAULT.name');
        const violations = importExportProfileMappingService.validate('blog', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('name');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'blog: should find missing required field name',
        withRequiredProductType(() => {
            const mapping = requiredProductMappingsWithType.filter((field) => field.key !== 'translations.DEFAULT.name');
            const violations = importExportProfileMappingService.validate('blog', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain('name');
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type mapping.
    it.deprecated('v6.8.0.0')('blog: should find missing required field createdAt', () => {
        const mapping = mappings.blogProfileOnlyRequired.filter(
            (field) => field.key !== 'translations.DEFAULT.createdAt',
        );
        const violations = importExportProfileMappingService.validate('blog', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('createdAt');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'blog: should find missing required field createdAt',
        withRequiredProductType(() => {
            const mapping = requiredProductMappingsWithType.filter(
                (field) => field.key !== 'translations.DEFAULT.createdAt',
            );
            const violations = importExportProfileMappingService.validate('blog', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain('createdAt');
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type schema.
    it.deprecated('v6.8.0.0')('blog: should return all missing required fields', () => {
        const violations = importExportProfileMappingService.validate('blog', []);

        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields.sort()).toEqual(
            [
                'id',
                'versionId',
                'parentVersionId',
                'blogManufacturerVersionId',
                'blogMediaVersionId',
                'taxId',
                'blogNumber',
                'stock',
                'name',
                'canonicalProductVersionId',
                'cmsPageVersionId',
                'createdAt',
            ].sort(),
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'blog: should return all missing required fields',
        withRequiredProductType(() => {
            const violations = importExportProfileMappingService.validate('blog', []);

            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields.sort()).toEqual(
                [
                    'id',
                    'versionId',
                    'parentVersionId',
                    'blogManufacturerVersionId',
                    'blogMediaVersionId',
                    'taxId',
                    'blogNumber',
                    'stock',
                    'name',
                    'canonicalProductVersionId',
                    'cmsPageVersionId',
                    'createdAt',
                    'type',
                ].sort(),
            );
        }),
    );

    it('blog: should find missing required when parentProduct is existing', async () => {
        const mapping = mappings.blogDuplicateProfileOnlyRequired.filter((field) => field.key === 'blogNumber');
        const violations = importExportProfileMappingService.validate(
            'blog',
            mapping,
            mappings.blogDuplicateProfileOnlyRequired,
        );

        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toEqual(['id', 'taxId']);
    });

    it('blog: should not find any missing required when parentProduct is existing', async () => {
        const violations = importExportProfileMappingService.validate(
            'blog',
            mappings.blogDuplicateProfileOnlyRequired,
            mappings.blogDuplicateProfileOnlyRequired,
        );

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(0);
    });

    it('blog: should find missing required when key.id is existing', async () => {
        const violations = importExportProfileMappingService.validate(
            'blog',
            [
                {
                    id: 'fc416f509b0b46fabb8cd8728cf63531',
                    key: 'tax.id',
                    mappedKey: 'tax_id',
                },
            ],
            mappings.blogDuplicateProfileOnlyRequired,
        );

        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toEqual(['id', 'blogNumber']);
    });

    it('media: should not find any missing required fields', async () => {
        const violations = importExportProfileMappingService.validate('media', mappings.mediaProfileOnlyRequired);

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(0);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type mapping.
    it.deprecated('v6.8.0.0')('media: should find missing required field id', () => {
        const mapping = mappings.blogProfileOnlyRequired.filter((field) => field.key !== 'id');

        const violations = importExportProfileMappingService.validate('blog', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('id');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'media: should find missing required field id',
        withRequiredProductType(() => {
            const mapping = requiredProductMappingsWithType.filter((field) => field.key !== 'id');

            const violations = importExportProfileMappingService.validate('blog', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain('id');
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type mapping.
    it.deprecated('v6.8.0.0')('media: should find missing required field createdAt', () => {
        const mapping = mappings.blogProfileOnlyRequired.filter(
            (field) => field.key !== 'translations.DEFAULT.createdAt',
        );
        const violations = importExportProfileMappingService.validate('blog', mapping);

        expect(violations.missingRequiredFields).toHaveLength(1);
        expect(violations.duplicateMappings).toHaveLength(0);

        expect(violations.missingRequiredFields).toContain('createdAt');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'media: should find missing required field createdAt',
        withRequiredProductType(() => {
            const mapping = requiredProductMappingsWithType.filter(
                (field) => field.key !== 'translations.DEFAULT.createdAt',
            );
            const violations = importExportProfileMappingService.validate('blog', mapping);

            expect(violations.missingRequiredFields).toHaveLength(1);
            expect(violations.duplicateMappings).toHaveLength(0);

            expect(violations.missingRequiredFields).toContain('createdAt');
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type mapping.
    it.deprecated('v6.8.0.0')('should detect duplicate mapping keys', () => {
        const mapping = [
            ...mappings.blogProfileOnlyRequired,
            mappings.blogProfileOnlyRequired.find((mapping) => mapping.key === 'id'),
        ];

        const violations = importExportProfileMappingService.validate('blog', mapping);

        expect(violations.missingRequiredFields).toHaveLength(0);
        expect(violations.duplicateMappings).toHaveLength(1);

        expect(violations.duplicateMappings.at(0).key).toBe('id');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should detect duplicate mapping keys',
        withRequiredProductType(() => {
            const mapping = [
                ...requiredProductMappingsWithType,
                requiredProductMappingsWithType.find((mapping) => mapping.key === 'id'),
            ];

            const violations = importExportProfileMappingService.validate('blog', mapping);

            expect(violations.missingRequiredFields).toHaveLength(0);
            expect(violations.duplicateMappings).toHaveLength(1);

            expect(violations.duplicateMappings.at(0).key).toBe('id');
        }),
    );

    it('category: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('category', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'displayNestedProducts',
            'type',
            'blogAssignmentType',
            'translations.DEFAULT.name',
        ]);
    });

    it('category: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('category', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'displayNestedProducts',
            'type',
            'blogAssignmentType',
            'translations.DEFAULT.name',
        ]);
    });

    it('blog_cross_selling: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('blog_cross_selling', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'position',
            'type',
            'blog.id',
            'translations.DEFAULT.name',
        ]);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type schema.
    it.deprecated('v6.8.0.0')('blog_cross_selling: should list all required fields with depth 3', () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('blog_cross_selling', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'position',
            'type',
            'blog.id',
            'blog.price.DEFAULT.net',
            'blog.price.DEFAULT.gross',
            'blog.blogNumber',
            'blog.stock',
            'blog.tax.id',
            'blog.tax.taxRate',
            'blog.tax.name',
            'blog.tax.position',
            'blog.translations.DEFAULT.name',
            'translations.DEFAULT.name',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'blog_cross_selling: should list all required fields with depth 3',
        withRequiredProductType(() => {
            const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields(
                'blog_cross_selling',
                3,
            );

            expect(Object.keys(systemRequiredFields)).toEqual([
                'id',
                'position',
                'type',
                'blog.id',
                'blog.price.DEFAULT.net',
                'blog.price.DEFAULT.gross',
                'blog.blogNumber',
                'blog.stock',
                'blog.tax.id',
                'blog.tax.taxRate',
                'blog.tax.name',
                'blog.tax.position',
                'blog.translations.DEFAULT.name',
                'blog.type',
                'translations.DEFAULT.name',
            ]);
        }),
    );

    it('media: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('media', 1);

        expect(Object.keys(systemRequiredFields)).toEqual(['id']);
    });

    it('media: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('media', 3);

        expect(Object.keys(systemRequiredFields)).toEqual(['id']);
    });

    it('newsletter_recipient: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('newsletter_recipient', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'email',
            'status',
            'hash',
            'language.id',
            'channel.id',
        ]);
    });

    it('newsletter_recipient: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('newsletter_recipient', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'email',
            'status',
            'hash',
            'language.id',
            'language.name',
            'language.active',
            'language.locale.id',
            'language.locale.code',
            'language.locale.translations.DEFAULT.name',
            'language.locale.translations.DEFAULT.territory',
            'channel.id',
            'channel.accessKey',
            'channel.translations.DEFAULT.name',
            'channel.translations.DEFAULT.homeEnabled',
            'channel.type.id',
            'channel.type.translations.DEFAULT.name',
            'channel.userGroup.id',
            'channel.userGroup.translations.DEFAULT.name',
            'channel.currency.id',
            'channel.currency.factor',
            'channel.currency.symbol',
            'channel.currency.isoCode',
            'channel.currency.translations.DEFAULT.shortName',
            'channel.currency.translations.DEFAULT.name',
            'channel.currency.itemRounding',
            'channel.currency.totalRounding',
            'channel.paymentMethod.id',
            'channel.paymentMethod.technicalName',
            'channel.paymentMethod.translations.DEFAULT.name',
            'channel.shippingMethod.id',
            'channel.shippingMethod.technicalName',
            'channel.shippingMethod.taxType',
            'channel.shippingMethod.deliveryTime.id',
            'channel.shippingMethod.translations.DEFAULT.name',
            'channel.country.id',
            'channel.country.isEu',
            'channel.country.translations.DEFAULT.name',
            'channel.country.translations.DEFAULT.addressFormat',
            'channel.navigationCategory.id',
            'channel.navigationCategory.displayNestedProducts',
            'channel.navigationCategory.type',
            'channel.navigationCategory.blogAssignmentType',
            'channel.navigationCategory.translations.DEFAULT.name',
        ]);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type schema.
    it.deprecated('v6.8.0.0')('blog: should list all required fields with depth 1', () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('blog', 1);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'price.DEFAULT.net',
            'price.DEFAULT.gross',
            'blogNumber',
            'stock',
            'tax.id',
            'translations.DEFAULT.name',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'blog: should list all required fields with depth 1',
        withRequiredProductType(() => {
            const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('blog', 1);

            expect(Object.keys(systemRequiredFields)).toEqual([
                'id',
                'price.DEFAULT.net',
                'price.DEFAULT.gross',
                'blogNumber',
                'stock',
                'tax.id',
                'translations.DEFAULT.name',
                'type',
            ]);
        }),
    );

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type schema.
    it.deprecated('v6.8.0.0')('blog: should list all required fields with depth 3', () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('blog', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'price.DEFAULT.net',
            'price.DEFAULT.gross',
            'blogNumber',
            'stock',
            'tax.id',
            'tax.taxRate',
            'tax.name',
            'tax.position',
            'translations.DEFAULT.name',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'blog: should list all required fields with depth 3',
        withRequiredProductType(() => {
            const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('blog', 3);

            expect(Object.keys(systemRequiredFields)).toEqual([
                'id',
                'price.DEFAULT.net',
                'price.DEFAULT.gross',
                'blogNumber',
                'stock',
                'tax.id',
                'tax.taxRate',
                'tax.name',
                'tax.position',
                'translations.DEFAULT.name',
                'type',
            ]);
        }),
    );

    it('property_group_option: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('property_group_option', 1);

        expect(Object.keys(systemRequiredFields)).toEqual(['id', 'group.id', 'translations.DEFAULT.name']);
    });

    it('property_group_option: should list all required fields with depth 3', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields('property_group_option', 3);

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'group.id',
            'group.displayType',
            'group.sortingType',
            'group.translations.DEFAULT.name',
            'translations.DEFAULT.name',
        ]);
    });

    it('blog_configurator_setting: should list all required fields with depth 1', async () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields(
            'blog_configurator_setting',
            1,
        );

        expect(Object.keys(systemRequiredFields)).toEqual(['id', 'blog.id', 'option.id']);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the optional blog type schema.
    it.deprecated('v6.8.0.0')('blog_configurator_setting: should list all required fields with depth 3', () => {
        const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields(
            'blog_configurator_setting',
            3,
        );

        expect(Object.keys(systemRequiredFields)).toEqual([
            'id',
            'blog.id',
            'blog.price.DEFAULT.net',
            'blog.price.DEFAULT.gross',
            'blog.blogNumber',
            'blog.stock',
            'blog.tax.id',
            'blog.tax.taxRate',
            'blog.tax.name',
            'blog.tax.position',
            'blog.translations.DEFAULT.name',
            'option.id',
            'option.group.id',
            'option.group.displayType',
            'option.group.sortingType',
            'option.group.translations.DEFAULT.name',
            'option.translations.DEFAULT.name',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'blog_configurator_setting: should list all required fields with depth 3',
        withRequiredProductType(() => {
            const systemRequiredFields = importExportProfileMappingService.getSystemRequiredFields(
                'blog_configurator_setting',
                3,
            );

            expect(Object.keys(systemRequiredFields)).toEqual([
                'id',
                'blog.id',
                'blog.price.DEFAULT.net',
                'blog.price.DEFAULT.gross',
                'blog.blogNumber',
                'blog.stock',
                'blog.tax.id',
                'blog.tax.taxRate',
                'blog.tax.name',
                'blog.tax.position',
                'blog.translations.DEFAULT.name',
                'blog.type',
                'option.id',
                'option.group.id',
                'option.group.displayType',
                'option.group.sortingType',
                'option.group.translations.DEFAULT.name',
                'option.translations.DEFAULT.name',
            ]);
        }),
    );
});
