import { test } from '@fixtures/AcceptanceTest';
import { Manufacturer, Blog, PropertyGroup } from '@contena-ag/acceptance-test-suite';

const TIMEOUT = 15_000;

test(
    'User should see unavailable filter disabled based on selected filter',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendHome, CheckVisibilityInHome }) => {
        await TestDataService.setSystemConfig({
            'core.listing.disableEmptyFilterOptions': true,
        });
        const color = await TestDataService.createColorPropertyGroup({
            name: 'Color',
            description: 'Color Description',
            options: [{ name: 'Red', colorHexCode: '#bf0f2a' }],
        });
        const size = await TestDataService.createTextPropertyGroup({
            name: 'Size',
            description: 'Size Description',
            options: [{ name: 'Medium' }],
        });
        const propertyGroupsColor: PropertyGroup[] = [color];
        const propertyGroupsText: PropertyGroup[] = [size];
        const sizeOptions = await TestDataService.getPropertyGroupOptions(size.id);
        let colorManufacturer: Manufacturer;
        let parentProductColor: Blog;
        let variantProductColor: Blog[];
        let sizeManufacturer: Manufacturer;
        let parentProductSize: Blog;
        let variantProductSize: Blog[];
        let freeShipProduct: Blog;
        let basicProduct: Blog;

        await test.step('Create manufacturer and blogs then verify blogs created', async () => {
            sizeManufacturer = await TestDataService.createBasicManufacturer({
                name: 'Size Manufacturer',
                description: 'Size Description Manufacturer',
            });
            colorManufacturer = await TestDataService.createBasicManufacturer({
                name: 'Color Manufacturer',
                description: 'Color Description Manufacturer',
            });
            parentProductColor = await TestDataService.createBasicProduct({
                manufacturerId: colorManufacturer.id,
            });
            parentProductSize = await TestDataService.createBasicProduct({
                manufacturerId: sizeManufacturer.id,
            });
            const freeShipManufacturer = await TestDataService.createBasicManufacturer({
                name: 'Free-shipping Manufacturer',
                description: 'Free ship Description Manufacturer',
            });

            freeShipProduct = await TestDataService.createBasicProduct({
                shippingFree: true,
                manufacturerId: freeShipManufacturer.id,
            });
            basicProduct = await TestDataService.createBasicProduct({
                name: 'Blog without filters',
            });
            variantProductColor = await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
                description: 'Variant description',
            });
            variantProductSize = await TestDataService.createVariantProducts(parentProductSize, propertyGroupsText, {
                description: 'Variant description',
            });

            await TestDataService.clearCaches();

            await CheckVisibilityInHome(variantProductSize.at(0).name)();
            await CheckVisibilityInHome(variantProductColor.at(0).name)();
            await CheckVisibilityInHome(freeShipProduct.name)();
            await CheckVisibilityInHome(basicProduct.name)();
        });

        await test.step('Verify setup filters display & enabled', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeVisible();
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeEnabled();
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeVisible();
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeEnabled();
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeVisible();
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled();
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeVisible();
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeEnabled();
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(size.name)).toBeVisible();
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(size.name)).toBeEnabled();
        });

        await test.step('Select a manufacturer and verify that unavailable filter is disabled and blogs are filtered', async () => {
            const manufacturerLocator = await FrontendHome.getFilterItemByFilterName(colorManufacturer.name);
            await ShopCustomer.presses(FrontendHome.manufacturerFilter);
            await manufacturerLocator.check();

            await ShopCustomer.expects(manufacturerLocator).toBeChecked();
            await ShopCustomer.expects(FrontendHome.blogItemNames).toHaveCount(1);
            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: variantProductColor.at(0).name,
                }),
            ).toHaveCount(1);

            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeEnabled({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(size.name)).toBeDisabled({
                timeout: TIMEOUT,
            });
        });

        await test.step('Reset all filters and verify that all filters are enabled', async () => {
            await ShopCustomer.presses(FrontendHome.manufacturerFilter);
            await ShopCustomer.expects(FrontendHome.resetAllButton).toBeVisible();
            await ShopCustomer.presses(FrontendHome.resetAllButton);
            await ShopCustomer.expects(FrontendHome.loader).not.toBeAttached();

            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(size.name)).toBeEnabled({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeEnabled({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });

            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: variantProductSize.at(0).name,
                }),
            ).toHaveCount(1);
            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: variantProductColor.at(0).name,
                }),
            ).toHaveCount(1);
            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: freeShipProduct.name,
                }),
            ).toHaveCount(1);
            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: basicProduct.name,
                }),
            ).toHaveCount(1);
        });

        await test.step('Select another manufacturer and verify that a different filter is disabled', async () => {
            await ShopCustomer.presses(FrontendHome.manufacturerFilter);
            await (await FrontendHome.getFilterItemByFilterName(sizeManufacturer.name)).check();
            await ShopCustomer.expects(FrontendHome.loader).not.toBeAttached();
            await ShopCustomer.expects(FrontendHome.blogItemNames).toHaveCount(1);
            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: variantProductSize.at(0).name,
                }),
            ).toHaveCount(1);

            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(size.name)).toBeEnabled({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeDisabled({
                timeout: TIMEOUT,
            });
        });

        await test.step('Filter only by size and verify color and freeshipping filters are disabled', async () => {
            const sizeFilter = await FrontendHome.getFilterButtonByFilterName(size.name);
            await ShopCustomer.presses(sizeFilter);
            await (await FrontendHome.getFilterItemByFilterName(sizeOptions[0].name)).check();
            await ShopCustomer.expects(FrontendHome.loader).not.toBeAttached();
            await ShopCustomer.expects(FrontendHome.blogItemNames).toHaveCount(1);
            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: variantProductSize.at(0).name,
                }),
            ).toHaveCount(1);

            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeDisabled({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeEnabled({ timeout: TIMEOUT });

            await ShopCustomer.presses(sizeFilter);
            await ShopCustomer.expects(FrontendHome.resetAllButton).toBeVisible();
            await ShopCustomer.presses(FrontendHome.resetAllButton);
            await ShopCustomer.expects(FrontendHome.loader).not.toBeAttached();

            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeVisible();
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeEnabled();
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeVisible();
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeEnabled();
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeVisible();
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled();
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeVisible();
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeEnabled();
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(size.name)).toBeVisible();
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(size.name)).toBeEnabled();
        });

        await test.step('Select filter by free shipping, verify that all filters are disabled', async () => {
            await ShopCustomer.presses(FrontendHome.freeShippingFilter);
            await ShopCustomer.expects(FrontendHome.loader).not.toBeAttached();

            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(size.name)).toBeDisabled({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeDisabled({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeEnabled({ timeout: TIMEOUT });
        });
    },
);

test(
    'User should see unavailable filter options disabled when filtering by rating',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendHome, CheckVisibilityInHome }) => {
        await TestDataService.setSystemConfig({
            'core.listing.disableEmptyFilterOptions': true,
        });
        const color = await TestDataService.createColorPropertyGroup();
        const propertyGroupsColor: PropertyGroup[] = [color];
        const colorManufacturer = await TestDataService.createBasicManufacturer({
            name: 'Color Manufacturer',
            description: 'Color Description Manufacturer',
        });
        const parentProductColor = await TestDataService.createBasicProduct({
            manufacturerId: colorManufacturer.id,
            variantListingConfig: { displayParent: true },
        });
        await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
            description: 'Variant description',
        });
        const freeShipManufacturer = await TestDataService.createBasicManufacturer({
            name: 'Free-shipping Manufacturer',
            description: 'Free ship Description Manufacturer',
        });
        const blogWithShippingAndManufacturer = await TestDataService.createBasicProduct({
            shippingFree: true,
            manufacturerId: freeShipManufacturer.id,
        });
        const blogWithRating1 = await TestDataService.createBasicProduct();
        const blogWithRating2 = await TestDataService.createBasicProduct();
        const blogWithoutFilter = await TestDataService.createBasicProduct({
            name: 'Blog without filters',
        });

        await TestDataService.createProductReview(blogWithRating1.id, {
            points: 3,
        });
        await TestDataService.createProductReview(blogWithRating2.id, {
            points: 5,
        });
        const blogs = [
            blogWithRating1,
            blogWithRating2,
        ];

        await TestDataService.clearCaches();

        await CheckVisibilityInHome(blogWithRating2.name)();
        await CheckVisibilityInHome(blogWithRating1.name)();
        await CheckVisibilityInHome(blogWithoutFilter.name)();
        await CheckVisibilityInHome(blogWithShippingAndManufacturer.name)();
        await CheckVisibilityInHome(parentProductColor.name)();

        await test.step('Verify setup filters display', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.expects(FrontendHome.blogRatingButton).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.blogRatingButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeVisible({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeEnabled({
                timeout: TIMEOUT,
            });
        });

        await test.step('When a rating is selected, verifies that any unavailable filter is disabled and that the blogs are filtered accordingly.', async () => {
            await ShopCustomer.presses(FrontendHome.blogRatingButton);
            await FrontendHome.page.locator('.filter-rating-select-item-label[for="rating-3"]').click();

            await ShopCustomer.expects(FrontendHome.freeShippingFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(FrontendHome.manufacturerFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await FrontendHome.getFilterButtonByFilterName(color.name)).toBeDisabled({
                timeout: TIMEOUT,
            });
            await ShopCustomer.expects(FrontendHome.blogItemNames).toHaveCount(blogs.length);
            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: blogWithRating1.name,
                }),
            ).toHaveCount(1);
            await ShopCustomer.expects(
                FrontendHome.blogItemNames.filter({
                    hasText: blogWithRating2.name,
                }),
            ).toHaveCount(1);
        });
    },
);
