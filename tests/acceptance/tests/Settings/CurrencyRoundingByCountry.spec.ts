import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'As a merchant, I would be able to adjust frontend rounding for defined country',
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
        HomeProduct,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        FrontendHeader,
        FrontendHome,
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
        const blog = HomeProduct;
        const currency = await TestDataService.createCurrency({ factor: 2.25555 });
        const country = await TestDataService.createCountry();
        const salutation = await TestDataService.getSalutation();
        const user = await TestDataService.createCustomer({
            defaultShippingAddress: {
                firstName: 'John',
                lastName: 'Doe',
                city: 'Schöppingen',
                street: 'Ebbinghoff 10',
                zipcode: '48624',
                countryId: country.id,
                salutationId: salutation.id,
            },
            defaultBillingAddress: {
                firstName: 'John',
                lastName: 'Doe',
                city: 'Schöppingen',
                street: 'Ebbinghoff 10',
                zipcode: '48624',
                countryId: country.id,
                salutationId: salutation.id,
            },
        });

        await TestDataService.assignCurrencyCountryRounding(currency.id, country.id, 3);
        await TestDataService.assignSalesChannelCurrency(DefaultSalesChannel.channel.id, currency.id);
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.channel.id, country.id);

        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendHome.url());

        // eslint-disable-next-line playwright/no-conditional-in-test
        if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
            await FrontendHeader.currenciesDropdown.click();
            await FrontendHeader.currenciesMenuOptions.getByText(currency.symbol).click();
        } else {
            await ShopCustomer.attemptsTo(ChangeFrontendCurrency(currency.name));
        }

        const blogListingLocatorsByProductId = await FrontendHome.getListingItemByProductName(blog.name);
        await ShopCustomer.expects(blogListingLocatorsByProductId.blogPrice).toContainText(
            currency.isoCode + ' 22.556',
        );

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.expects(FrontendProductDetail.blogSinglePrice).toContainText(currency.isoCode + ' 22.556');

        await ShopCustomer.attemptsTo(AddProductToCart(blog));
        await ShopCustomer.expects(FrontendProductDetail.offCanvasSummaryTotalPrice).toContainText(
            currency.isoCode + ' 22.556',
        );
        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
        await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

        await ShopCustomer.expects(FrontendCheckoutConfirm.grandTotalPrice).toContainText(currency.isoCode + ' 22.556');

        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(FrontendCheckoutFinish.grandTotalPrice).toContainText(currency.isoCode + ' 22.556');

        const orderId = FrontendCheckoutFinish.getOrderId();

        TestDataService.addCreatedRecord('order', orderId);
    },
);
