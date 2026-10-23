import { test, expect, getLanguageCode, getLocale, type Blog } from '@fixtures/AcceptanceTest';

const testLegalGuaranteeNotice = (showTosCheckbox: boolean) =>
    test(
        `User sees GARAN label with showTosCheckbox=${showTosCheckbox}.`,
        {
            tag: [
                '@Checkout',
                '@Frontend',
            ],
        },
        async ({
            ShopCustomer,
            TestDataService,
            AdminApiContext,
            InstanceMeta,
            FrontendProductDetail,
            FrontendCheckoutConfirm,
            FrontendCheckoutFinish,
            FrontendAccountOrder,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
            ConfirmTermsAndConditions,
            SelectPaymentMethod,
            SelectShippingMethod,
            SubmitOrder,
        }) => {
            await TestDataService.setSystemConfig({
                'core.cart.showLegalGuaranteeNotice': true,
                'core.cart.showTosCheckbox': showTosCheckbox,
            });
            const manufacturer = await TestDataService.createBasicManufacturer({ name: 'GARAN-ACME' });
            const guaranteeMonths = 36;
            const expectedGuaranteeDuration = '3';

            const expectedLegalGuaranteeNoticeUrl = (locale = getLocale()): string => {
                const languagePrefix = getLanguageCode(locale).split('-')[0]?.toLowerCase() ?? 'en';
                const slug = languagePrefix === 'de' ? 'garantien' : 'guarantees';

                return `https://europa.eu/youreurope/${slug}`;
            };
            const garanLabelInfoLanguage = getLanguageCode(getLocale()).split('-')[0]?.toLowerCase() === 'de' ? 'de' : 'en';
            const garanLabelInfoUrl = `https://europa.eu/youreurope/citizens/consumers/shopping/commercial-guarantee-durability/index_${garanLabelInfoLanguage}.htm`;
            const garanLabelModal = FrontendProductDetail.page.locator('.js-pseudo-modal .modal.show');
            const blog = await TestDataService.createBasicProduct({
                name: 'GARAN-Label-Checkout-Blog',
                manufacturerId: manufacturer.id,
                manufacturerNumber: 'ACME-36',
                ...({
                    guaranteeMonths,
                    guaranteeConfirmed: true,
                } as Partial<Blog>),
            });

            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));

            await test.step('Blog detail page shows nested GARAN label and expands to full label.', async () => {
                await ShopCustomer.expects(FrontendProductDetail.garanNestedLabel).toBeVisible();
                await ShopCustomer.expects(FrontendProductDetail.garanNestedLabel).toContainText(
                    expectedGuaranteeDuration,
                );
                await ShopCustomer.expects(FrontendProductDetail.showGaranLabelButton).toBeVisible();
                await ShopCustomer.expects(FrontendProductDetail.hideGaranLabelButton).not.toBeVisible();

                await ShopCustomer.presses(FrontendProductDetail.showGaranLabelButton);

                await ShopCustomer.expects(FrontendProductDetail.garanFullLabel).toContainText('GARAN-ACME');
                await ShopCustomer.expects(FrontendProductDetail.garanFullLabel).toContainText('ACME-36');
                await ShopCustomer.expects(FrontendProductDetail.garanFullLabel).toContainText(expectedGuaranteeDuration);
                await ShopCustomer.expects(FrontendProductDetail.showGaranLabelButton).not.toBeVisible();
                await ShopCustomer.expects(FrontendProductDetail.hideGaranLabelButton).toBeVisible();
            });

            await test.step('Nested GARAN label on the blog detail page expands the full label itself.', async () => {
                await ShopCustomer.presses(FrontendProductDetail.hideGaranLabelButton);
                await ShopCustomer.expects(FrontendProductDetail.garanFullLabel).not.toBeVisible();

                await ShopCustomer.presses(FrontendProductDetail.garanNestedLabel.getByRole('button'));

                await ShopCustomer.expects(FrontendProductDetail.garanFullLabel).toBeVisible();
                await ShopCustomer.expects(FrontendProductDetail.hideGaranLabelButton).toBeFocused();
                await ShopCustomer.expects(FrontendProductDetail.garanFullLabel.getByRole('link')).toHaveAttribute(
                    'href',
                    garanLabelInfoUrl,
                );
            });

            await test.step('Nested GARAN label on cart and checkout line items opens the full label.', async () => {
                await ShopCustomer.attemptsTo(AddProductToCart(blog));
                await ShopCustomer.expects(FrontendProductDetail.offCanvasLineItemGaranLabel).toBeVisible();

                await ShopCustomer.presses(FrontendProductDetail.offCanvasLineItemGaranLabel.getByRole('button'));
                await ShopCustomer.expects(garanLabelModal).toBeFocused();
                await ShopCustomer.expects(garanLabelModal).toContainText('GARAN-ACME');
                await ShopCustomer.expects(garanLabelModal).toContainText('ACME-36');
                await ShopCustomer.expects(garanLabelModal.getByRole('link')).toHaveAttribute('href', garanLabelInfoUrl);
                await ShopCustomer.presses(garanLabelModal.locator('.btn-close'));
                await ShopCustomer.expects(garanLabelModal).not.toBeVisible();

                await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
                await ShopCustomer.expects(FrontendCheckoutConfirm.lineItemGaranLabel).toBeVisible();
            });

            await test.step('Legal guarantee notice modal displays with expected Europe language URL.', async () => {
                await ShopCustomer.expects(FrontendCheckoutConfirm.legalGuaranteeNoticeLink).toHaveAttribute(
                    'href',
                    expectedLegalGuaranteeNoticeUrl(),
                );
            });

            await test.step('Legal guarantee notice follows the active terms layout.', async () => {
                if (InstanceMeta.features['V6_8_0_0'] && !showTosCheckbox) {
                    await ShopCustomer.expects(FrontendCheckoutConfirm.termsAndConditionsCheckbox).not.toBeVisible();
                    await ShopCustomer.expects(
                        FrontendCheckoutConfirm.termsAutoConfirmedText.locator(
                            '[data-bs-target="#legalGuaranteeNoticeModal"]',
                        ),
                    ).toBeVisible();
                    await ShopCustomer.expects(
                        FrontendCheckoutConfirm.page.locator('.legal-guarantee-notice'),
                    ).not.toBeVisible();
                } else {
                    await ShopCustomer.expects(FrontendCheckoutConfirm.termsAndConditionsCheckbox).toBeVisible();
                    await ShopCustomer.expects(
                        FrontendCheckoutConfirm.page.locator('.legal-guarantee-notice'),
                    ).toBeVisible();
                    await ShopCustomer.expects(FrontendCheckoutConfirm.termsAutoConfirmedText).not.toBeVisible();
                }
                await ShopCustomer.expects(
                    FrontendCheckoutConfirm.termsAndConditionsWithLegalGuaranteeRightsLabel,
                ).not.toBeVisible();
            });

            let orderNumber: string;

            await test.step('User can complete checkout.', async () => {
                await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
                await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
                await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
                await ShopCustomer.attemptsTo(SubmitOrder());

                const orderId = FrontendCheckoutFinish.getOrderId();
                TestDataService.addCreatedRecord('order', orderId);
                orderNumber = await FrontendCheckoutFinish.getOrderNumber();
            });

            await test.step('Nested GARAN label on account order detail opens the full label.', async () => {
                await ShopCustomer.goesTo(FrontendAccountOrder.url());
                const orderLocators = await FrontendAccountOrder.getOrderByOrderNumber(orderNumber, blog.blogNumber);
                await ShopCustomer.presses(orderLocators.orderDetailButton);
                await ShopCustomer.expects(orderLocators.lineItemGaranLabel).toBeVisible();

                await ShopCustomer.presses(orderLocators.lineItemGaranLabel.getByRole('button'));
                await ShopCustomer.expects(garanLabelModal).toContainText('ACME-36');
            });

            await test.step('Account order detail keeps the GARAN label the order was placed with.', async () => {
                const response = await AdminApiContext.patch(`blog/${blog.id}`, {
                    data: { guaranteeMonths: 48 },
                });
                expect(response.ok()).toBeTruthy();

                await ShopCustomer.goesTo(FrontendAccountOrder.url());
                const orderLocators = await FrontendAccountOrder.getOrderByOrderNumber(orderNumber, blog.blogNumber);
                await ShopCustomer.presses(orderLocators.orderDetailButton);
                await ShopCustomer.expects(orderLocators.lineItemGaranLabel.locator('tspan')).toHaveText(
                    expectedGuaranteeDuration,
                );
            });
        },
    );

