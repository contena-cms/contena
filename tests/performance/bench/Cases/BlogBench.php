<?php declare(strict_types=1);

namespace Contena\Tests\Bench\Cases;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes as Bench;
use PhpBench\Attributes\BeforeMethods;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal - only for performance benchmarks
 */
class BlogBench extends AbstractBenchCase
{
    #[BeforeMethods(['setUp'])]
    #[AfterMethods(['tearDown'])]
    #[Bench\Assert('mode(variant.time.avg) < 10ms')]
    public function bench_loading_a_simple_product(): void
    {
        $criteria = new Criteria(
            $this->ids->getList(['simple-blog'])
        );

        static::getContainer()->get('blog.repository')
            ->search($criteria, Context::createDefaultContext());
    }
}
