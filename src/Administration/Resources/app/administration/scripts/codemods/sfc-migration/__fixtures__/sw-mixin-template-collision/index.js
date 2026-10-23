import template from './sw-mixin-template-collision.html.twig';
import { salutation } from './salutation.helper';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Contena.Mixin.getByName('salutation'),
    ],

    props: {
        user: {
            type: Object,
            required: true,
        },
    },

    methods: {
        shortLabel() {
            return salutation(this.user);
        },
    },
};
