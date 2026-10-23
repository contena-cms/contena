<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\DataTransfer\TranslationUpdate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationInstallPlan;

/**
 * @internal
 */
#[CoversClass(TranslationInstallPlan::class)]
class TranslationInstallPlanTest extends TestCase
{
    public function testNothingCanBeInstalledWhenEveryLocaleIsUnavailable(): void
    {
        static::assertTrue((new TranslationInstallPlan(unavailableLocales: ['de-DE']))->nothingCanBeInstalled());
    }

    public function testSomethingCanBeInstalledWhenALocaleCanBeDownloaded(): void
    {
        $plan = new TranslationInstallPlan(localesToDownload: ['de-DE'], unavailableLocales: ['es-ES']);

        static::assertFalse($plan->nothingCanBeInstalled());
    }

    public function testSomethingCanBeInstalledWhenALocaleCanBeLinked(): void
    {
        $plan = new TranslationInstallPlan(localesToLink: ['de-DE'], unavailableLocales: ['es-ES']);

        static::assertFalse($plan->nothingCanBeInstalled());
    }

    public function testAnEmptyPlanDoesNotReportNothingInstallable(): void
    {
        // No locale was requested at all, which is a different situation from every requested one being unavailable
        static::assertFalse((new TranslationInstallPlan())->nothingCanBeInstalled());
    }
}
