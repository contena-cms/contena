/*
 * @sw-package inventory
 */
import template from './sw-blog-measurement-form.html.twig';
import './sw-blog-measurement-form.scss';

const { Mixin, Utils } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [Mixin.getByName('placeholder')],

    props: {
        allowEdit: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        lengthUnit() {
            return Contena.Store.get('swProductDetail').lengthUnit;
        },

        weightUnit() {
            return Contena.Store.get('swProductDetail').weightUnit;
        },

        ...mapPropertyErrors('blog', [
            'width',
            'height',
            'length',
            'weight',
        ]),
    },

    methods: {
        onUpdateLengthUnit(unit, type) {
            if (type === 'width') {
                this.convertHeight(unit);
                this.convertLength(unit);
            }

            if (type === 'height') {
                this.convertWidth(unit);
                this.convertLength(unit);
            }

            if (type === 'length') {
                this.convertWidth(unit);
                this.convertHeight(unit);
            }

            Contena.Store.get('swProductDetail').setLengthUnit(unit);
        },

        convertWidth(unit) {
            if (!this.blog.width) {
                return;
            }

            this.blog.width = Utils.unitConversion.convert(this.blog.width, this.lengthUnit, unit);
        },

        convertHeight(unit) {
            if (!this.blog.height) {
                return;
            }

            this.blog.height = Utils.unitConversion.convert(this.blog.height, this.lengthUnit, unit);
        },

        convertLength(unit) {
            if (!this.blog.length) {
                return;
            }

            this.blog.length = Utils.unitConversion.convert(this.blog.length, this.lengthUnit, unit);
        },

        onUpdateWeightUnit(unit) {
            Contena.Store.get('swProductDetail').setWeightUnit(unit);
        },
    },
};
