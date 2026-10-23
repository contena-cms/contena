import { test as base } from '@playwright/test';
import { Blog, FixtureTypes } from '@contena-ag/acceptance-test-suite';

export interface HomeProducts {
    HomeProduct: Blog;
    HomeProducts: Blog[];
}

export const test = base.extend<FixtureTypes & HomeProducts>({
    HomeProduct: async ({ TestDataService, CheckVisibilityInHome }, use) => {
        const blog = await TestDataService.createBasicProduct();

        await TestDataService.clearCaches();

        await CheckVisibilityInHome(blog.name)();

        await use(blog);
    },

    HomeProducts: async ({ TestDataService, CheckVisibilityInHome }, use) => {
        const blog1 = await TestDataService.createBasicProduct();
        const blog2 = await TestDataService.createBasicProduct();
        const blog3 = await TestDataService.createBasicProduct();

        await TestDataService.clearCaches();

        await CheckVisibilityInHome(blog1.name)();
        await CheckVisibilityInHome(blog2.name)();
        await CheckVisibilityInHome(blog3.name)();

        await use([
            blog1,
            blog2,
            blog3,
        ]);
    },
});
