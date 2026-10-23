<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRouteResponse;
use Contena\Core\Content\Blog\Channel\FindVariant\FoundCombination;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(FindBlogVariantRouteResponse::class)]
class FindBlogVariantRouteResponseTest extends TestCase
{
    public function testInstantiate(): void
    {
        $id = Uuid::randomHex();
        $response = new FindBlogVariantRouteResponse(new FoundCombination($id, []));
        $foundCombination = $response->getFoundCombination();

        static::assertSame($id, $foundCombination->getVariantId());
        static::assertSame([], $foundCombination->getOptions());
    }
}
