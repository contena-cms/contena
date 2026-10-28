import type { InAppPurchaseRequest } from '../../../store/in-app-purchase-checkout.store';
import template from './sw-in-app-purchase-checkout.html.twig';

/**
 * @sw-package checkout
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Contena.Component.wrapComponentConfig({
    template,

    computed: {
        entry(): InAppPurchaseRequest | null {
            const store = Contena.Store.get('inAppPurchaseCheckout');
            return store.entry;
        },
    },

    methods: {
        closeModal() {
            const store = Contena.Store.get('inAppPurchaseCheckout');
            store.dismiss();
        },
    },
});
