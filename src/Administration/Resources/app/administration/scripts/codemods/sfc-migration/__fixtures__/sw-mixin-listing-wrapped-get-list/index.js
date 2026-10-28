import template from './sw-mixin-listing-wrapped-get-list.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Contena.Mixin.getByName('listing'),
    ],

    data() {
        return {
            items: null,
        };
    },

    methods: {
        // A decorated method: the codemod has no function body it could hand over.
        getList: Contena.Utils.debounce(function getList() {
            this.repositoryFactory
                .create('product')
                .search(new Contena.Data.Criteria(this.page, this.limit), Contena.Context.api)
                .then((result) => {
                    this.items = result;
                    this.total = result.total;
                });
        }, 750),
    },
};
