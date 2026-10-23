import BulkEditBaseHandler from './bulk-edit-base.handler';
import RetryHelper from '../../../../core/helper/retry.helper';

const types = Contena.Utils.types;
const { Service, Application } = Contena;
const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;

/**
 * @class
 * @extends BulkEditBaseHandler
 * @sw-package inventory
 */
class BulkEditProductHandler extends BulkEditBaseHandler {
    constructor() {
        super();
        this.name = 'bulkEditProductHandler';
        this.calculatePriceService = Application.getContainer('factory').apiService.getByName('calculate-price');
        this.entityName = 'blog';
        this.entityIds = [];
        this.blogs = {};
    }

    async bulkEdit(entityIds, payload, context) {
        this.entityIds = entityIds;
        const taxId = payload.find((change) => change.field === 'taxId')?.value;
        const price = payload.find((change) => change.field === 'price')?.value;
        const purchasePrices = payload.find((change) => change.field === 'purchasePrices')?.value;
        let updatedPricePayload = [];

        if (taxId || price || purchasePrices) {
            await this.getProducts();
        }

        if (this.shouldRecalculateTax(taxId, price, purchasePrices)) {
            updatedPricePayload = await this.recalculatePrices(taxId, price, purchasePrices);

            payload = payload.filter((change) => change.field !== 'taxId');
        }

        if (price || purchasePrices) {
            updatedPricePayload = this.updatePriceDirectly(price, purchasePrices, updatedPricePayload);

            payload = payload.filter((change) => change.field !== 'price' && change.field !== 'purchasePrices');
        }

        const syncPayload = await this.buildBulkSyncPayload(payload);

        if (updatedPricePayload.length) {
            if (!types.isEmpty(syncPayload) && 'upsert-blog' in syncPayload) {
                syncPayload['upsert-blog'].payload = this.mapProductPricesToSyncPayload(
                    syncPayload['upsert-blog'].payload,
                    updatedPricePayload,
                );
            } else {
                syncPayload['upsert-blog'] = {
                    action: 'upsert',
                    entity: 'blog',
                    payload: updatedPricePayload,
                };
            }
        }

        if (types.isEmpty(syncPayload)) {
            return Promise.resolve({ data: [] });
        }

        return RetryHelper.retry(() => {
            return this.syncService.sync(
                syncPayload,
                {},
                {
                    'single-operation': 1,
                    'sw-language-id': Contena.Context.api.languageId,
                    ...context,
                },
            );
        });
    }

    shouldRecalculateTax(taxId, price, purchasePrices) {
        return taxId && this.isNullPrice(price, purchasePrices);
    }

    isNullPrice(price, purchasePrices) {
        return !price || !price[0].listPrice || !price[0].regulationPrice || !purchasePrices;
    }

    mapProductPricesToSyncPayload(syncPayload, updatePricePayload) {
        const mappedPayload = [];
        syncPayload.forEach((payload) => {
            const pricePayload = updatePricePayload.find((blogPrice) => blogPrice.id === payload.id);

            if (pricePayload) {
                payload = { ...payload, ...pricePayload };
                updatePricePayload = updatePricePayload.filter((blogPrice) => blogPrice.id !== payload.id);
            }

            mappedPayload.push(payload);
        });

        return mappedPayload.concat(updatePricePayload);
    }

    getProducts() {
        const blogRepository = Service('repositoryFactory').create('blog');

        const criteria = new Criteria(1, 25);
        criteria.setIds(this.entityIds);

        return blogRepository.search(criteria, Contena.Context.api).then((blogs) => {
            this.blogs = blogs;
        });
    }

