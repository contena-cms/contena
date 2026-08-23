<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\MailTemplateException;
use Contena\Core\Content\MailTemplate\Request\PreviewRequest;
use Contena\Core\Content\MailTemplate\Request\Resolver\PreviewRequestResolver;
use Contena\Core\Content\MailTemplate\Service\MailTemplateService;
use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[CoversClass(PreviewRequestResolver::class)]
class PreviewRequestResolverTest extends TestCase
{
    private MailTemplateService&MockObject $mailTemplateService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
    }

    public function testResolveBuildsRequestAndFiltersUnknownEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => [
                'user' => 'user-id',
                'media' => 'media-id',
            ],
            'templateData' => [
                'foo' => 'bar',
            ],
            'includeHeaderFooter' => true,
            'strictRendering' => true,
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $result = $this->resolveRequest($request);

        static::assertSame($mailTemplate, $result->mailTemplate);
        static::assertSame(['user' => 'user-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertTrue($result->includeHeaderFooter);
        static::assertTrue($result->strictRendering);
    }

    public function testResolveKeepsEntitiesWhenMailTemplateTypeIsMissing(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();

        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => [
                'user' => 'user-id',
                'media' => 'media-id',
            ],
        ]);

        $this->mailTemplateService->method('loadTemplate')->willReturn($mailTemplate);

        $result = $this->resolveRequest($request);

        static::assertSame(
            [
                'user' => 'user-id',
                'media' => 'media-id',
            ],
            $result->entityMapping
        );
    }

    public function testResolveAcceptsPlainArrayValuesFromRequest(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => ['user' => 'user-id', 'media' => 'media-id'],
            'templateData' => ['foo' => 'bar'],
            'strictRendering' => true,
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $result = $this->resolveRequest($request);

        static::assertSame(['user' => 'user-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertTrue($result->strictRendering);
    }

    public function testResolveAcceptsStringBooleanValuesFromFormRequests(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'includeHeaderFooter' => '1',
            'strictRendering' => '0',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $result = $this->resolveRequest($request);

        static::assertTrue($result->includeHeaderFooter);
        static::assertFalse($result->strictRendering);
    }

    public function testResolveThrowsForInvalidEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('entities', 'array|object', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidTemplateData(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'templateData' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('templateData', 'array|object', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidIncludeHeaderFooter(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'includeHeaderFooter' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('includeHeaderFooter', 'bool', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidStrict(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'strictRendering' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('strictRendering', 'bool', 'string')
        );

        $this->resolveRequest($request);
    }

    private function resolveRequest(Request $request): PreviewRequest
    {
        $resolver = new PreviewRequestResolver($this->mailTemplateService);

        return iterator_to_array(
            $resolver->resolve($request, new ArgumentMetadata('previewRequest', PreviewRequest::class, false, false, null))
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

    private function createMailTemplate(): MailTemplateEntity
    {
        $mailTemplateType = new MailTemplateTypeEntity();
        $mailTemplateType->setAvailableEntities([
            'user' => 'user',
        ]);

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setMailTemplateType($mailTemplateType);

        return $mailTemplate;
    }
}
