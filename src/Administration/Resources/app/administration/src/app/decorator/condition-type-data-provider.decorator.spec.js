/**
 * @sw-package fundamentals@after-sales
 */
import { CONDITIONS, ruleConditionTypeDataProvider } from 'src/app/decorator/condition-type-data-provider.decorator';
import RuleConditionService from 'src/app/service/rule-condition.service';

// Guardrail list: Adding or removing a condition is a deliberate change
const EXPECTED_CONDITION_TYPES = [
    'dateRange',
    'timeRange',
    'numberOfReviews',
    'userOrderCount',
    'userDaysSinceLastOrder',
    'channel',
    'currency',
    'language',
    'cartTaxDisplay',
    'userBillingCountry',
    'userBillingStreet',
    'userBillingZipCode',
    'userCustomerGroup',
    'userRequestedGroup',
    'userTag',
    'userCustomerNumber',
    'userDifferentAddresses',
    'userEmail',
    'userLastName',
    'userIsCompany',
    'userIsGuest',
    'userIsNewsletterRecipient',
    'userShippingCountry',
    'userShippingStreet',
    'userShippingZipCode',
    'userLoggedIn',
    'userBillingCity',
    'userBillingState',
    'userIsActive',
    'userShippingCity',
    'userShippingState',
    'userAge',
    'userDaysSinceLastLogin',
    'userDaysSinceFirstLogin',
    'userAffiliateCode',
    'userCampaignCode',
    'orderAffiliateCode',
    'orderCampaignCode',
    'cartCartAmount',
    'cartPositionPrice',
    'cartGoodsCount',
    'cartTotalPurchasePrice',
    'cartGoodsPrice',
    'cartLineItemOfType',
    'cartLineItem',
    'cartLineItemsInCartCount',
    'cartLineItemTotalPrice',
    'cartLineItemUnitPrice',
    'cartLineItemWithQuantity',
    'cartLineItemPerItemQuantity',
    'cartHasDeliveryFreeItem',
    'dayOfWeek',
    'cartWeight',
    'cartVolume',
    'cartShippingCost',
    'cartLineItemTag',
    'alwaysValid',
    'cartLineItemProperty',
    'cartLineItemIsNew',
    'cartLineItemOfManufacturer',
    'cartLineItemPurchasePrice',
    'cartLineItemCreationDate',
    'cartLineItemReleaseDate',
    'cartLineItemClearanceSale',
    'cartLineItemPromoted',
    'cartLineItemInCategory',
    'cartLineItemInProductStream',
    'cartLineItemTaxation',
    'cartLineItemDimensionWidth',
    'cartLineItemDimensionHeight',
    'cartLineItemDimensionLength',
    'cartLineItemDimensionWeight',
    'cartLineItemDimensionVolume',
    'cartLineItemListPrice',
    'cartLineItemListPriceRatio',
    'cartLineItemCustomField',
    'cartLineItemActualStock',
    'userCustomField',
    'paymentMethod',
    'shippingMethod',
    'cartLineItemGoodsTotal',
    'userOrderTotalAmount',
    'promotionLineItem',
    'promotionCodeOfType',
    'promotionsInCartCount',
    'promotionValue',
    'userBirthday',
    'userCreatedByAdmin',
    'userSalutation',
    'cartLineItemProductType',
    'orderDaysSincePlaced',
    'orderTag',
    'orderTrackingCode',
    'orderDeliveryStatus',
    'adminSalesChannelSource',
    'orderTransactionStatus',
    'orderStatus',
    'orderCreatedByAdmin',
    'orderCustomField',
    'orderDocumentType',
    'orderDocumentTypeSent',
    'cartLineItemPropertyValue',
    'cartLineItemVariantValue',
];

describe('app/decorator/condition-type-data-provider.decorator', () => {
    let service;

    beforeAll(async () => {
        Contena.Service().register('ruleConditionDataProviderService', () => {
            return new RuleConditionService();
        });

        service = Contena.Service('ruleConditionDataProviderService');
    });

    it('should register exactly the expected rule conditions', () => {
        const registered = Object.keys(service.$store);
        const expected = Contena.Feature.isActive('v6.8.0.0')
            ? EXPECTED_CONDITION_TYPES
            : [...EXPECTED_CONDITION_TYPES, 'cartLineItemProductStates'];

        expect(registered).toHaveLength(expected.length);
        expect([...registered].sort()).toEqual([...expected].sort());
    });

    it('should not declare duplicate rule condition types', () => {
        const types = CONDITIONS.map((condition) => condition.type);

        expect(new Set(types).size).toBe(types.length);
    });

    it('every rule condition should declare at least one scope', () => {
        const conditionsWithoutScope = CONDITIONS.filter((condition) => condition.scopes.length === 0);

        expect(conditionsWithoutScope).toHaveLength(0);
    });

    it.each(CONDITIONS.filter((condition) => Boolean(condition.removedInFeature)))(
        'should skip $type while feature flag $removedInFeature is active',
        ({ type, removedInFeature }) => {
            jest.spyOn(Contena.Feature, 'isActive').mockImplementation((flag) => flag === removedInFeature);

            const conditionService = ruleConditionTypeDataProvider(new RuleConditionService());
            expect(Object.keys(conditionService.$store)).not.toContain(type);

            jest.restoreAllMocks();
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('removes the legacy blog states condition with the 6.8 major flag', () => {
        const conditionService = ruleConditionTypeDataProvider(new RuleConditionService());

        expect(conditionService.$store).not.toHaveProperty('cartLineItemProductStates');
    });

    it.each(CONDITIONS.filter((condition) => Boolean(condition.removedInFeature)))(
        'should register a deprecation for $type with version $removedInFeature',
        ({ type, removedInFeature, label, replacement }) => {
            const conditionService = ruleConditionTypeDataProvider(new RuleConditionService());

            expect(conditionService.$deprecations[type]).toEqual({
                version: removedInFeature,
                label,
                replacement,
            });
        },
    );

    it.each(CONDITIONS.filter((condition) => Boolean(condition.removedInFeature)))(
        'should register a deprecation for $type even when feature flag $removedInFeature is active',
        ({ type, removedInFeature }) => {
            jest.spyOn(Contena.Feature, 'isActive').mockImplementation((flag) => flag === removedInFeature);

            const conditionService = ruleConditionTypeDataProvider(new RuleConditionService());
            expect(conditionService.$deprecations[type]).toBeDefined();

            jest.restoreAllMocks();
        },
    );

    it('should not register deprecations for conditions without removedInFeature', () => {
        const conditionService = ruleConditionTypeDataProvider(new RuleConditionService());

        const nonDeprecatedTypes = CONDITIONS.filter((condition) => !condition.removedInFeature).map(
            (condition) => condition.type,
        );

        nonDeprecatedTypes.forEach((type) => {
            expect(conditionService.$deprecations[type]).toBeUndefined();
        });
    });

    it('should add app script conditions', () => {
        service.addScriptConditions([
            {
                id: 'bar',
                name: 'foo',
                group: 'misc',
                config: {},
            },
        ]);

        const condition = service.getByType('bar');

        expect(condition.component).toBe('sw-condition-script');
        expect(condition.type).toBe('scriptRule');
        expect(condition.label).toBe('foo');
    });
});