    async recalculatePrices(taxId, inputPrice, inputPurchasePrices) {
        const updatePriceTax = {};
        const updateListPriceTax = {};
        const updateRegulationPriceTax = {};
        const updatePurchasePriceTax = {};

        const blogs = this.blogs.filter((blog) => blog.taxId !== taxId);

        blogs.forEach((blog) => {
            if (!inputPrice) {
                const blogPrice = blog.price
                    ?.filter((price) => price.linked)
                    ?.map((price) => this.getRecalculatePrice(price));

                if (!types.isEmpty(blogPrice)) {
                    updatePriceTax[blog.id] = blogPrice;
                }
            }

            if (!inputPrice || !inputPrice[0].listPrice) {
                const blogListPrice = blog.price
                    ?.filter((price) => price.listPrice?.linked)
                    ?.map((price) => this.getRecalculatePrice(price.listPrice));

                if (!types.isEmpty(blogListPrice)) {
                    updateListPriceTax[blog.id] = blogListPrice;
                }
            }

            if (!inputPrice || !inputPrice[0].regulationPrice) {
                const blogRegulationPrice = blog.price
                    ?.filter((price) => price.regulationPrice?.linked)
                    ?.map((price) => this.getRecalculatePrice(price.regulationPrice));

                if (!types.isEmpty(blogRegulationPrice)) {
                    updateRegulationPriceTax[blog.id] = blogRegulationPrice;
                }
            }

            if (!inputPurchasePrices) {
                const blogPurchasePrice = blog.purchasePrices
                    ?.filter((price) => price.linked)
                    ?.map((price) => this.getRecalculatePrice(price));

                if (!types.isEmpty(blogPurchasePrice)) {
                    updatePurchasePriceTax[blog.id] = blogPurchasePrice;
                }
            }
        });

        const results = await Promise.all([
            this.calculatePrices(taxId, updatePriceTax),
            this.calculatePrices(taxId, updateListPriceTax),
            this.calculatePrices(taxId, updateRegulationPriceTax),
            this.calculatePrices(taxId, updatePurchasePriceTax),
        ]);

        const calculatedPrices = results[0];
        const calculatedListPrices = results[1];
        const calculatedRegulationPrices = results[2];
        const calculatedPurchasePrices = results[3];
        const reformatted = [];

        blogs.forEach((blog) => {
            const calculatedPrice = calculatedPrices[blog.id] ?? [];
            const calculatedListPrice = calculatedListPrices[blog.id] ?? [];
            const calculatedRegulationPrice = calculatedRegulationPrices[blog.id] ?? [];
            const calculatedPurchasePrice = calculatedPurchasePrices[blog.id] ?? [];
            const currentPrice = {
                id: blog.id,
                taxId: taxId,
            };

            if (blog.price) {
                currentPrice.price = this.getCalculatedPrices(
                    blog.price,
                    calculatedPrice,
                    calculatedListPrice,
                    calculatedRegulationPrice,
                );
            }

            if (blog.purchasePrices) {
                currentPrice.purchasePrices = this.getCalculatedPrices(blog.purchasePrices, calculatedPurchasePrice);
            }

            reformatted.push(currentPrice);
        });

        return reformatted;
    }

    getRecalculatePrice(price) {
        return {
            price: price.gross,
            currencyId: price.currencyId,
        };
    }

    async calculatePrices(taxId, prices) {
        if (prices === null || Object.keys(prices).length === 0) {
            return {};
        }

        return this.calculatePriceService.calculatePrices(taxId, prices);
    }

    getCalculatedPrices(dbPrices, calculatedPrices, calculatedListPrices = [], calculatedRegulationPrices = []) {
        const price = [];
        dbPrices.forEach((dbPrice) => {
            const { currencyId, listPrice, regulationPrice } = dbPrice;
            if (dbPrice.linked && calculatedPrices[currencyId]) {
                dbPrice.net = dbPrice.gross - this.getTax(calculatedPrices[currencyId].calculatedTaxes);
            }

            if (listPrice?.linked && calculatedListPrices[currencyId]) {
                dbPrice.listPrice.net = listPrice.gross - this.getTax(calculatedListPrices[currencyId].calculatedTaxes);
            }

            if (regulationPrice?.linked && calculatedRegulationPrices[currencyId]) {
                const regulationPriceTaxes = calculatedRegulationPrices[currencyId].calculatedTaxes;
                dbPrice.regulationPrice.net = regulationPrice.gross - this.getTax(regulationPriceTaxes);
            }

            price.push(dbPrice);
        });

        return price;
    }

    getTax(calculatedTaxes) {
        let tax = 0;

        calculatedTaxes.forEach((item) => {
            tax += item.tax;
        });

        return tax;
    }

