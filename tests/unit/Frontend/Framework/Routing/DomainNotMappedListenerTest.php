<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Framework\Routing\DomainNotMappedListener;
use Contena\Frontend\Framework\Routing\Exception\ChannelMappingException;
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

        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            0,
            new \Exception()
        );

        new DomainNotMappedListener($container)($event);
    }

    public function testChannelMappingException(): void
    {
        $twig = static::createStub(Environment::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with('twig')->willReturn($twig);
        $container->expects($this->once())->method('getParameter')->with('kernel.debug')->willReturn(false);

        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            0,
            new ChannelMappingException('test')
        );

        new DomainNotMappedListener($container)($event);

        static::assertSame(Response::HTTP_BAD_REQUEST, $event->getResponse()?->getStatusCode());
    }
}
