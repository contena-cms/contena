<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ProductExport\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ProductExport\Struct\ProductExportResult;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportResult::class)]
class ProductExportResultTest extends TestCase
{
    public function testSkippingTotalKeepsLaterNamedArgumentsUsable(): void
    {
        $result = new ProductExportResult('content', [], offset: 250, hasNextBatch: true);

        static::assertSame(250, $result->getOffset());
        static::assertTrue($result->hasNextBatch());
    }

    public function testPassingNonDefaultTotalTriggersDeprecation(): void
    {
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: Passing $total is deprecated'));

        new ProductExportResult('content', [], 1);
    }
}
