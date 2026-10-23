<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Framework\Routing\DomainNotMappedListener;
use Contena\Frontend\Framework\FrontendFrameworkException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(DomainNotMappedListener::class)]
class DomainNotMappedListenerTest extends TestCase
{
    public function testAnotherExceptionDoesNothing(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');

        $listener = new DomainNotMappedListener($container);

        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            0,
            new \Exception()
        );

        $listener($event);
    }

    public function testChannelMappingException(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(static::createStub(Environment::class));

        $listener = new DomainNotMappedListener($container);

        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            0,
            FrontendFrameworkException::channelMappingException('test')
        );

        $listener($event);

        $response = $event->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response?->getStatusCode());
    }
}
