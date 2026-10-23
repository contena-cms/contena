import { test } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should be able to upload an image to the blog gallery within a blog.',
    {
        tag: [
            '@Blog',
            '@Media',
        ],
    },
    async ({ ShopAdmin, TestDataService, AdminProductDetail, UploadImage, SaveProduct, IdProvider }) => {
        await test.slow();

        const blog = await TestDataService.createBasicProduct();

        const imageId = IdProvider.getIdPair().id;
        const imageName = `image-${imageId}`;

        await ShopAdmin.goesTo(AdminProductDetail.url(blog.id));
        await ShopAdmin.attemptsTo(UploadImage(imageName));
        await ShopAdmin.attemptsTo(SaveProduct());

        await ShopAdmin.expects(AdminProductDetail.blogImage).toHaveCount(2);
        await ShopAdmin.expects(AdminProductDetail.blogImage.first()).toHaveAttribute('alt', imageName);
        await ShopAdmin.expects(AdminProductDetail.blogImage.nth(1)).toHaveAttribute('alt', imageName);
    },
);
