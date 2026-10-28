<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SalesChannel\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\Api\ResponseFields;
use Contena\Core\System\SalesChannel\Api\StoreApiResponseListener;
use Contena\Core\System\SalesChannel\Api\StructEncoder;
use Contena\Core\System\SalesChannel\GenericStoreApiResponse;
use Contena\Core\System\SalesChannel\SalesChannelException;
use Contena\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiResponseListener::class)]
class StoreApiResponseListenerTest extends TestCase
{
    private MediaUrlPlaceholderHandlerInterface&Stub $mediaUrlPlaceholderHandler;

    private SeoUrlPlaceholderHandlerInterface&Stub $seoUrlPlaceholderHandler;

    protected function setUp(): void
    {
        $this->mediaUrlPlaceholderHandler = static::createStub(MediaUrlPlaceholderHandlerInterface::class);
        $this->mediaUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
        $this->seoUrlPlaceholderHandler = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $this->seoUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
    }

    public function testEncodeEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'store-api.my-route');

        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->exactly(1))->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('store-api.my-route.encode', $listener);

        $instance = new StoreApiResponseListener(
            static::createStub(StructEncoder::class),
            $dispatcher,
            $this->seoUrlPlaceholderHandler,
            $this->mediaUrlPlaceholderHandler
        );

        $instance->encodeResponse(new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new GenericStoreApiResponse(200, new ArrayStruct())
        ));
    }

    public function testEncodeResponseWithDifferentStatusCode(): void
    {
        $encoder = $this->createMock(StructEncoder::class);
        $encoder->expects($this->once())
            ->method('encode')
            ->willReturn(['encoded' => 'data']);

        $responseObject = new class extends Struct {};

        $response = static::createStub(StoreApiResponse::class);
        $response->method('getObject')
            ->willReturn($responseObject);
        $response->method('getStatusCode')
            ->willReturn(404);
        $response->headers = new ResponseHeaderBag();

        $kernel = static::createStub(HttpKernelInterface::class);

        $event = new ResponseEvent(
            $kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $listener = new StoreApiResponseListener($encoder, new EventDispatcher(), $this->seoUrlPlaceholderHandler, $this->mediaUrlPlaceholderHandler);
        $listener->encodeResponse($event);

        $response = $event->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(404, $response->getStatusCode());
        $content = $response->getContent();
        static::assertIsString($content, 'Response content is not a string.');
        $decoded = json_decode($content, true);
        static::assertIsArray($decoded, 'Decoded JSON is not an array.');
        static::assertSame(['encoded' => 'data'], $decoded);
    }

    public function testEncodeResponsePreservesHeaders(): void
    {
        $encoder = $this->createMock(StructEncoder::class);
        $encoder->expects($this->once())
            ->method('encode')
            ->willReturn(['encoded' => 'data']);

        $responseObject = new class extends Struct {};

        $response = static::createStub(StoreApiResponse::class);
        $response->method('getObject')
            ->willReturn($responseObject);
        $response->method('getStatusCode')
            ->willReturn(200);
        $response->headers = new ResponseHeaderBag();
        $response->headers->set('X-Custom-Header', 'value');

        $kernel = static::createStub(HttpKernelInterface::class);

        $event = new ResponseEvent(
            $kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $listener = new StoreApiResponseListener($encoder, new EventDispatcher(), $this->seoUrlPlaceholderHandler, $this->mediaUrlPlaceholderHandler);
        $listener->encodeResponse($event);

        $response = $event->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame('value', $response->headers->get('X-Custom-Header'));
        $content = $response->getContent();
        static::assertIsString($content, 'Response content is not a string.');
        $decoded = json_decode($content, true);
        static::assertIsArray($decoded, 'Decoded JSON is not an array.');
        static::assertSame(['encoded' => 'data'], $decoded);
    }

    public function testEncodeResponseUsesFieldsFromResolvedCriteria(): void
    {
        $criteria = new Criteria();
        $criteria->setIncludes(['product' => ['id']]);

        $request = new Request(['includes' => ['product' => ['name']]]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CRITERIA, $criteria);

        $encoder = $this->createMock(StructEncoder::class);
        $encoder->expects($this->once())
            ->method('encode')
            ->willReturnCallback(static function (Struct $struct, ResponseFields $fields): array {
                static::assertTrue($fields->isAllowed('product', 'id'));
                static::assertFalse($fields->isAllowed('product', 'name'));

                return ['encoded' => 'data'];
            });

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new GenericStoreApiResponse(200, new ArrayStruct())
        );

        $listener = new StoreApiResponseListener($encoder, new EventDispatcher(), $this->seoUrlPlaceholderHandler, $this->mediaUrlPlaceholderHandler);
        $listener->encodeResponse($event);
    }

    public function testEncodeResponseUsesFieldsFromRequestWithoutResolvedCriteria(): void
    {
        $request = new Request(['includes' => ['category' => ['id']]]);

        $encoder = $this->createMock(StructEncoder::class);
        $encoder->expects($this->once())
            ->method('encode')
            ->willReturnCallback(static function (Struct $struct, ResponseFields $fields): array {
                static::assertTrue($fields->isAllowed('category', 'id'));
                static::assertFalse($fields->isAllowed('category', 'name'));

                return ['encoded' => 'data'];
            });

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new GenericStoreApiResponse(200, new ArrayStruct())
        );

        $listener = new StoreApiResponseListener($encoder, new EventDispatcher(), $this->seoUrlPlaceholderHandler, $this->mediaUrlPlaceholderHandler);
        $listener->encodeResponse($event);
    }

    public function testEncodeResponseRejectsIncludesThatAreNotAnArray(): void
    {
        $encoder = $this->createMock(StructEncoder::class);
        $encoder->expects($this->never())->method('encode');

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(['includes' => '{"category":["id"]}']),
            HttpKernelInterface::MAIN_REQUEST,
            new GenericStoreApiResponse(200, new ArrayStruct())
        );

        $listener = new StoreApiResponseListener($encoder, new EventDispatcher(), $this->seoUrlPlaceholderHandler, $this->mediaUrlPlaceholderHandler);

        $this->expectExceptionObject(SalesChannelException::invalidType('The includes must be of the type array, string given'));

        $listener->encodeResponse($event);
    }
}
