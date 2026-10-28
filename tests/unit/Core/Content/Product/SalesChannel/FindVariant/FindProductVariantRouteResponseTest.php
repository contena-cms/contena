<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse;
use Contena\Core\Content\Product\SalesChannel\FindVariant\FoundCombination;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(FindProductVariantRouteResponse::class)]
class FindProductVariantRouteResponseTest extends TestCase
{
    public function testInstantiate(): void
    {
        $id = Uuid::randomHex();
        $response = new FindProductVariantRouteResponse(new FoundCombination($id, []));
        $foundCombination = $response->getFoundCombination();

        static::assertSame($id, $foundCombination->getVariantId());
        static::assertSame([], $foundCombination->getOptions());
    }
}
