import template from './sw-order-blog-select.html.twig';
import { LineItemType, PriceType } from '../../order.types';
import './sw-order-blog-select.scss';

/**
 * @sw-package checkout
 */

const { Service } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        item: {
            type: Object,
            required: true,
        },

        channelId: {
            type: String,
            required: true,
            default: '',
        },

        taxStatus: {
            type: String,
            required: true,
            default: '',
        },
    },

    data() {
        return {
            blog: null,
        };
    },

    computed: {
        blogRepository() {
            return Service('repositoryFactory').create('blog');
        },

        lineItemTypes() {
            return LineItemType;
        },

        lineItemPriceTypes() {
            return PriceType;
        },

        isShownProductSelect() {
            return this.item._isNew && this.item.type === this.lineItemTypes.BLOG;
        },

        isShownItemLabelInput() {
            return this.item.type !== this.lineItemTypes.BLOG;
        },

        contextWithInheritance() {
            return { ...Contena.Context.api, inheritance: true };
        },

        blogCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('options.group');
            criteria.addAssociation('tax');

            criteria.addFilter(
                Criteria.multi('OR', [Criteria.equals('childCount', 0), Criteria.equals('childCount', null)]),
            );

            criteria.addFilter(Criteria.equals('visibilities.channelId', this.channelId));
            criteria.addFilter(Criteria.equals('active', true));
            criteria.setTotalCountMode(0);

            return criteria;
        },
    },

    methods: {
        onItemChanged(newProductId) {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('tax');

            this.blogRepository.get(newProductId, this.contextWithInheritance, criteria).then((newProduct) => {
                this.item.identifier = newProduct.id;
                this.item.label = newProduct.name;
                this.item.priceDefinition.price =
                    this.taxStatus === 'gross' ? newProduct.price[0].gross : newProduct.price[0].net;
                this.item.priceDefinition.type = this.lineItemPriceTypes.QUANTITY;
                this.item.price.taxRules[0].taxRate = newProduct.tax.taxRate;
                this.item.price.unitPrice = '...';
                this.item.price.totalPrice = '...';
                this.item.price.quantity = 1;
                this.item.unitPrice = '...';
                this.item.totalPrice = '...';
                this.item.precision = 2;
                this.item.priceDefinition.taxRules[0].taxRate = newProduct.tax.taxRate;
            });
        },
    },
};
