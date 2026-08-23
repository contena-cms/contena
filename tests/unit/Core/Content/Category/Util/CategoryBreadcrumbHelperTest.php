<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Util\CategoryBreadcrumbHelper;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(CategoryBreadcrumbHelper::class)]
class CategoryBreadcrumbHelperTest extends TestCase
{
    private const ROOT_ID = 'root-id';
    private const NAVIGATION_ID = 'navigation-id';
    private const SERVICE_ID = 'service-id';
    private const FOOTER_ID = 'footer-id';
    private const CHILD_ID = 'child-id';
    private const LEAF_ID = 'leaf-id';

    public function testReturnsFullBreadcrumbWhenNoChannelAndNoNavigationCategory(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::NAVIGATION_ID => 'Catalogue',
            self::CHILD_ID => 'Category',
        ]);

        static::assertSame(
            [
                self::ROOT_ID => 'Root',
                self::NAVIGATION_ID => 'Catalogue',
                self::CHILD_ID => 'Category',
            ],
            CategoryBreadcrumbHelper::build($category)
        );
    }

    public function testSlicesBreadcrumbAfterChannelNavigationCategory(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::NAVIGATION_ID => 'Catalogue',
            self::CHILD_ID => 'Category',
            self::LEAF_ID => 'Leaf',
        ]);

        $channel = $this->createChannel(self::NAVIGATION_ID);

        static::assertSame(
            [
                self::CHILD_ID => 'Category',
                self::LEAF_ID => 'Leaf',
            ],
            CategoryBreadcrumbHelper::build($category, $channel)
        );
    }

    public function testSlicesBreadcrumbAfterServiceCategory(): void
    {
        $category = $this->createCategory([
            self::SERVICE_ID => 'Service',
            self::CHILD_ID => 'Category',
        ]);

        $channel = $this->createChannel('unrelated-navigation', self::SERVICE_ID);

        static::assertSame(
            [self::CHILD_ID => 'Category'],
            CategoryBreadcrumbHelper::build($category, $channel)
        );
    }

    public function testSlicesBreadcrumbAfterFooterCategory(): void
    {
        $category = $this->createCategory([
            self::FOOTER_ID => 'Footer',
            self::CHILD_ID => 'Category',
        ]);

        $channel = $this->createChannel('unrelated-navigation', 'unrelated-service', self::FOOTER_ID);

        static::assertSame(
            [self::CHILD_ID => 'Category'],
            CategoryBreadcrumbHelper::build($category, $channel)
        );
    }

    public function testExplicitNavigationCategoryIdTakesPrecedence(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::NAVIGATION_ID => 'Catalogue',
            self::CHILD_ID => 'Category',
            self::LEAF_ID => 'Leaf',
        ]);

        $channel = $this->createChannel(self::NAVIGATION_ID);

        static::assertSame(
            [self::LEAF_ID => 'Leaf'],
            CategoryBreadcrumbHelper::build($category, $channel, self::CHILD_ID)
        );
    }

    public function testUsesNavigationCategoryIdWithoutChannel(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::NAVIGATION_ID => 'Catalogue',
            self::CHILD_ID => 'Category',
        ]);

        static::assertSame(
            [
                self::CHILD_ID => 'Category',
            ],
            CategoryBreadcrumbHelper::build($category, null, self::NAVIGATION_ID)
        );
    }

    public function testReturnsFullBreadcrumbWhenNoEntryPointMatches(): void
    {
        $category = $this->createCategory([
            self::ROOT_ID => 'Root',
            self::CHILD_ID => 'Category',
        ]);

        $channel = $this->createChannel('unknown-navigation');

        static::assertSame(
            [
                self::ROOT_ID => 'Root',
                self::CHILD_ID => 'Category',
            ],
            CategoryBreadcrumbHelper::build($category, $channel)
        );
    }

    /**
     * @param array<string, string> $breadcrumb
     */
    private function createCategory(array $breadcrumb): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId(self::LEAF_ID);
        $category->setTranslated(['breadcrumb' => $breadcrumb]);

        return $category;
    }

    private function createChannel(
        string $navigationCategoryId,
        ?string $serviceCategoryId = null,
        ?string $footerCategoryId = null,
    ): ChannelEntity {
        $channel = new ChannelEntity();
        $channel->setId('channel-id');
        $channel->setNavigationCategoryId($navigationCategoryId);

        if ($serviceCategoryId !== null) {
            $channel->setServiceCategoryId($serviceCategoryId);
        }

        if ($footerCategoryId !== null) {
            $channel->setFooterCategoryId($footerCategoryId);
        }

        return $channel;
    }
}
