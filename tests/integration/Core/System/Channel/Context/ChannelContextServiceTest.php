<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Context;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelContextServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreatesChannelContextFromChannelDefaults(): void
    {
        $context = static::getContainer()->get(ChannelContextService::class)->get(
            new ChannelContextServiceParameters(TestDefaults::CHANNEL, 'channel-context-test-token')
        );

        static::assertInstanceOf(ChannelContext::class, $context);
        static::assertSame(TestDefaults::CHANNEL, $context->getChannelId());
        static::assertSame(TestDefaults::FALLBACK_MEMBER_GROUP, $context->getMemberGroupId());
        static::assertInstanceOf(ChannelApiSource::class, $context->getContext()->getSource());
        static::assertSame(TestDefaults::CHANNEL, $context->getContext()->getSource()->getChannelId());
        static::assertSame(TestDefaults::CHANNEL, $context->getChannel()->getId());
    }

    public function testOriginalContextVersionAndInheritanceArePreserved(): void
    {
        $original = new Context(new ChannelApiSource(TestDefaults::CHANNEL), [Defaults::LANGUAGE_SYSTEM], 'version-id', false);

        $context = static::getContainer()->get(ChannelContextService::class)->get(
            new ChannelContextServiceParameters(TestDefaults::CHANNEL, 'channel-context-test-token-2', originalContext: $original)
        );

        static::assertSame('version-id', $context->getVersionId());
        static::assertFalse($context->considerInheritance());
    }
}
