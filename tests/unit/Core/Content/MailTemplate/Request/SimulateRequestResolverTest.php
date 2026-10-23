<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\MailTemplateException;
use Contena\Core\Content\MailTemplate\Request\Resolver\SimulateRequestResolver;
use Contena\Core\Content\MailTemplate\Request\SimulateRequest;
use Contena\Core\Content\Shared\MailFlow\DataProvider\ChannelProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[CoversClass(SimulateRequestResolver::class)]
class SimulateRequestResolverTest extends TestCase
{
    private ChannelProvider&MockObject $channelProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channelProvider = $this->createMock(ChannelProvider::class);
    }

    public function testResolveBuildsRequest(): void
    {
        $context = Context::createDefaultContext();
        $channel = new ChannelEntity();
        $request = $this->createRequest($context, [
            'templateParts' => new DataBag([
                'contentHtml' => 'Hello {{ email }}',
            ]),
            'eventName' => 'checkout.user.before.login',
            'strictRendering' => false,
            'channelId' => 'sales-channel-id',
        ]);

        $this->channelProvider->expects($this->once())
            ->method('getData')
            ->with('sales-channel-id', $context)
            ->willReturn($channel);

        $result = $this->resolveRequest($request);

        static::assertSame(['contentHtml' => 'Hello {{ email }}'], $result->templateParts);
        static::assertSame('checkout.user.before.login', $result->eventName);
        static::assertSame($channel, $result->channel);
        static::assertFalse($result->strictRendering);
    }

    public function testResolveAcceptsArrayMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.user.before.login',
        ]);

        $this->channelProvider->expects($this->never())->method('getData');

        $result = $this->resolveRequest($request);

        static::assertSame(['contentHtml' => 'Hello {{ email }}'], $result->templateParts);
        static::assertSame('checkout.user.before.login', $result->eventName);
        static::assertNull($result->channel);
        static::assertTrue($result->strictRendering);
    }

    public function testResolveThrowsForInvalidMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => 'invalid',
            'eventName' => 'checkout.user.before.login',
        ]);

        $this->channelProvider->expects($this->never())->method('getData');

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('templateParts', 'array|object', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidStrict(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.user.before.login',
            'strictRendering' => 'invalid',
        ]);

        $this->channelProvider->expects($this->never())->method('getData');

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('strictRendering', 'bool', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidChannelIdType(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.user.before.login',
            'channelId' => 1,
        ]);

        $this->channelProvider->expects($this->never())->method('getData');

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('channelId', 'string', 'int')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForUnknownChannelId(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'checkout.user.before.login',
            'channelId' => 'sales-channel-id',
        ]);

        $this->channelProvider->expects($this->once())
            ->method('getData')
            ->with('sales-channel-id', $context)
            ->willReturn(null);

        $this->expectExceptionObject(MailTemplateException::invalidChannelId('sales-channel-id'));

        $this->resolveRequest($request);
    }

    private function resolveRequest(Request $request): SimulateRequest
    {
        $resolver = new SimulateRequestResolver($this->channelProvider);

        return iterator_to_array(
            $resolver->resolve($request, new ArgumentMetadata('simulateRequest', SimulateRequest::class, false, false, null))
        )[0];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createRequest(Context $context, array $payload): Request
    {
        $request = new Request([], $payload);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        return $request;
    }
}
