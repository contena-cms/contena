import { defineComponent } from 'vue';

/**
 * @sw-package checkout
 * @private
 */
export default Contena.Mixin.register(
    'sw-extension-error',
    defineComponent({
        mixins: [Contena.Mixin.getByName('notification')],

        methods: {
            showExtensionErrors(errorResponse) {
                Contena.Service('extensionErrorService')
                    .handleErrorResponse(errorResponse, this)
                    .forEach((notification) => {
                        this.createNotificationError(notification);
                    });
            },
        },
    }),
);
