import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'As a merchant, I would be able to adjust free tax for defined currency.',
    {
        tag: [
            '@Settings',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        FrontendHeader,
        FrontendProductDetail,
        AddProductToCart,
        ChangeFrontendCurrency,
        ConfirmTermsAndConditions,
        Login,
        ProceedFromProductToCheckout,
        SelectPaymentMethod,
        SelectShippingMethod,
        SubmitOrder,
        InstanceMeta,
    }) => {
        const blog = await TestDataService.createBasicProduct();
        const currency = await TestDataService.createCurrency({ taxFreeFrom: 5 });
        const user = await TestDataService.createCustomer();
        await TestDataService.assignSalesChannelCurrency(DefaultSalesChannel.channel.id, currency.id);

        await ShopCustomer.attemptsTo(Login(user));

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));

        // eslint-disable-next-line playwright/no-conditional-in-test
        if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
            await FrontendHeader.currenciesDropdown.click();
            await FrontendHeader.currenciesMenuOptions.getByText(currency.symbol).click();
        } else {
            await ShopCustomer.attemptsTo(ChangeFrontendCurrency(currency.name));
        }

        let blogPrice = `${currency.isoCode} 24.00`;
        let totalPrice = `${currency.isoCode} 20.16`;

        // eslint-disable-next-line playwright/no-conditional-in-test
        if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
            blogPrice = `${currency.isoCode} 24.00*`;
            totalPrice = `${currency.isoCode} 20.16*`;
        }

        await ShopCustomer.expects(FrontendProductDetail.blogSinglePrice).toHaveText(blogPrice);

        await ShopCustomer.attemptsTo(AddProductToCart(blog));
        await ShopCustomer.expects(FrontendProductDetail.offCanvasSummaryTotalPrice).toHaveText(totalPrice);

        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
        await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

        await ShopCustomer.expects(FrontendCheckoutConfirm.taxPrice).not.toBeVisible();
        await ShopCustomer.expects(FrontendCheckoutConfirm.grandTotalPrice).toHaveText(currency.isoCode + ' 20.16');

        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(FrontendCheckoutFinish.taxPrice).not.toBeVisible();
        await ShopCustomer.expects(FrontendCheckoutFinish.grandTotalPrice).toHaveText(currency.isoCode + ' 20.16');

        const orderId = FrontendCheckoutFinish.getOrderId();

        TestDataService.addCreatedRecord('order', orderId);
    },
);
