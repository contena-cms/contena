<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Test\Product\ProductBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('after-sales')]
class ProductReviewSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<ProductReviewCollection>
     */
    private EntityRepository $productReviewRepository;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        /** @var EntityRepository<ProductReviewCollection> $productReviewRepository */
        $productReviewRepository = static::getContainer()->get('product_review.repository');
        $this->productReviewRepository = $productReviewRepository;

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        $this->customerRepository = $customerRepository;

        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        $this->productRepository = $productRepository;

        $this->createCustomer();
        $this->createProduct();
    }

    public function testCreatingNewReview(): void
    {
        $this->createReviews();

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getReviewCount());
    }

    public function testDeletingNewReview(): void
    {
        $this->createReviews();

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getReviewCount());

        $this->productReviewRepository->delete([['id' => $this->ids->get('review')], ['id' => $this->ids->get('review-2')]], Context::createDefaultContext());

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(0, $customer->getReviewCount());
    }

    public function testUpdateReviews(): void
    {
        $this->createReviews();

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getReviewCount());

        $this->productReviewRepository->update([
            [
                'id' => $this->ids->get('review'),
                'content' => 'foo',
            ],
        ], Context::createDefaultContext());

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getReviewCount());

        $this->productReviewRepository->update([
            [
                'id' => $this->ids->get('review-2'),
                'status' => true,
            ],
        ], Context::createDefaultContext());

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(2, $customer->getReviewCount());
    }

    private function createProduct(): void
    {
        $builder = new ProductBuilder($this->ids, 'product');
        $builder->price(10);

        $this->productRepository->create([$builder->build()], Context::createDefaultContext());
    }

    private function createCustomer(): void
    {
        $builder = new CustomerBuilder($this->ids, 'customer');

        $this->customerRepository->create([$builder->build()], Context::createDefaultContext());
    }

    private function createReviews(): void
    {
        $this->productReviewRepository->create([
            [
                'id' => $this->ids->create('review'),
                'productId' => $this->ids->get('product'),
                'customerId' => $this->ids->get('customer'),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'title' => 'fooo',
                'content' => 'baar',
                'points' => 5,
                'externalUser' => 'Test',
                'status' => true,
            ],
            [
                'id' => $this->ids->create('review-2'),
                'productId' => $this->ids->get('product'),
                'customerId' => $this->ids->get('customer'),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'title' => 'fooo',
                'content' => 'baar',
                'points' => 5,
                'externalUser' => 'Test',
                'status' => false,
            ],
        ], Context::createDefaultContext());
    }
}
