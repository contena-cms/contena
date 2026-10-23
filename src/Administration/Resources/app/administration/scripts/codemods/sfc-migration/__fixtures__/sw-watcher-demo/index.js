import template from './sw-watcher-demo.html.twig';

export default {
    template,

    props: {
        blogId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            term: '',
            config: { limit: 25 },
        };
    },

    watch: {
        term(newTerm, oldTerm) {
            this.onTermChange(newTerm, oldTerm);
        },

        blogId: {
            handler() {
                this.loadProduct();
            },
            immediate: true,
        },

        'config.limit': {
            handler: 'loadProduct',
            deep: true,
        },

        $route() {
            this.term = '';
        },
    },

    methods: {
        onTermChange() {},

        loadProduct() {},
    },
};
