import template from './sw-mixin-unmapped.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Contena.Mixin.getByName('salutation'),
    ],

    data() {
        return {
            user: null,
        };
    },

    methods: {
        // `salutationFilter` is the mixin's own computed; the composable inlines it and returns only
        // the formatting helper, so this read has nothing to resolve against after the migration.
        formatShort() {
            return this.salutationFilter(this.user, '');
        },
    },
};
