import { test, formatPrice, type Blog } from '@fixtures/AcceptanceTest';

const promotionName = 'Newsletter Recipient Promotion';
const blogGrossPrice = 50;
const discountPrice = 45.0;
const discountValue = 5.0;
const discountPercentage = 10;

test.describe('Newsletter recipient promotion', () => {
    // Guest and Registered both create blogs in beforeEach. Running them in parallel
    // races MariaDB snapshot isolation on system_config (SQLSTATE 1020).
    test.describe.configure({ mode: 'serial' });

    let blog: Blog;
    let guestCustomerEmail = '';
    let submittedOrderId = '';

    test.beforeEach(async ({ IdProvider, DefaultSalesChannel, TestDataService, ChannelBaseConfig }) => {
        submittedOrderId = '';
        guestCustomerEmail = `${IdProvider.getIdPair().uuid}@test.com`;
        const ruleId = IdProvider.getIdPair().uuid;
        const ruleConfig = {
            id: ruleId,
            name: `Test-Rule - ${ruleId}`,
            description: 'This rule applied for newsletter recipients',
        };
        const ruleCondition = { type: 'userIsNewsletterRecipient', value: { isNewsletterRecipient: true } };
        await TestDataService.createBasicRule(ruleConfig, ruleCondition);

        const promotionConfig = {
            id: IdProvider.getIdPair().uuid,
            name: `${promotionName} ${ruleConfig.id}`,
            useCode: false,
            discountValue: discountPercentage,
            discountScope: 'cart',
            discountType: 'percentage',
            channelId: DefaultSalesChannel.channel.id,
            ruleId: ruleConfig.id,
        };
        await TestDataService.createPromotionWithConditionRule(promotionConfig);

        const blogPrices = [
            {
                currencyId: DefaultSalesChannel.channel.currencyId,
                gross: blogGrossPrice,
                linked: true,
                net: 42.016806722689,
            },
            {
                currencyId: ChannelBaseConfig.defaultCurrencyId,
                gross: blogGrossPrice,
                linked: true,
                net: 42.016806722689,
            },
        ];
        blog = await TestDataService.createBasicProduct({ price: blogPrices, purchasePrices: blogPrices });
    });

    [
        'Guest',
        'Registered',
    ].forEach((userType) => {
        test(
            `${userType} user newsletter recipient should have corresponding promotion applied automatically.`,
            {
                tag: [
                    '@Checkout',
                    '@Frontend',
                ],
            },
            async ({
                IdProvider,
                DefaultSalesChannel,
                TestDataService,
                ShopCustomer,
                FrontendProductDetail,
                FrontendOffCanvasCart,
                FrontendAccountLogin,
                FrontendCheckoutConfirm,
                FrontendCheckoutFinish,
                Login,
                Register,
                AddProductToCart,
                SelectPaymentMethod,
                SelectShippingMethod,
                ConfirmTermsAndConditions,
                SubmitOrder,
            }) => {
                const user =
                    userType === 'Guest'
                        ? { ...DefaultSalesChannel.user, id: IdProvider.getIdPair().uuid, email: guestCustomerEmail }
                        : DefaultSalesChannel.user;

                if (userType === 'Registered') {
                    await ShopCustomer.attemptsTo(Login(user));
                }
                await TestDataService.createNewsletterRecipient(user);
                // Guest has no user context yet; promo applies only after checkout registration.
                await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
                await ShopCustomer.expects(FrontendProductDetail.blogSinglePrice).toContainText(
                    formatPrice(blogGrossPrice),
                );
                await ShopCustomer.attemptsTo(AddProductToCart(blog));

                const offcanvasItem = await FrontendOffCanvasCart.getLineItemByProductNumber(blog.blogNumber);
                await ShopCustomer.expects(offcanvasItem.blogTotalPriceValue).toContainText(
                    formatPrice(blogGrossPrice),
                );

                if (userType === 'Registered') {
                    const promoItem = await FrontendOffCanvasCart.getLineItemByPromotionName(promotionName);
                    await ShopCustomer.expects(promoItem.promotionLabel).toContainText(promotionName);
                    await ShopCustomer.expects(promoItem.promotionPrice).toContainText(formatPrice(discountValue));
                    await ShopCustomer.expects(FrontendOffCanvasCart.subTotalPrice).toContainText(
                        formatPrice(discountPrice),
                    );
                }

                await ShopCustomer.presses(FrontendOffCanvasCart.goToCheckoutButton);
                if (userType === 'Guest') {
                    await FrontendAccountLogin.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });
                    await ShopCustomer.expects(FrontendAccountLogin.registerEmailInput).toBeVisible();
                    await ShopCustomer.attemptsTo(Register({ email: user.email, isGuest: true }));
                }
                await FrontendCheckoutConfirm.page.waitForURL('**/checkout/confirm', { waitUntil: 'commit' });
                await ShopCustomer.expects(FrontendCheckoutConfirm.headline).toBeVisible();

                await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
                await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

                const blogLineItem = FrontendCheckoutConfirm.getLineItemByProductName(blog.translated.name);
                await ShopCustomer.expects(blogLineItem.blogNameLabel).toContainText(blog.translated.name);
                await ShopCustomer.expects(blogLineItem.blogTotalPrice).toContainText(formatPrice(blogGrossPrice));

                const promotionLineItem = FrontendCheckoutConfirm.getLineItemByPromotionName(promotionName);
                await ShopCustomer.expects(promotionLineItem.promotionNameLabel).toContainText(promotionName);
                await ShopCustomer.expects(promotionLineItem.promotionTotalPrice).toContainText(formatPrice(discountValue));
                await ShopCustomer.expects(FrontendCheckoutConfirm.grandTotalPrice).toContainText(
                    formatPrice(discountPrice),
                );

                await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
                await ShopCustomer.attemptsTo(SubmitOrder());
                await ShopCustomer.expects(FrontendCheckoutFinish.grandTotalPrice).toContainText(
                    formatPrice(discountPrice),
                );

                submittedOrderId = FrontendCheckoutFinish.getOrderId();
            },
        );
    });

    test.afterEach(async ({ TestDataService }) => {
        if (submittedOrderId) {
            TestDataService.addCreatedRecord('order', submittedOrderId);
        }
    });
});
