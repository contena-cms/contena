<?php declare(strict_types=1);

namespace Contena\Tests\Bench\Frontend;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes as Bench;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal - only for performance benchmarks
 */
#[Bench\BeforeMethods(['setupWithLogin'])]
class BlogListingRouteBench extends AbstractBenchCase
{
    use BasicTestDataBehaviour;
    use ChannelApiTestBehaviour;

    private const SUBJECT_CUSTOMER = 'user-0';
    private const CATEGORY_KEY = 'level-2.1';

    public function setupWithLogin(): void
    {
        $this->ids = clone Fixtures::getIds();
        $this->context = Fixtures::context([
            ChannelContextService::CUSTOMER_ID => $this->ids->get(self::SUBJECT_CUSTOMER),
        ]);
        if (!$this->context->getUser() instanceof UserEntity) {
            throw new \Exception('User not logged in for bench tests which require it!');
        }

        static::getContainer()->get(Connection::class)->beginTransaction();
    }

    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 35ms')]
    public function bench_load_product_listing_route_with_logged_out_user(): void
    {
        static::getContainer()->get(BlogListingRoute::class)
            ->load($this->ids->get(self::CATEGORY_KEY), new Request(), $this->context, new Criteria());
    }

    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 35ms')]
    public function bench_load_product_listing_route_no_criteria(): void
    {
        static::getContainer()->get(BlogListingRoute::class)
            ->load($this->ids->get(self::CATEGORY_KEY), new Request(), $this->context, new Criteria());
    }

    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 35ms')]
    public function bench_load_product_listing_route_only_active_and_price_below_80(): void
    {
        $criteria = (new Criteria())
            ->addFilter(new RangeFilter('price', [
                RangeFilter::GTE => 0.00,
                RangeFilter::LT => 80.00,
            ]))
            ->addFilter(new EqualsFilter('active', true));
        static::getContainer()->get(BlogListingRoute::class)
            ->load($this->ids->get(self::CATEGORY_KEY), new Request(), $this->context, $criteria);
    }

    protected static function getKernel(): KernelInterface
    {
        return self::getContainer()->get('kernel');
    }
}
