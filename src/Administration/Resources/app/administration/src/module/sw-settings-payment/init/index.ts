import '../store/overview-cards.store';

/**
 * @sw-package checkout
 */

Contena.ExtensionAPI.handle('uiModulePaymentOverviewCard', (componentConfig) => {
    if (componentConfig.component === 'sw-card') {
        componentConfig.component = 'mt-card';
    }

    Contena.Store.get('paymentOverviewCard').add(componentConfig);
});
