<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Kernel\HttpKernel;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

/**
 * @internal
 */
#[CoversClass(HttpKernel::class)]
class HttpKernelTest extends TestCase
{
    private ControllerResolverInterface&Stub $controllerResolver;

    protected function setUp(): void
    {
        $this->controllerResolver = static::createStub(ControllerResolverInterface::class);
        $this->controllerResolver
            ->method('getController')
            ->willReturn(static function (): Response {
                return new Response();
            });
    }

    public function testNoTransformOnErrorPages(): void
    {
        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects($this->never())
            ->method('transform');

        $kernel = new HttpKernel(
            new EventDispatcher(),
            $this->controllerResolver,
            static::createStub(RequestStack::class),
            static::createStub(ArgumentResolverInterface::class),
            $requestTransformer,
        );

        $request = new Request();
        $request->attributes->set('exception', new \Exception());

        $kernel->handle($request);
    }

    public function testTransformThrowsUnknownException(): void
    {
        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects($this->once())
            ->method('transform')
            ->willThrowException(new \Exception());

        $kernel = new HttpKernel(
            new EventDispatcher(),
            $this->controllerResolver,
            static::createStub(RequestStack::class),
            static::createStub(ArgumentResolverInterface::class),
            $requestTransformer,
        );

        $request = new Request();

        $this->expectException(\Exception::class);

        $kernel->handle($request);
    }

    public function testHandleNormalRequest(): void
    {
        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects($this->once())
            ->method('transform')
            ->willReturnArgument(0);

        $kernel = new HttpKernel(
            new EventDispatcher(),
            $this->controllerResolver,
            static::createStub(RequestStack::class),
            static::createStub(ArgumentResolverInterface::class),
            $requestTransformer,
        );

        $request = new Request();

        $kernel->handle($request);
    }
}
