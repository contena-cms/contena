import template from './sw-mixin-demo.html.twig';
import swListMixin from './sw-list.mixin';

export default {
    template,

    mixins: [
        Contena.Mixin.getByName('sw-form-field'),
        swListMixin,
    ],

    methods: {
        finish() {
            this.getList();
        },
    },
};
