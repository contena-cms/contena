<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\StaticAnalyze\Danger\Rules\AgenticCommercePluginHint;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AgenticCommercePluginHint::class)]
class AgenticCommercePluginHintTest extends TestCase
{
    #[TestDox('Warns once with a sorted, de-duplicated file list when agentic-commerce code is touched')]
    public function testWarnsForAgenticCommerceFiles(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
        ])));

        (new AgenticCommercePluginHint())($context);

        static::assertCount(1, $context->getWarnings());
        $warning = $context->getWarnings()[0];
        static::assertStringContainsString('Agentic Commerce Sales Channel', $warning);
        static::assertStringContainsString('SwagAgenticCommerce', $warning);
        // sorted: Provider/OpenAi… before Tracking/…
        static::assertMatchesRegularExpression('/OpenAiProductFeedProvider\.php.*TrackingEventRoute\.php/s', $warning);
        static::assertStringNotContainsString('ProductExportEntity.php', $warning);
    }

    #[TestDox('Stays silent when no agentic-commerce pattern matches')]
    public function testSilentForUnrelatedFiles(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
        ])));

        (new AgenticCommercePluginHint())($context);

        static::assertFalse($context->hasReports());
    }
}
