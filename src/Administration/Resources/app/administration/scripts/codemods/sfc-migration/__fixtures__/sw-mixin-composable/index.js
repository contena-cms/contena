import template from './sw-mixin-composable.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Contena.Mixin.getByName('notification'),
        Contena.Mixin.getByName('salutation'),
    ],

    props: {
        user: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onGreet() {
            this.createNotificationSuccess({ message: 'greeted' });
        },
    },
};
