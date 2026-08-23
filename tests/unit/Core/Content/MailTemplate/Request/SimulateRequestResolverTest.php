<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\MailTemplateException;
use Contena\Core\Content\MailTemplate\Request\Resolver\SimulateRequestResolver;
use Contena\Core\Content\MailTemplate\Request\SimulateRequest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[CoversClass(SimulateRequestResolver::class)]
class SimulateRequestResolverTest extends TestCase
{
    public function testResolveBuildsRequest(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => new DataBag([
                'contentHtml' => 'Hello {{ email }}',
            ]),
            'strictRendering' => false,
            'eventName' => 'user.recovery.request',
        ]);

        $result = $this->resolveRequest($request);

        static::assertSame(['contentHtml' => 'Hello {{ email }}'], $result->templateParts);
        static::assertSame('user.recovery.request', $result->eventName);
        static::assertFalse($result->strictRendering);
    }

    public function testResolveAcceptsArrayMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => ['contentHtml' => 'Hello {{ email }}'],
            'eventName' => 'user.recovery.request',
        ]);

        $result = $this->resolveRequest($request);

        static::assertSame(['contentHtml' => 'Hello {{ email }}'], $result->templateParts);
        static::assertSame('user.recovery.request', $result->eventName);
        static::assertTrue($result->strictRendering);
    }

    public function testResolveThrowsForInvalidMailTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $request = $this->createRequest($context, [
            'templateParts' => 'invalid',
            'eventName' => 'user.recovery.request',
        ]);

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
            'eventName' => 'user.recovery.request',
            'strictRendering' => 'invalid',
        ]);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('strictRendering', 'bool', 'string')
        );

        $this->resolveRequest($request);
    }

    private function resolveRequest(Request $request): SimulateRequest
    {
        $resolver = new SimulateRequestResolver();

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