testLegalGuaranteeNotice(true);
testLegalGuaranteeNotice(false);

test(
    'User sees standard terms and no GARAN label when legal guarantee notice is disabled.',
    {
        tag: [
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        InstanceMeta,
        FrontendProductDetail,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        FrontendAccountOrder,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SelectShippingMethod,
        SubmitOrder,
    }) => {
        await TestDataService.setSystemConfig({ 'core.cart.showLegalGuaranteeNotice': false });
        const manufacturer = await TestDataService.createBasicManufacturer({ name: 'GARAN-ACME-Off' });
        const blog = await TestDataService.createBasicProduct({
            name: 'GARAN-Label-Checkout-Blog-Notice-Off',
            manufacturerId: manufacturer.id,
            manufacturerNumber: 'ACME-36-OFF',
            ...({
                guaranteeMonths: 36,
                guaranteeConfirmed: false,
            } as Partial<Blog>),
        });

        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));

        await test.step('Blog detail page does not show GARAN label.', async () => {
            await ShopCustomer.expects(FrontendProductDetail.garanNestedLabel).not.toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.showGaranLabelButton).not.toBeVisible();
        });

        await test.step('Cart and checkout show standard terms without GARAN or legal guarantee notice.', async () => {
            await ShopCustomer.attemptsTo(AddProductToCart(blog));
            await ShopCustomer.expects(FrontendProductDetail.offCanvasLineItemGaranLabel).not.toBeVisible();
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

            await ShopCustomer.expects(FrontendCheckoutConfirm.lineItemGaranLabel).not.toBeVisible();
            await ShopCustomer.expects(FrontendCheckoutConfirm.legalGuaranteeNoticeLink).not.toBeVisible();
            await ShopCustomer.expects(FrontendCheckoutConfirm.page.locator('.legal-guarantee-notice')).not.toBeVisible();
            if (InstanceMeta.features['V6_8_0_0']) {
                await ShopCustomer.expects(FrontendCheckoutConfirm.termsAndConditionsCheckbox).not.toBeVisible();
                await ShopCustomer.expects(FrontendCheckoutConfirm.termsAutoConfirmedText).toBeVisible();
            } else {
                await ShopCustomer.expects(FrontendCheckoutConfirm.termsAndConditionsCheckbox).toBeVisible();
            }
        });

        let orderNumber: string;

        await test.step('User can complete checkout.', async () => {
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SubmitOrder());

            const orderId = FrontendCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await FrontendCheckoutFinish.getOrderNumber();
        });

        await test.step('Nested GARAN label is not visible on account order detail.', async () => {
            await ShopCustomer.goesTo(FrontendAccountOrder.url());
            const orderLocators = await FrontendAccountOrder.getOrderByOrderNumber(orderNumber, blog.blogNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.lineItemGaranLabel).not.toBeVisible();
        });
    },
);
