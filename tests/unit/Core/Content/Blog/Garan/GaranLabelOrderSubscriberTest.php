<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Order\CartConvertedEvent;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Garan\GaranLabelOrderSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Test\Generator;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(GaranLabelOrderSubscriber::class)]
class GaranLabelOrderSubscriberTest extends TestCase
{
    private const PLACED_WITH = ['guaranteeMonths' => 42, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    private const CURRENT = ['guaranteeMonths' => 36, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    public function testSubscribesToCartConversion(): void
    {
        static::assertSame(
            [CartConvertedEvent::class => 'storeLabels'],
            GaranLabelOrderSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, bool, array<string, int|string>|null}>
     */
    public static function labelProvider(): \Generator
    {
        yield 'a new order stores the current label' => [[], false, self::CURRENT];
        yield 'a new order replaces a label stored earlier with the current one' => [['garanLabel' => self::PLACED_WITH], false, self::CURRENT];
        yield 'an edited order keeps the label it was placed with' => [['garanLabel' => self::PLACED_WITH], true, self::PLACED_WITH];
        yield 'an edited order keeps having no label' => [['garanLabel' => null], true, null];
        yield 'a blog added to an edited order gets the current label' => [[], true, self::CURRENT];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, int|string>|null $expected
     */
    #[DataProvider('labelProvider')]
    public function testStoresTheLabelInTheLineItemPayload(array $payload, bool $isExistingOrder, ?array $expected): void
    {
        $event = self::createEvent([['blogId' => 'blog-id', 'payload' => $payload]], $isExistingOrder);

        $this->createSubscriber([new BlogCollection([self::createBlog('blog-id')])])->storeLabels($event);

        static::assertSame($expected, $event->getConvertedCart()['lineItems'][0]['payload']['garanLabel']);
    }

    public function testSearchesEachBlogOnceWithInheritanceAndSkipsLineItemsWithoutBlog(): void
    {
        $event = self::createEvent([
            ['blogId' => 'blog-id', 'payload' => []],
            ['blogId' => null, 'payload' => []],
            ['blogId' => 'blog-id', 'payload' => []],
        ], false);

        $this->createSubscriber([
            static function (Criteria $criteria, Context $context): BlogCollection {
                static::assertSame(['blog-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertTrue($context->considerInheritance(), 'variants inherit the guarantee and manufacturer');

                return new BlogCollection([self::createBlog('blog-id')]);
            },
        ])->storeLabels($event);

        $lineItems = $event->getConvertedCart()['lineItems'];
        static::assertSame(self::CURRENT, $lineItems[0]['payload']['garanLabel']);
        static::assertArrayNotHasKey('garanLabel', $lineItems[1]['payload']);
        static::assertSame(self::CURRENT, $lineItems[2]['payload']['garanLabel']);
    }

    public function testLeavesTheLabelOutForDeletedBlogs(): void
    {
        $event = self::createEvent([['blogId' => 'deleted-blog-id', 'payload' => []]], false);

        $this->createSubscriber([new BlogCollection()])->storeLabels($event);

        static::assertArrayNotHasKey('garanLabel', $event->getConvertedCart()['lineItems'][0]['payload']);
    }

    public function testDoesNotSearchWhenNoLineItemNeedsTheCurrentLabel(): void
    {
        $event = self::createEvent([
            ['blogId' => null, 'payload' => []],
            ['blogId' => 'blog-id', 'payload' => ['garanLabel' => self::PLACED_WITH]],
        ], true);

        // the repository throws on a search it has no result for
        $this->createSubscriber([])->storeLabels($event);

        static::assertSame(self::PLACED_WITH, $event->getConvertedCart()['lineItems'][1]['payload']['garanLabel']);
    }



    private static function createBlog(string $id): BlogEntity
    {
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setTranslated(['name' => 'ACME']);

        $blog = new BlogEntity();
        $blog->setId($id);
        $blog->setManufacturer($manufacturer);
        $blog->setManufacturerNumber('ACME-36');
        $blog->setGuaranteeMonths(36);
        $blog->setGuaranteeConfirmed(true);

        return $blog;
    }
}
