<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Defaults;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextRequestRestorer;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ChannelContextRequestRestorer::class)]
class ChannelContextRequestRestorerTest extends TestCase
{
    public function testItReturnsExistingContextWithoutLoadingItAgain(): void
    {
        $existingContext = Generator::generateChannelContext();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $existingContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);

        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService
            ->expects($this->never())
            ->method('get');

        $restorer = new ChannelContextRequestRestorer($contextService);

        static::assertSame($existingContext, $restorer->restore($request));
    }

    public function testItReturnsNullWithoutChannelId(): void
    {
        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService
            ->expects($this->never())
            ->method('get');

        $restorer = new ChannelContextRequestRestorer($contextService);

        static::assertNull($restorer->restore(new Request()));
    }

    public function testItLoadsAndStoresContextFromChannelRequestAttributes(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_ID, 'domain-id');
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM);

        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (ChannelContextServiceParameters $parameters) use ($context): ChannelContext {
                static::assertSame(TestDefaults::CHANNEL, $parameters->getChannelId());
                static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $parameters->getToken());
                static::assertSame(Defaults::LANGUAGE_SYSTEM, $parameters->getLanguageId());
                static::assertSame('domain-id', $parameters->getDomainId());

                return $context;
            });

        $restorer = new ChannelContextRequestRestorer($contextService);

        static::assertSame($context, $restorer->restore($request));
        static::assertSame($context, $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT));
    }
}
