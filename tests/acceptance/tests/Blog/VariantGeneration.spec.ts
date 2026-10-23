import { test } from '@fixtures/AcceptanceTest';
import { PropertyGroup } from '@contena-ag/acceptance-test-suite';

test(
    'Shop administrator should be able to create blog variants.',
    { tag: '@Blog' },
    async ({ ShopAdmin, TestDataService, AdminProductDetail, GenerateVariants }) => {
        const blog = await TestDataService.createBasicProduct();
        const color = await TestDataService.createColorPropertyGroup();
        const size = await TestDataService.createTextPropertyGroup();

        await ShopAdmin.goesTo(AdminProductDetail.url(blog.id));
        await ShopAdmin.page.waitForLoadState('domcontentloaded');

        await ShopAdmin.attemptsTo(GenerateVariants(color.name, size.name));

        /**
         * The test has to handle random behaviour.
         * Variants displayed in the admin grid can have different order and naming combinations.
         */
        const variantLocators = AdminProductDetail.page.locator('.sw-blog-variants-overview__variation-link');
        const variantTexts = await variantLocators.allInnerTexts();
        const allowedVariants = [
            'RedMedium',
            'RedLarge',
            'BlueMedium',
            'BlueLarge',
            'MediumRed',
            'MediumBlue',
            'LargeRed',
            'LargeBlue',
        ];

        const validateVariants = variantTexts.every((variant) => allowedVariants.includes(variant.trim()));

        ShopAdmin.expects(validateVariants).toBeTruthy();
    },
);

test(
    'User should be able to see a new property displayed on the blog detail page',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail, CheckVisibilityInHome }) => {
        await TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true });
        const color = await TestDataService.createColorPropertyGroup({
            name: 'Color',
            description: 'Color Description',
            options: [{ name: 'Red', colorHexCode: '#bf0f2a' }],
        });
        const propertyGroupsColor: PropertyGroup[] = [color];
        const colorManufacturer = await TestDataService.createBasicManufacturer({
            name: 'Color Manufacturer',
            description: 'Color Description Manufacturer',
        });
        const parentProductColor = await TestDataService.createBasicProduct({ manufacturerId: colorManufacturer.id });

        const variantProductColor = await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
            description: 'Variant description',
        });
        await TestDataService.clearCaches();

        await test.step('Verify property display on the blog detail page', async () => {
            await CheckVisibilityInHome(variantProductColor.at(0).name)();
            await ShopCustomer.goesTo(FrontendProductDetail.url(variantProductColor.at(0)));
            await ShopCustomer.expects(FrontendProductDetail.addToCartButton).toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.propertyRadioGroup(color.name)).toBeVisible();
            await ShopCustomer.expects(
                FrontendProductDetail.propertyRadioGroup(color.name).getByRole('radio'),
            ).toHaveCount(variantProductColor.length);
        });
    },
);
