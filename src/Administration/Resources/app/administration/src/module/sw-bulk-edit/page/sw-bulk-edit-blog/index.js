import template from './sw-bulk-edit-blog.html.twig';
import './sw-bulk-edit-blog.scss';
import '../../../sw-blog/page/sw-blog-detail/store';

const { Context } = Contena;
const { Criteria, EntityCollection } = Contena.Data;
const { types } = Contena.Utils;
const { chunk } = Contena.Utils.array;
const { cloneDeep } = Contena.Utils.object;
const { convert } = Contena.Utils.unitConversion;

/**
 * @sw-package inventory
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'feature',
        'bulkEditApiFactory',
        'repositoryFactory',
        'customFieldDataProviderService',
    ],

    data() {
        return {
            isLoading: false,
            isLoadedData: false,
            isSaveSuccessful: false,
            displayAdvancePricesModal: false,
            /**
             * @deprecated tag:v6.8.0 - will be removed without replacement
             */
            isDisabledListPrice: true,
            /**
             * @deprecated tag:v6.8.0 - will be removed without replacement
             */
            isDisabledRegulationPrice: true,
            bulkEditProduct: {},
            bulkEditSelected: [],
            taxRate: {},
            currency: {},
            customFieldSets: [],
            processStatus: '',
            rules: [],
            parentProductFrozen: null,
            isComponentMounted: true,
            lengthUnit: 'mm',
            weightUnit: 'kg',
            preferenceUnits: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        taxes() {
            return Contena.Store.get('swProductDetail').taxes;
        },

        defaultCurrency() {
            return Contena.Store.get('swProductDetail').defaultCurrency;
        },

        defaultPrice() {
            return Contena.Store.get('swProductDetail').defaultPrice;
        },

        selectedIds() {
            return Contena.Store.get('swBulkEdit').selectedIds;
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        hasSelectedChanges() {
            return Object.values(this.bulkEditProduct).some((field) => field.isChanged) || this.bulkEditSelected.length > 0;
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'blog'));

            return criteria;
        },

        taxCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('position'));

            return criteria;
        },

        blogCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.getAssociation('media').addSorting(Criteria.sort('position', 'ASC'));

            criteria.getAssociation('properties').addSorting(Criteria.sort('name', 'ASC', true));

            criteria
                .getAssociation('prices')
                .addSorting(Criteria.sort('quantityStart', 'ASC', true))
                .addAssociation('rule');

            criteria.getAssociation('tags').addSorting(Criteria.sort('name', 'ASC'));

            criteria.getAssociation('seoUrls').addFilter(Criteria.equals('isCanonical', true));

            criteria
                .getAssociation('crossSellings')
                .addSorting(Criteria.sort('position', 'ASC'))
                .getAssociation('assignedProducts')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('blog')
                .getAssociation('blog')
                .addAssociation('options.group');

            criteria
                .addAssociation('cover')
                .addAssociation('categories')
                .addAssociation('visibilities.channel')
                .addAssociation('options')
                .addAssociation('configuratorSettings.option')
                .addAssociation('unit')
                .addAssociation('blogReviews')
                .addAssociation('seoUrls')
                .addAssociation('mainCategories')
                .addAssociation('options.group')
                .addAssociation('customFieldSets')
                .addAssociation('featureSet')
                .addAssociation('cmsPage')
                .addAssociation('featureSet');

            criteria.getAssociation('manufacturer').addAssociation('media');

            return criteria;
        },

        isChild() {
            return this.$route.params.parentId !== 'null';
        },

        restrictedFields() {
            let restrictedFields = [];
            const includesDigital = this.$route.params.includesDigital;

            if (includesDigital === '1' || includesDigital === '2') {
                restrictedFields = [
                    'isCloseout',
                    'restockTime',
                    'shippingFree',
                ];
            }
            if (includesDigital === '1') {
                restrictedFields.push('stock');
            }

            return restrictedFields;
        },

        generalFormFields() {
            return [
                {
                    name: 'description',
                    type: 'html',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-bulk-edit-blog-description',
                        changeLabel: this.$t('sw-bulk-edit.blog.generalInformation.description.changeLabel'),
                        disabled: this.bulkEditProduct?.description?.isInherited,
                    },
                },
                {
                    name: 'manufacturerId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'blog_manufacturer',
                        changeLabel: this.$t('sw-bulk-edit.blog.generalInformation.manufacturer.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.generalInformation.manufacturer.placeholderManufacturer'),
                        disabled: this.bulkEditProduct?.manufacturerId?.isInherited,
                    },
                },
                {
                    name: 'active',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$t('sw-bulk-edit.blog.generalInformation.active.switchLabel'),
                        changeLabel: this.$t('sw-bulk-edit.blog.generalInformation.active.changeLabel'),
                        disabled: this.bulkEditProduct?.active?.isInherited,
                    },
                },
                {
                    name: 'markAsTopseller',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$t('sw-bulk-edit.blog.generalInformation.blogPromotion.switchLabel'),
                        changeLabel: this.$t('sw-bulk-edit.blog.generalInformation.blogPromotion.changeLabel'),
                        disabled: this.bulkEditProduct?.markAsTopseller?.isInherited,
                    },
                },
            ];
        },

        pricesFormFields() {
            const fields = [
                {
                    name: 'taxId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'tax',
                        changeLabel: this.$t('sw-bulk-edit.blog.prices.taxRate.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.prices.taxRate.placeholderTax'),
                        disabled: this.bulkEditProduct?.taxId?.isInherited,
                    },
                },
                {
                    name: 'price',
                    config: {
                        componentName: 'sw-price-field',
                        price: this.blog?.price,
                        taxRate: this.taxRate,
                        currency: this.currency,
                        changeLabel: this.isChild
                            ? this.$t('sw-bulk-edit.blog.prices.price.label')
                            : this.$t('sw-bulk-edit.blog.prices.price.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.prices.price.placeholderPrice'),
                        disabled: this.isChild ? this.bulkEditProduct?.isPriceInherited?.isInherited : false,
                    },
                },
                {
                    name: 'purchasePrices',
                    config: {
                        componentName: 'sw-price-field',
                        price: this.blog?.purchasePrices,
                        taxRate: this.taxRate,
                        currency: this.currency,
                        changeLabel: this.isChild
                            ? this.$t('sw-bulk-edit.blog.prices.purchasePrices.label')
                            : this.$t('sw-bulk-edit.blog.prices.purchasePrices.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.prices.purchasePrices.placeholderPurchasePrices'),
                        disabled: this.isChild ? this.bulkEditProduct?.isPriceInherited?.isInherited : false,
                    },
                },
                {
                    name: 'listPrice',
                    config: {
                        componentName: 'sw-price-field',
                        price: this.blog?.listPrice,
                        taxRate: this.taxRate,
                        currency: this.currency,
                        changeLabel: this.isChild
                            ? this.$t('sw-bulk-edit.blog.prices.listPrice.label')
                            : this.$t('sw-bulk-edit.blog.prices.listPrice.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.prices.listPrice.placeholderListPrice'),
                        disabled: this.isChild ? this.bulkEditProduct?.isPriceInherited?.isInherited : false,
                    },
                },
                {
                    name: 'regulationPrice',
                    config: {
                        componentName: 'sw-price-field',
                        price: this.blog?.regulationPrice,
                        taxRate: this.taxRate,
                        currency: this.currency,
                        changeLabel: this.isChild
                            ? this.$t('sw-bulk-edit.blog.prices.regulationPrice.label')
                            : this.$t('sw-bulk-edit.blog.prices.regulationPrice.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.prices.regulationPrice.placeholderRegulationPrice'),
                        disabled: this.isChild ? this.bulkEditProduct?.isPriceInherited?.isInherited : false,
                    },
                },
            ];

            if (this.isChild) {
                const isPriceInherited = {
                    name: 'isPriceInherited',
                    canInherit: this.isChild,
                    config: {
                        componentName: '',
                        changeLabel: this.$t('sw-bulk-edit.blog.prices.isPriceInherited.changeLabel'),
                    },
                };
                fields.splice(1, 0, isPriceInherited);
            }

            return fields;
        },

        advancedPricesFormFields() {
            return [
                {
                    name: 'prices',
                    canInherit: this.isChild,
                    config: {
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$t('sw-bulk-edit.blog.advancedPrices.changeLabel'),
                    },
                },
            ];
        },

        propertyFormFields() {
            return [
                {
                    name: 'properties',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-blog-properties',
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$t('sw-bulk-edit.blog.property.changeLabel'),
                        emptyStateTitle: this.$t('sw-bulk-edit.blog.property.titleEmptyState'),
                        emptyStateDescription: this.$t('sw-bulk-edit.blog.property.descriptionEmptyState'),
                        disabled: this.bulkEditProduct?.properties?.isInherited,
                        isAssociation: false,
                        showInheritanceSwitcher: false,
                    },
                },
            ];
        },

        deliverabilityFormFields() {
            const fields = [
                {
                    name: 'stock',
                    type: 'int',
                    canInherit: false,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.deliverability.stock.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.deliverability.stock.placeholderStock'),
                        numberType: 'int',
                        allowEmpty: true,
                        allowOverwrite: true,
                        allowClear: true,
                    },
                },
                {
                    name: 'isCloseout',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$t('sw-bulk-edit.blog.deliverability.isCloseout.switchLabel'),
                        changeLabel: this.$t('sw-bulk-edit.blog.deliverability.isCloseout.changeLabel'),
                        disabled: this.bulkEditProduct?.isCloseout?.isInherited,
                    },
                },
                {
                    name: 'deliveryTimeId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'delivery_time',
                        changeLabel: this.$t('sw-bulk-edit.blog.deliverability.deliveryTime.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.deliverability.deliveryTime.placeholderDeliveryTime'),
                        disabled: this.bulkEditProduct?.deliveryTimeId?.isInherited,
                    },
                },
                {
                    name: 'restockTime',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.deliverability.restockTime.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.deliverability.restockTime.placeholderRestockTime'),
                        numberType: 'int',
                        allowEmpty: true,
                        allowOverwrite: true,
                        allowClear: true,
                        min: 0,
                        disabled: this.bulkEditProduct?.restockTime?.isInherited,
                    },
                },
                {
                    name: 'shippingFree',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$t('sw-bulk-edit.blog.deliverability.freeShipping.switchLabel'),
                        changeLabel: this.$t('sw-bulk-edit.blog.deliverability.freeShipping.changeLabel'),
                        disabled: this.bulkEditProduct?.shippingFree?.isInherited,
                    },
                },
                {
                    name: 'minPurchase',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.deliverability.minOrderQuantity.changeLabel'),
                        placeholder: this.$t(
                            'sw-bulk-edit.blog.deliverability.minOrderQuantity.placeholderMinOrderQuantity',
                        ),
                        numberType: 'int',
                        allowOverwrite: true,
                        allowClear: true,
                        allowEmpty: true,
                        min: 1,
                        disabled: this.bulkEditProduct?.minPurchase?.isInherited,
                    },
                },
                {
                    name: 'purchaseSteps',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.deliverability.purchaseSteps.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.deliverability.purchaseSteps.placeholderPurchaseSteps'),
                        numberType: 'int',
                        allowOverwrite: true,
                        allowClear: true,
                        allowEmpty: true,
                        min: 1,
                        disabled: this.bulkEditProduct?.purchaseSteps?.isInherited,
                    },
                },
                {
                    name: 'maxPurchase',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.deliverability.maxOrderQuantity.changeLabel'),
                        placeholder: this.$t(
                            'sw-bulk-edit.blog.deliverability.maxOrderQuantity.placeholderMaxOrderQuantity',
                        ),
                        numberType: 'int',
                        allowOverwrite: true,
                        allowClear: true,
                        allowEmpty: true,
                        min: 0,
                        disabled: this.bulkEditProduct?.maxPurchase?.isInherited,
                    },
                },
            ];

            return fields.filter((field) => {
                return !this.restrictedFields.includes(field.name);
            });
        },

        assignmentFormFields() {
            return [
                {
                    name: 'visibilities',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-bulk-edit-blog-visibility',
                        bulkEditProduct: this.bulkEditProduct,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$t('sw-bulk-edit.blog.assignment.visibilities.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.assignment.visibilities.placeholder'),
                        disabled: this.bulkEditProduct?.visibilities?.isInherited,
                    },
                },
                {
                    name: 'categories',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-category-tree-field',
                        categoriesCollection: this.blog?.categories,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$t('sw-bulk-edit.blog.assignment.categories.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.assignment.categories.placeholder'),
                        disabled: this.bulkEditProduct?.categories?.isInherited,
                    },
                },
                {
                    name: 'tags',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-tag-select',
                        entityCollection: this.blog?.tags,
                        entityName: 'tag',
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$t('sw-bulk-edit.blog.assignment.tags.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.assignment.tags.placeholder'),
                        disabled: this.bulkEditProduct?.tags?.isInherited,
                    },
                },
                {
                    name: 'searchKeywords',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-multi-tag-select',
                        value: this.blog?.searchKeywords,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: false,
                        allowRemove: false,
                        changeLabel: this.$t('sw-bulk-edit.blog.assignment.searchKeywords.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.assignment.searchKeywords.placeholder'),
                        disabled: this.bulkEditProduct?.searchKeywords?.isInherited,
                    },
                },
            ];
        },

        mediaFormFields() {
            return [
                {
                    name: 'media',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-bulk-edit-blog-media',
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        changeLabel: this.$t('sw-bulk-edit.blog.media.changeLabel'),
                        disabled: this.bulkEditProduct?.media?.isInherited,
                    },
                },
            ];
        },

        labellingFormFields() {
            return [
                {
                    name: 'releaseDate',
                    type: 'datetime',
                    canInherit: this.isChild,
                    config: {
                        type: 'date',
                        dateType: 'datetime-local',
                        changeLabel: this.$t('sw-bulk-edit.blog.labelling.releaseDate.changeLabel'),
                        disabled: this.bulkEditProduct?.releaseDate?.isInherited,
                    },
                },
            ];
        },

        guaranteeFormFields() {
            return [
                {
                    name: 'guaranteeMonths',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        numberType: 'int',
                        min: 30,
                        max: 600,
                        step: 6,
                        allowEmpty: true,
                        changeLabel: this.$t('sw-bulk-edit.blog.guarantee.guaranteeMonths.changeLabel'),
                        helpText: this.$t('sw-bulk-edit.blog.guarantee.guaranteeMonths.helpText'),
                        placeholder: this.$t('sw-bulk-edit.blog.guarantee.guaranteeMonths.placeholder'),
                        disabled: this.bulkEditProduct?.guaranteeMonths?.isInherited,
                    },
                },
                {
                    name: 'guaranteeConfirmed',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$t('sw-bulk-edit.blog.guarantee.guaranteeConfirmed.label'),
                        helpText: this.$t('sw-bulk-edit.blog.guarantee.guaranteeConfirmed.helpText'),
                        changeLabel: this.$t('sw-bulk-edit.blog.guarantee.guaranteeConfirmed.changeLabel'),
                        disabled: this.bulkEditProduct?.guaranteeConfirmed?.isInherited,
                    },
                },
                {
                    name: 'guaranteeTermsMediaId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-media-field',
                        fileAccept: 'application/pdf',
                        defaultFolder: 'blog',
                        changeLabel: this.$t('sw-bulk-edit.blog.guarantee.guaranteeTermsMedia.changeLabel'),
                        disabled: this.bulkEditProduct?.guaranteeTermsMediaId?.isInherited,
                    },
                },
                {
                    name: 'guaranteeTermsUrl',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-text-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.guarantee.guaranteeTermsUrl.changeLabel'),
                        placeholder: 'https://',
                        disabled: this.bulkEditProduct?.guaranteeTermsUrl?.isInherited,
                    },
                },
            ];
        },

        seoFormFields() {
            return [
                {
                    name: 'metaTitle',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-text-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.seo.metaTitle.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.seo.metaTitle.placeholderMetaTitle'),
                        disabled: this.bulkEditProduct?.metaTitle?.isInherited,
                    },
                },
                {
                    name: 'metaDescription',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-textarea',
                        changeLabel: this.$t('sw-bulk-edit.blog.seo.metaDescription.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.seo.metaDescription.placeholderMetaDescription'),
                        disabled: this.bulkEditProduct?.metaDescription?.isInherited,
                    },
                },
                {
                    name: 'keywords',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-text-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.seo.seoKeywords.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.seo.seoKeywords.placeholderSeoKeywords'),
                        disabled: this.bulkEditProduct?.keywords?.isInherited,
                    },
                },
            ];
        },

        measuresPackagingFields() {
            return [
                {
                    name: 'width',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-unit-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.measurements.width.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.measurements.width.placeholder'),
                        numberType: 'float',
                        defaultUnit: this.lengthUnit,
                        measurementType: 'length',
                        fieldName: 'width',
                        digits: 3,
                        min: 0,
                        disabled: this.bulkEditProduct?.width?.isInherited,
                    },
                },
                {
                    name: 'height',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-unit-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.measurements.height.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.measurements.height.placeholder'),
                        numberType: 'float',
                        defaultUnit: this.lengthUnit,
                        measurementType: 'length',
                        fieldName: 'height',
                        digits: 3,
                        min: 0,
                        disabled: this.bulkEditProduct?.height?.isInherited,
                    },
                },
                {
                    name: 'length',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-unit-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.measurements.length.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.measurements.length.placeholder'),
                        numberType: 'float',
                        defaultUnit: this.lengthUnit,
                        measurementType: 'length',
                        fieldName: 'length',
                        digits: 3,
                        min: 0,
                        disabled: this.bulkEditProduct?.length?.isInherited,
                    },
                },
                {
                    name: 'weight',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-unit-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.measurements.weight.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.measurements.weight.placeholder'),
                        numberType: 'float',
                        defaultUnit: this.weightUnit,
                        measurementType: 'mass',
                        fieldName: 'weight',
                        digits: 3,
                        min: 0,
                        disabled: this.bulkEditProduct?.weight?.isInherited,
                    },
                },
            ];
        },

        sellingPackagingFields() {
            return [
                {
                    name: 'purchaseUnit',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        numberType: 'float',
                        min: 0,
                        digits: 3,
                        changeLabel: this.$t('sw-bulk-edit.blog.sellingPackaging.purchaseUnit.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.sellingPackaging.purchaseUnit.placeholder'),
                        disabled: this.bulkEditProduct?.purchaseUnit?.isInherited,
                    },
                },
                {
                    name: 'unitId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'unit',
                        changeLabel: this.$t('sw-bulk-edit.blog.sellingPackaging.unitId.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.sellingPackaging.unitId.placeholder'),
                        disabled: this.bulkEditProduct?.unitId?.isInherited,
                    },
                },
                {
                    name: 'referenceUnit',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        numberType: 'float',
                        min: 0,
                        digits: 3,
                        changeLabel: this.$t('sw-bulk-edit.blog.sellingPackaging.referenceUnit.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.sellingPackaging.referenceUnit.placeholder'),
                        disabled: this.bulkEditProduct?.referenceUnit?.isInherited,
                    },
                },
                {
                    name: 'packUnit',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-text-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.sellingPackaging.packUnit.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.sellingPackaging.packUnit.placeholder'),
                        disabled: this.bulkEditProduct?.packUnit?.isInherited,
                    },
                },
                {
                    name: 'packUnitPlural',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-text-field',
                        changeLabel: this.$t('sw-bulk-edit.blog.sellingPackaging.packUnitPlural.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.sellingPackaging.packUnitPlural.placeholder'),
                        disabled: this.bulkEditProduct?.packUnitPlural?.isInherited,
                    },
                },
            ];
        },

        essentialCharacteristicsFormFields() {
            return [
                {
                    name: 'featureSetId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'blog_feature_set',
                        changeLabel: this.$t('sw-bulk-edit.blog.featureSets.changeLabel'),
                        placeholder: this.$t('sw-bulk-edit.blog.featureSets.placeholder'),
                        disabled: this.bulkEditProduct?.featureSetId?.isInherited,
                    },
                },
            ];
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        priceRepository() {
            if (!this.blog?.prices) {
                return null;
            }

            return this.repositoryFactory.create(this.blog?.prices.entity, this.blog?.prices.source);
        },

        ruleCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.contains('rule.moduleTypes.types', 'price'),
                    Criteria.equals('rule.moduleTypes', null),
                ]),
            );

            return criteria;
        },

        priceRuleGroups() {
            if (!this.blog?.prices) {
                return {};
            }

            return this.blog?.prices.reduce((r, a) => {
                r[a.ruleId] = [...(r[a.ruleId] || []), a];
                return r;
            }, {});
        },

        selectedPriceRules() {
            const collection = new EntityCollection(this.ruleRepository.route, this.ruleRepository.entityName, Context.api);

            if (!this.blog?.prices?.length) {
                return collection;
            }

            const seen = new Set();
            this.blog.prices.forEach((price) => {
                if (!price.ruleId || seen.has(price.ruleId)) {
                    return;
                }
                seen.add(price.ruleId);

                const rule = this.rules?.find?.((r) => r.id === price.ruleId) ?? price.rule;
                if (rule) {
                    collection.push(rule);
                    return;
                }

                collection.push({
                    id: price.ruleId,
                    name: price.ruleName,
                    ruleName: price.ruleName,
                });
            });

            return collection;
        },

        hasPreferenceUnitsChanged() {
            return this.preferenceUnits.length !== this.lengthUnit || this.preferenceUnits.weight !== this.weightUnit;
        },
    },

    watch: {
        'bulkEditProduct.prices.type'(type) {
            if (!this.blog?.prices?.length || type !== 'remove') {
                return;
            }

            const ids = this.blog?.prices?.getIds();
            ids.forEach((id) => this.blog?.prices.remove(id));
        },
        'blog.visibilities': {
            handler(blogVisibilities) {
                if (!this.isChild) {
                    return;
                }

                this.bulkEditProduct.visibilities.value = blogVisibilities;
            },
        },
        'bulkEditProduct.isPriceInherited.isChanged': {
            handler(isChanged) {
                if (!this.isChild) {
                    return;
                }

                this.bulkEditProduct.price.isChanged = isChanged;
                this.bulkEditProduct.purchasePrices.isChanged = isChanged;
                this.bulkEditProduct.listPrice.isChanged = isChanged;
                this.bulkEditProduct.regulationPrice.isChanged = isChanged;
            },
        },
        'bulkEditProduct.isPriceInherited.isInherited': {
            handler(isInherited) {
                if (!this.isChild) {
                    return;
                }

                this.bulkEditProduct.price.isInherited = isInherited;
                this.bulkEditProduct.purchasePrices.isInherited = isInherited;
                this.bulkEditProduct.listPrice.isInherited = isInherited;
                this.bulkEditProduct.regulationPrice.isInherited = isInherited;
            },
        },
        'blog.listPrice': {
            deep: true,
            handler(listPrice) {
                if (!this.isChild) {
                    return;
                }
                if (!this.bulkEditProduct?.price?.value?.length) {
                    return;
                }
                if (this.bulkEditProduct?.price?.value[0]?.listPrice) {
                    return;
                }

                this.bulkEditProduct.price.value[0].listPrice = listPrice[0];
            },
        },
        'blog.regulationPrice': {
            deep: true,
            handler(regulationPrice) {
                if (!this.isChild) {
                    return;
                }
                if (!this.bulkEditProduct?.price?.value?.length) {
                    return;
                }
                if (this.bulkEditProduct?.price?.value[0]?.regulationPrice) {
                    return;
                }

                this.bulkEditProduct.price.value[0].regulationPrice = regulationPrice[0];
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.setRouteMetaModule();
            this.isLoading = true;

            if (this.isChild) {
                await this.getParentProduct();
            }

            const promises = [
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadCustomFieldSets(),
                this.loadDefaultCurrency(),
                this.loadRules(),
                this.loadPreferenceUnits(),
            ];

            Promise.all(promises).then(() => {
                const blog = this.isChild ? this.parentProduct : this.blogRepository.create();
                Contena.Store.get('swProductDetail').blog = blog;

                this.loadBulkEditData();
                this.setDefaultBooleanProductValues();
                this.definePricesBulkEdit();

                if (this.isChild) {
                    this.setBulkEditProductValue();
                }

                this.isLoading = false;
                this.isLoadedData = true;
            });
        },

        setRouteMetaModule() {
            this.$route.meta.$module.color = 'var(--sw-color-module-green-default)';
            this.$route.meta.$module.icon = 'regular-blogs';
        },

        setBulkEditProductValue() {
            Object.keys(this.bulkEditProduct).forEach((key) => {
                this.bulkEditProduct[key].value = cloneDeep(this.parentProduct?.[key]);

                if (key === 'searchKeywords') {
                    this.setProductSearchKeywords();
                }
            });
        },

        getParentProduct() {
            return this.blogRepository
                .get(this.$route.params.parentId, Contena.Context.api, this.blogCriteria)
                .then((parentProduct) => {
                    parentProduct.stock = null;
                    Contena.Store.get('swProductDetail').parentProduct = parentProduct;
                    this.parentProductFrozen = JSON.stringify(parentProduct);
                })
                .catch(() => {
                    Contena.Store.get('swProductDetail').parentProduct = {};
                });
        },

        loadDefaultCurrency() {
            return this.currencyRepository.get(Context.app.systemCurrencyId).then((currency) => {
                this.currency = currency;
            });
        },

        defineBulkEditData(name, value = null, type = 'overwrite', isChanged = false) {
            if (this.bulkEditProduct[name]) {
                return;
            }

            if (name === 'stock') {
                this.bulkEditProduct[name] = {
                    value,
                    type,
                    isChanged,
                    isInherited: false,
                };
                return;
            }

            this.bulkEditProduct[name] = {
                isChanged: isChanged,
                type: type,
                value: value,
                isInherited: this.isChild,
            };
        },

        loadBulkEditData() {
            this.getBulkEditFormGroups().forEach((bulkEditForms) => {
                bulkEditForms.forEach((bulkEditForm) => {
                    this.defineBulkEditData(bulkEditForm.name);
                });
            });
        },

        getBulkEditFormGroups() {
            return [
                this.generalFormFields,
                this.deliverabilityFormFields,
                this.pricesFormFields,
                this.advancedPricesFormFields,
                this.propertyFormFields,
                this.assignmentFormFields,
                this.mediaFormFields,
                this.labellingFormFields,
                this.guaranteeFormFields,
                this.seoFormFields,
                this.measuresPackagingFields,
                this.sellingPackagingFields,
                this.essentialCharacteristicsFormFields,
            ];
        },

        setDefaultBooleanProductValues() {
            if (this.isChild) {
                return;
            }

            this.getBulkEditFormGroups().forEach((bulkEditForms) => {
                bulkEditForms.forEach((bulkEditForm) => {
                    if (bulkEditForm.type !== 'bool') {
                        return;
                    }

                    this.blog[bulkEditForm.name] ??= false;
                    this.bulkEditProduct[bulkEditForm.name].value ??= false;
                });
            });
        },

        loadCustomFieldSets() {
            return this.customFieldDataProviderService.getCustomFieldSets('blog', false, null).then((res) => {
                this.customFieldSets = res;
            });
        },

        loadTaxes() {
            return this.taxRepository.search(this.taxCriteria).then((taxes) => {
                this.taxRate = this.isChild ? this.parentProduct?.tax : taxes[0];
                Contena.Store.get('swProductDetail').setTaxes(taxes);
            });
        },

        blogTaxRate() {
            if (!this.taxes) {
                return {};
            }

            return this.taxes.find((tax) => {
                return tax.id === this.blog?.taxId;
            });
        },

        loadCurrencies() {
            return this.currencyRepository.search(new Criteria(1, 500)).then((res) => {
                Contena.Store.get('swProductDetail').currencies = res;
            });
        },

        definePricesBulkEdit() {
            if (!this.isComponentMounted) {
                return;
            }

            if (this.isChild && !types.isEmpty(this.parentProduct)) {
                this.blog.price = this.parentProduct.price;
                this.blog.purchasePrices = this.parentProduct.purchasePrices;
                this.setProductPrice('listPrice');
                this.setProductPrice('regulationPrice');

                return;
            }

            this.blog.price ??= [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];

            this.blog.purchasePrices ??= [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];

            this.blog.listPrice = [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];

            this.blog.regulationPrice = [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];
        },

        setProductPrice(price) {
            const emptyPrice = [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];

            if (!types.isEmpty(this.parentProduct.price?.[0][price])) {
                this.blog[price] = [this.parentProduct.price[0][price]];
            } else {
                this.blog[price] = emptyPrice;
            }
        },

        onChangePrices(item, value = null) {
            if (item === 'taxId') {
                this.taxRate = this.blogTaxRate();
                return;
            }

            if (value && typeof value !== 'boolean') {
                this.blog[item] = Array.isArray(value) ? value : [value];
            }
        },

        onCustomFieldsChange(value) {
            if (Object.keys(value).length <= 0) {
                this.bulkEditSelected = this.bulkEditSelected.filter((change) => change.field !== 'customFields');
                return;
            }

            const change = {
                field: 'customFields',
                type: 'overwrite',
                value: value,
            };

            this.bulkEditSelected.push(change);
        },

        onProcessData() {
            let hasListPrice = false;
            let hasRegulationPrice = false;
            let hasPriceChange = false;

            Object.keys(this.bulkEditProduct).forEach((key) => {
                const bulkEditField = cloneDeep(this.bulkEditProduct[key]);
                if (!bulkEditField.isChanged) {
                    return;
                }

                if (key === 'listPrice') {
                    hasListPrice = true;
                    hasPriceChange = true;

                    return;
                }

                if (key === 'regulationPrice') {
                    hasRegulationPrice = true;
                    hasPriceChange = true;

                    return;
                }

                if (['price', 'purchasePrices'].includes(key)) {
                    hasPriceChange = true;
                }

                let bulkEditValue = this.blog[key];

                if (
                    [
                        'minPurchase',
                        'maxPurchase',
                        'purchaseSteps',
                        'restockTime',
                    ].includes(key) &&
                    bulkEditField.type === 'clear'
                ) {
                    bulkEditValue = null;
                    bulkEditField.type = 'overwrite';
                } else if (key === 'searchKeywords') {
                    key = 'customSearchKeywords';
                }

                if (bulkEditField.isInherited) {
                    bulkEditValue = null;
                }

                const change = {
                    field: key,
                    type: bulkEditField.type,
                    value: bulkEditValue,
                };

                if (key === 'visibilities') {
                    change.mappingReferenceField = 'channelId';
                } else if (key === 'media') {
                    change.mappingReferenceField = 'mediaId';
                } else if (key === 'prices') {
                    change.mappingReferenceField = 'ruleId';
                }

                // Variants inherit the parent's visibilities all-or-nothing: they own no
                // `blog_visibility` rows until they override. A plain ADD/REMOVE bulk
                // edit therefore either persists nothing (REMOVE finds no own rows) or
                // drops the whole inherited set (ADD materializes only the added channel).
                // We instead route the change through a dedicated handler path that rebuilds
                // each variant's effective set (its own rows when it overrides, otherwise the
                // inherited parent set) with the removed channels dropped and the added ones
                // merged in.
                if (this.isChild && key === 'visibilities' && ['add', 'remove'].includes(bulkEditField.type)) {
                    this.transformVariantVisibilityChange(change);
                }

                if (this.isChild && change.value !== null && types.isArray(change.value)) {
                    change.value.forEach((association) => {
                        delete association.id;
                    });
                }

                this.bulkEditSelected.push(change);
            });

            if (hasPriceChange && !this.bulkEditProduct.taxId?.isChanged && this.taxRate?.id) {
                this.bulkEditSelected.push({
                    field: 'taxId',
                    type: 'overwrite',
                    value: this.taxRate.id,
                });
            }

            if (hasListPrice) {
                this.processListPrice();
            }

            if (hasRegulationPrice) {
                this.processRegulationPrice();
            }
        },

        transformVariantVisibilityChange(change) {
            // Always flag the change so the bulk-edit handler routes it through the
            // per-variant path and never falls back to the generic ADD/REMOVE flow
            // (which ignores the inherited set the variant does not own).
            change.removedSalesChannelIds = [];
            change.addedVisibilities = [];
            change.inheritedVisibilities = [];

            // The selector holds the sales channels to add or to remove (depending on the
            // change type). When the field is left inherited the value is null and there
            // is nothing to do.
            const selectedVisibilities = Array.isArray(change.value) ? change.value : [];

            if (change.type === 'remove') {
                change.removedSalesChannelIds = selectedVisibilities
                    .map((visibility) => visibility?.channelId)
                    .filter(Boolean);
            } else {
                change.addedVisibilities = selectedVisibilities
                    .filter((visibility) => visibility?.channelId)
                    .map((visibility) => ({
                        channelId: visibility.channelId,
                        visibility: visibility.visibility,
                    }));
            }

            // The parent's inherited set is the fallback base for variants that do not
            // override visibilities; the handler needs the `visibility` value to recreate
            // the rows when materializing the effective set.
            if (this.parentProductFrozen) {
                const parentProduct = JSON.parse(this.parentProductFrozen);
                const parentVisibilities = Array.isArray(parentProduct?.visibilities) ? parentProduct.visibilities : [];

                change.inheritedVisibilities = parentVisibilities.map((visibility) => ({
                    channelId: visibility.channelId,
                    visibility: visibility.visibility,
                }));
            }
        },

        processListPrice() {
            const priceField = this.bulkEditSelected.find((dataField) => {
                return dataField.field === 'price';
            });

            if (priceField?.value === null) {
                return;
            }

            if (priceField) {
                priceField.value[0].listPrice = this.blog?.listPrice[0];
            } else {
                // Add price change when only listPrice is changed - price payload is required for API
                this.bulkEditSelected.push({
                    field: 'price',
                    type: 'overwrite',
                    value: [
                        {
                            currencyId: this.currency.id,
                            gross: null,
                            net: null,
                            linked: true,
                            listPrice: this.blog?.listPrice[0],
                            regulationPrice: null,
                        },
                    ],
                });
            }
        },

        processRegulationPrice() {
            const priceField = this.bulkEditSelected.find((dataField) => {
                return dataField.field === 'price';
            });

            if (priceField?.value === null) {
                return;
            }

            if (priceField) {
                priceField.value[0].regulationPrice = this.blog?.regulationPrice[0];
            } else {
                // Add price change when only regulationPrice is changed - price payload is required for API
                this.bulkEditSelected.push({
                    field: 'price',
                    type: 'overwrite',
                    value: [
                        {
                            currencyId: this.currency.id,
                            gross: null,
                            net: null,
                            linked: true,
                            listPrice: null,
                            regulationPrice: this.blog?.regulationPrice[0],
                        },
                    ],
                });
            }
        },

        openModal() {
            this.isComponentMounted = false;
            this.$router.push({
                name: 'sw.bulk.edit.blog.save',
                params: {
                    parentId: this.$route.params.parentId,
                },
            });
        },

        async onSave() {
            this.isLoading = true;
            this.onProcessData();

            const payloadChunks = chunk(this.selectedIds, 50);

            const context = {
                'sw-measurement-length-unit': this.lengthUnit,
                'sw-measurement-weight-unit': this.weightUnit,
            };

            const requests = payloadChunks.map((payload) => {
                return this.bulkEditApiFactory.getHandler('blog').bulkEdit(payload, this.bulkEditSelected, context);
            });

            this.bulkEditSelected = [];

            await this.savePreferenceUnits();

            return Promise.all(requests)
                .then((response) => {
                    const isSuccessful = response.every((item) => item.data);
                    this.processStatus = isSuccessful ? 'success' : 'fail';
                })
                .catch(() => {
                    this.processStatus = 'fail';
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        savePreferenceUnits() {
            if (!this.hasPreferenceUnitsChanged) {
                return Promise.resolve();
            }

            return Contena.Service('admin_userConfigService').upsert({
                'measurement.preferenceUnits': {
                    length: this.lengthUnit,
                    weight: this.weightUnit,
                },
            });
        },

        closeModal() {
            this.$router.push({ name: 'sw.bulk.edit.blog' });
        },

        onChangeLanguage(languageId) {
            Contena.Store.get('context').setApiLanguageId(languageId);
        },

        loadRules() {
            return this.ruleRepository.search(this.ruleCriteria).then((res) => {
                this.rules = res;
            });
        },

        async loadPreferenceUnits() {
            const preferenceUnits = (await Contena.Service('admin_userConfigService').search(['measurement.preferenceUnits']))
                ?.data?.['measurement.preferenceUnits'] || {
                length: 'mm',
                weight: 'kg',
            };

            this.preferenceUnits = preferenceUnits;
            this.lengthUnit = preferenceUnits.length;
            this.weightUnit = preferenceUnits.weight;
        },

        onRuleChange(rules) {
            if (!this.blog?.prices) {
                return;
            }

            const selectedRuleIds = new Set(rules.map((rule) => rule.id));
            const existingRuleIds = new Set(this.blog.prices.map((price) => price.ruleId));

            // Add price entries for newly selected rules
            rules.forEach((rule) => {
                if (existingRuleIds.has(rule.id)) {
                    return;
                }

                this.blog.prices.add(this.createPriceRuleEntry(rule));
            });

            // Remove price entries for rules that are no longer selected
            this.blog.prices.getIds().forEach((priceId) => {
                const price = this.blog.prices.get(priceId);
                if (!price || selectedRuleIds.has(price.ruleId)) {
                    return;
                }

                this.blog.prices.remove(priceId);
            });
        },

        createPriceRuleEntry(rule) {
            const newPriceRule = this.priceRepository.create();

            newPriceRule.blogId = this.blog?.id;
            newPriceRule.quantityStart = 1;
            newPriceRule.quantityEnd = null;
            newPriceRule.currencyId = this.defaultCurrency.id;
            newPriceRule.ruleId = rule.id;
            newPriceRule.ruleName = rule.name;
            newPriceRule.price = [
                {
                    currencyId: this.defaultCurrency.id,
                    gross: 0,
                    linked: this.defaultPrice.linked,
                    net: 0,
                    listPrice: null,
                    regulationPrice: null,
                },
            ];

            if (this.defaultPrice.listPrice) {
                newPriceRule.price[0].listPrice = {
                    currencyId: this.defaultCurrency.id,
                    gross: this.defaultPrice.listPrice.gross,
                    linked: this.defaultPrice.listPrice.linked,
                    net: this.defaultPrice.listPrice.net,
                };
            }

            if (this.defaultPrice.regulationPrice) {
                newPriceRule.price[0].regulationPrice = {
                    currencyId: this.defaultCurrency.id,
                    gross: this.defaultPrice.regulationPrice.gross,
                    linked: this.defaultPrice.regulationPrice.linked,
                    net: this.defaultPrice.regulationPrice.net,
                };
            }

            return newPriceRule;
        },

        onInheritanceRestore(item) {
            const parentProductFrozen = JSON.parse(this.parentProductFrozen);

            this.bulkEditProduct[item.name].isInherited = true;
            this.bulkEditProduct[item.name].value = parentProductFrozen[item.name];

            if (item.name === 'taxId') {
                this.taxRate = parentProductFrozen.tax;

                this.blog.taxId = parentProductFrozen.taxId;
                return;
            }
            if (item.name === 'isPriceInherited') {
                this.blog.price[0] = parentProductFrozen.price[0];
                this.blog.purchasePrices[0] = parentProductFrozen.purchasePrices[0];

                const listPrice = !types.isEmpty(parentProductFrozen.price[0].listPrice)
                    ? parentProductFrozen.price[0].listPrice
                    : {
                          currencyId: this.currency.id,
                          net: null,
                          linked: true,
                          gross: null,
                      };
                this.blog.listPrice = [listPrice];
                this.blog.price[0].listPrice = listPrice;

                const regulationPrice = !types.isEmpty(parentProductFrozen.price[0].regulationPrice)
                    ? parentProductFrozen.price[0].regulationPrice
                    : {
                          currencyId: this.currency.id,
                          net: null,
                          linked: true,
                          gross: null,
                      };
                this.blog.regulationPrice = [regulationPrice];
                this.blog.price[0].regulationPrice = regulationPrice;

                return;
            }
            if (item.name === 'categories') {
                this.setProductAssociation(item.name, parentProductFrozen);
                return;
            }
            if (item.name === 'media') {
                this.blog.media = parentProductFrozen.media;
                return;
            }
            if (item.name === 'prices') {
                this.setProductAssociation(item.name, parentProductFrozen);
                return;
            }
            if (item.name === 'properties') {
                this.setProductAssociation(item.name, parentProductFrozen);
                return;
            }
            if (item.name === 'searchKeywords') {
                this.setProductSearchKeywords();
                return;
            }

            this.blog[item.name] = parentProductFrozen[item.name];
        },

        onInheritanceRemove(item) {
            if (['properties', 'prices'].includes(item.name)) {
                this.setProductAssociation(item.name);
            }

            this.bulkEditProduct[item.name].isInherited = false;
        },

        setProductSearchKeywords() {
            if (types.isEmpty(this.parentProduct?.customSearchKeywords)) {
                this.bulkEditProduct.searchKeywords.value = [];
                this.blog.searchKeywords = [];

                return;
            }

            this.bulkEditProduct.searchKeywords.value = this.parentProduct.customSearchKeywords;
            this.blog.searchKeywords = this.parentProduct.customSearchKeywords;
        },

        setProductAssociation(entityName, parentProduct = null) {
            const ids = this.blog[entityName].getIds();
            ids.forEach((id) => this.blog[entityName].remove(id));

            if (!parentProduct) {
                this.bulkEditProduct[entityName].value = [];
                return;
            }

            parentProduct[entityName].forEach((item) => this.blog[entityName].add(item));
        },

        onUpdateDefaultUnit(event) {
            if (event.config.fieldName === 'width') {
                this.convertHeight(event.unit);
                this.convertLength(event.unit);
            }

            if (event.config.fieldName === 'height') {
                this.convertWidth(event.unit);
                this.convertLength(event.unit);
            }

            if (event.config.fieldName === 'length') {
                this.convertWidth(event.unit);
                this.convertHeight(event.unit);
            }

            if (event.config.measurementType === 'length') {
                this.lengthUnit = event.unit;
            } else {
                this.weightUnit = event.unit;
            }
        },

        convertWidth(unit) {
            if (!this.blog.width) {
                return;
            }

            this.blog.width = convert(this.blog.width, this.lengthUnit, unit);
        },

        convertHeight(unit) {
            if (!this.blog.height) {
                return;
            }

            this.blog.height = convert(this.blog.height, this.lengthUnit, unit);
        },

        convertLength(unit) {
            if (!this.blog.length) {
                return;
            }

            this.blog.length = convert(this.blog.length, this.lengthUnit, unit);
        },
    },
};
