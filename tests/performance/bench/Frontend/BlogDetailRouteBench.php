<?php declare(strict_types=1);

namespace Contena\Tests\Bench\Frontend;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes as Bench;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogDetailRouteBench extends AbstractBenchCase
{
    private const SUBJECT_CUSTOMER = 'user-0';
    private const BLOG_KEY = 'blog-10';

    public function setupWithLogin(): void
    {
        $this->ids = clone Fixtures::getIds();
        $this->context = Fixtures::context([
            ChannelContextService::CUSTOMER_ID => $this->ids->get(self::SUBJECT_CUSTOMER),
        ]);
        if (!$this->context->getUserId()) {
            throw new \Exception('User not logged in for bench tests which require it!');
        }

        static::getContainer()->get(Connection::class)->beginTransaction();
    }

    #[Bench\BeforeMethods(['setUp'])]
    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 30ms +/- 5ms')]
    public function bench_load_product_detail_route_with_logged_out_user(): void
    {
        static::getContainer()->get(BlogDetailRoute::class)
            ->load($this->ids->get(self::BLOG_KEY), new Request(), $this->context, new Criteria());
    }

    #[Bench\BeforeMethods(['setupWithLogin'])]
    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 30ms +/- 5ms')]
    public function bench_load_product_detail_route_with_logged_in_user(): void
    {
        static::getContainer()->get(BlogDetailRoute::class)
            ->load($this->ids->get(self::BLOG_KEY), new Request(), $this->context, new Criteria());
    }
}
