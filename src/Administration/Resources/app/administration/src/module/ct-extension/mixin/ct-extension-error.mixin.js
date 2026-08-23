import { defineComponent } from 'vue';

/**
 * @private
 */
export default Contena.Mixin.register(
    'ct-extension-error',
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
