<?php declare(strict_types=1);

namespace Contena\Tests\Bench\Cases;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes as Bench;
use PhpBench\Attributes\BeforeMethods;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Tests\Bench\AbstractBenchCase;

/**
 * @internal - only for performance benchmarks
 */
class ProductBench extends AbstractBenchCase
{
    #[BeforeMethods(['setUp'])]
    #[AfterMethods(['tearDown'])]
    #[Bench\Assert('mode(variant.time.avg) < 10ms')]
    public function bench_loading_a_simple_product(): void
    {
        $criteria = new Criteria(
            $this->ids->getList(['simple-product'])
        );

        static::getContainer()->get('product.repository')
            ->search($criteria, Context::createDefaultContext());
    }
}
