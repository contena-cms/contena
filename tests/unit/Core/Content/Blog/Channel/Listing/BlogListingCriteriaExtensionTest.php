<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Extension\BlogListingCriteriaExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(BlogListingCriteriaExtension::class)]
class BlogListingCriteriaExtensionTest extends TestCase
{
    public function testBlogListingCriteriaExample(): void
    {
        $example = new BlogListingCriteriaExtensionExample();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($example);

        $extension = new BlogListingCriteriaExtension(
            new Criteria(),
            static::createStub(ChannelContext::class),
            'categoryId'
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: BlogListingCriteriaExtension::NAME,
            extension: $extension,
            function: static function (Criteria $criteria, ChannelContext $context, string $categoryId): Criteria {
                $criteria->addFilter(
                    new EqualsFilter('blog.categoriesRo.id', $categoryId)
                );

                return $criteria;
            }
        );

        static::assertInstanceOf(Criteria::class, $result);
        static::assertSame([], $result->getFilters());
    }
}
