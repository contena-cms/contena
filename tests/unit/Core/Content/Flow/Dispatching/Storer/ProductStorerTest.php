<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ProductStorer;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Contena\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\ProductAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductStorer::class)]
class ProductStorerTest extends TestCase
{
    private ProductStorer $storer;

    private Stub&ProductProvider $productProvider;

    protected function setUp(): void
    {
        $this->productProvider = static::createStub(ProductProvider::class);

        $this->storer = $this->createStorer($this->productProvider);
    }

    public function testStoreWithAware(): void
    {
        $product = new ProductEntity();
        $product->setId('product-id');

        $event = new ReviewFormEvent(Context::createDefaultContext(), '', new MailRecipientStruct([]), new DataBag(), 'product-id', '', $product);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ProductAware::PRODUCT_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ProductAware::PRODUCT_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['productId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('product', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertCount(0, $storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $productProvider = $this->createMock(ProductProvider::class);
        $storer = $this->createStorer($productProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['productId' => 'id'], []);
        $storer->restore($storable);
        $entity = new ProductEntity();
        $entity->setId('id');

        $productProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('product');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $productProvider = $this->createMock(ProductProvider::class);
        $storer = $this->createStorer($productProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['productId' => 'id'], []);
        $storer->restore($storable);
        $productProvider->expects($this->once())->method('getData')->willReturn(null);

        $res = $storable->getData('product');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['productId' => null], []);
        $this->storer->restore($storable);
        $product = $storable->getData('product');

        static::assertNull($product);
    }

    private function createStorer(ProductProvider $productProvider): ProductStorer
    {
        return new ProductStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $productProvider,
        );
    }
}