    updatePriceDirectly(inputPrice, inputPurchasePrices, calculatedProductPrices) {
        const payload = [];
        this.blogs.forEach((blog) => {
            const calculatedPrice = calculatedProductPrices.find((blogPrice) => blogPrice.id === blog.id);
            const currentData = calculatedPrice ?? { id: blog.id };

            if (inputPrice) {
                const originalPrice = calculatedPrice?.price ?? blog.price;
                currentData.price = this.updatePrice(inputPrice[0], originalPrice);
            }

            if (inputPurchasePrices) {
                currentData.purchasePrices = this.updatePrice(inputPurchasePrices[0], blog.purchasePrices);
            }

            if (calculatedPrice) {
                calculatedProductPrices = calculatedProductPrices.filter((blogPrice) => blogPrice.id !== blog.id);
            }

            payload.push(currentData);
        });

        return payload.concat(calculatedProductPrices);
    }

    updatePrice(inputPrice, dbPrices) {
        const currencyId = inputPrice.currencyId;
        let blogPrices = [];
        const dbPrice = dbPrices?.find((blogPrice) => blogPrice.currencyId === currencyId) ?? null;
        const currentPrice = this.getPrice(inputPrice, dbPrice);

        if (dbPrices) {
            blogPrices = dbPrices.filter((blogPrice) => blogPrice.currencyId !== currencyId);
        }

        blogPrices.push(currentPrice);

        return blogPrices;
    }

    getPrice(inputPrice, dbPrice) {
        const dbListPrice = dbPrice?.listPrice;
        const dbRegulationPrice = dbPrice?.regulationPrice;
        let currentPrice = cloneDeep(inputPrice);
        currentPrice = this.formatPrice(currentPrice, dbPrice);

        // Set listPrice as the input value if exist, otherwise, set listPrice as the old value in the DB
        if (currentPrice.listPrice) {
            currentPrice.listPrice = this.formatPrice(currentPrice.listPrice, dbListPrice);
        } else if (dbListPrice) {
            currentPrice.listPrice = dbListPrice;
        }

        // Set regulationPrice as the input value if exist, otherwise, set regulationPrice as the old value in the DB
        if (currentPrice.regulationPrice) {
            currentPrice.regulationPrice = this.formatPrice(currentPrice.regulationPrice, dbRegulationPrice);
        } else if (dbRegulationPrice) {
            currentPrice.regulationPrice = dbRegulationPrice;
        }

        return currentPrice;
    }

    formatPrice(price, dbPrice) {
        const preserveFromDb = price.gross === null && price.net === null;

        if (price.gross === null) {
            price.linked = false;
            price.gross = dbPrice?.gross ?? 0;
        }

        if (price.net === null) {
            price.linked = false;
            price.net = dbPrice?.net ?? 0;
        }

        // When both gross and net were null (e.g. listPrice/regulationPrice-only bulk edit),
        // preserve base price semantics (linked) from DB to avoid unintended changes
        if (preserveFromDb && dbPrice?.linked !== undefined) {
            price.linked = dbPrice.linked;
        }

        return price;
    }

    /**
     * @private
     *
     * Bulk-adding media must append each image after the blog's own existing media, so the position is
     * computed per blog as the highest existing position + 1, instead of sharing a single value across all
     * selected blogs. Using the highest existing position (not the count) keeps it collision-free even when
     * a blog's positions are non-contiguous, e.g. images at positions 1 and 3 get the new image at 4.
     */
    _getOneToManyRecordDecorator(change, existAssociations) {
        if (change.referenceEntity !== 'blog_media' || change.type !== 'add') {
            return null;
        }

        const nextPositionByEntity = {};
        this.entityIds.forEach((entityId) => {
            nextPositionByEntity[entityId] = this._maxMediaPosition(existAssociations, change.referenceKey, entityId) + 1;
        });

        return (record, entityId) => {
            record.position = nextPositionByEntity[entityId];
            nextPositionByEntity[entityId] += 1;
        };
    }

    /**
     * @private
     *
     * Highest media position currently stored for a blog, derived from the prefetched associations.
     * Returns -1 when the blog has no media yet, so the first appended image starts at position 0.
     */
    _maxMediaPosition(existAssociations, referenceKey, entityId) {
        let maxPosition = -1;

        Object.values(existAssociations).forEach((associations) => {
            associations.forEach((association) => {
                if (association[referenceKey] !== entityId) {
                    return;
                }

                const position = Number.isFinite(association.position) ? association.position : 0;

                if (position > maxPosition) {
                    maxPosition = position;
                }
            });
        });

        return maxPosition;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BulkEditProductHandler;
