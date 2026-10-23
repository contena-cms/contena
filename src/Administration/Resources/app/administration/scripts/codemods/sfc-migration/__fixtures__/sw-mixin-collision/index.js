import template from './sw-mixin-collision.html.twig';
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
        // The module-level helper and the mixin member share a name, which only `this.` keeps apart
        // here — the composable's binding has to be renamed to stay apart from the helper.
        shortLabel() {
            return salutation(this.user);
        },

        fullLabel() {
            return this.salutation(this.user, 'no name');
        },
    },
};
