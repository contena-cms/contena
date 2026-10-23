import template from './sw-blog-deliverability-downloadable-form.html.twig';
import './sw-blog-deliverability-downloadable-form.scss';

const { Mixin } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

/*
 * @sw-package inventory
 * @private
 */
export default {
    template,

    mixins: [Mixin.getByName('placeholder')],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            enteredStock: null,
            enteredOrderQuantity: null,
            showOrderQuantitySetting: false,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        showModeSetting() {
            return Contena.Store.get('swProductDetail').showModeSetting;
        },

        showStockSetting() {
            if (this.blog.isCloseout !== null || !this.parentProduct?.id) {
                return this.blog.isCloseout;
            }

            return this.parentProduct.isCloseout;
        },

        isLimitedToOneUnit() {
            return (
                (this.getInheritedValue('minPurchase') ?? 1) === 1 &&
                (this.getInheritedValue('purchaseSteps') ?? 1) === 1 &&
                this.getInheritedValue('maxPurchase') === 1
            );
        },

        ...mapPropertyErrors('blog', [
            'stock',
            'deliveryTimeId',
            'isCloseout',
            'maxPurchase',
            'purchaseSteps',
            'minPurchase',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (typeof this.blog.stock === 'undefined') {
                this.blog.stock = 0;
            }

            this.showOrderQuantitySetting = !this.isLimitedToOneUnit;
        },

        onSwitchInput(enabled) {
            if (enabled === false) {
                this.enteredStock = this.blog.stock;
                this.blog.stock = this.blog.getOrigin().stock ?? 0;

                return;
            }

            if (this.enteredStock !== null) {
                this.blog.stock = this.enteredStock;
            }
        },

        onOrderQuantitySwitchInput(enabled) {
            this.showOrderQuantitySetting = enabled;

            if (enabled === false) {
                this.enteredOrderQuantity = this.getOrderQuantity();
                this.setOrderQuantity({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 1 });

                return;
            }

            if (this.enteredOrderQuantity) {
                this.setOrderQuantity(this.enteredOrderQuantity);
            }
        },

        getOrderQuantity() {
            return Contena.Utils.object.pick(this.blog, ['minPurchase', 'purchaseSteps', 'maxPurchase']);
        },

        setOrderQuantity({ minPurchase, purchaseSteps, maxPurchase }) {
            this.blog.minPurchase = minPurchase;
            this.blog.purchaseSteps = purchaseSteps;
            this.blog.maxPurchase = maxPurchase;
        },

        getInheritedValue(field) {
            if (!this.parentProduct?.id) {
                return this.blog[field];
            }

            return this.blog[field] ?? this.parentProduct[field];
        },
    },
};
