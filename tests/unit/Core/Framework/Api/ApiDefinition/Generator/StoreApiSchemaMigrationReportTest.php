<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReport;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(StoreApiSchemaMigrationReport::class)]
class StoreApiSchemaMigrationReportTest extends TestCase
{
    public function testDetectsMismatches(): void
    {
        $report = $this->createReport(
            jsonOverridesPhpGenerated: ['JsonOverrideEntity'],
        );

        static::assertTrue($report->hasMismatches());
    }

    public function testDetectsReportWithoutMismatches(): void
    {
        static::assertFalse($this->createReport()->hasMismatches());
    }

    public function testDetectsPhpGeneratedOnlySchemasAsMismatches(): void
    {
        $report = $this->createReport(
            phpGeneratedOnly: ['Blog'],
        );

        static::assertTrue($report->hasMismatches());
    }

    public function testSerializesReportBuckets(): void
    {
        $report = $this->createReport(
            jsonOverridesPhpGenerated: ['JsonOverrideEntity'],
            phpGeneratedOnly: ['Blog'],
        );

        static::assertSame([
            'jsonOverridesPhpGenerated' => ['JsonOverrideEntity'],
            'phpGeneratedOnly' => ['Blog'],
            'jsonWithoutPhpGenerated' => [],
        ], $report->jsonSerialize());
    }

    /**
     * @param list<string> $jsonOverridesPhpGenerated
     * @param list<string> $phpGeneratedOnly
     * @param list<string> $jsonWithoutPhpGenerated
     */
    private function createReport(
        array $jsonOverridesPhpGenerated = [],
        array $phpGeneratedOnly = [],
        array $jsonWithoutPhpGenerated = [],
    ): StoreApiSchemaMigrationReport {
        return new StoreApiSchemaMigrationReport(
            jsonOverridesPhpGenerated: $jsonOverridesPhpGenerated,
            phpGeneratedOnly: $phpGeneratedOnly,
            jsonWithoutPhpGenerated: $jsonWithoutPhpGenerated,
        );
    }
}
