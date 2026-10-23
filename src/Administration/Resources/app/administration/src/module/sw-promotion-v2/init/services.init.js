/**
 * @sw-package checkout
 */
import PromotionCodeApiService from '../service/promotion-code.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Service().register('promotionCodeApiService', () => {
    return new PromotionCodeApiService(
        Contena.Application.getContainer('init').httpClient,
        Contena.Service('loginService'),
    );
});
