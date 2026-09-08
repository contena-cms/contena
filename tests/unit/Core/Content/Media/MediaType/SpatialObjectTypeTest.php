<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\MediaType;

use Contena\Core\Content\Media\MediaType\SpatialObjectType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SpatialObjectType::class)]
class SpatialObjectTypeTest extends TestCase
{
    public function testName(): void
    {
        static::assertSame('SPATIAL_OBJECT', new SpatialObjectType()->getName());
    }
}
