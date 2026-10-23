<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Capability\DefaultChannelType;

/**
 * @internal
 */
#[CoversClass(DefaultChannelType::class)]
class DefaultChannelTypeTest extends TestCase
{
    #[DataProvider('defaultTypeProvider')]
    public function testOnlyFrontendAndHeadlessAreTransactional(DefaultChannelType $channelType, bool $shouldBeTransactional): void
    {
        static::assertSame($shouldBeTransactional, $channelType->isTransactional());
    }

    /**
     * @return iterable<string, array{DefaultChannelType, bool}>
     */
    public static function defaultTypeProvider(): iterable
    {
        yield 'frontend sells' => [DefaultChannelType::FRONTEND, true];
        yield 'headless sells' => [DefaultChannelType::HEADLESS, true];
        yield 'blog comparison exports a feed' => [DefaultChannelType::BLOG_COMPARISON, false];
    }
}
