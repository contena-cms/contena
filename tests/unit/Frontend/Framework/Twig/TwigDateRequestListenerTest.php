<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Twig\TwigDateRequestListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(TwigDateRequestListener::class)]
class TwigDateRequestListenerTest extends TestCase
{
    public static function dataProviderOnKernelRequest(): \Generator
    {
        yield 'UTC frontend timezone is unchanged' => [
            FrontendRouteScope::ID,
            'UTC',
            false,
        ];

        yield 'valid frontend timezone is applied' => [
            FrontendRouteScope::ID,
            'Europe/Berlin',
            true,
        ];

        yield 'administration request is unchanged' => [
            'admin',
            'Europe/Berlin',
            false,
        ];

        yield 'request without scope is unchanged' => [
            null,
            'Europe/Berlin',
            false,
        ];

        yield 'UTC request without scope is unchanged' => [
            null,
            'UTC',
            false,
        ];

        yield 'frontend request without cookie is unchanged' => [
            FrontendRouteScope::ID,
            null,
            false,
        ];

        yield 'request without scope or cookie is unchanged' => [
            null,
            null,
            false,
        ];
    }

    #[DataProvider('dataProviderOnKernelRequest')]
    public function testEvent(?string $scope, ?string $cookie, bool $changed): void
    {
        $request = new Request();
        if ($scope) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [$scope]);
        }

        if ($cookie) {
            $request->cookies->set(TwigDateRequestListener::TIMEZONE_COOKIE, $cookie);
        }

        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $container = new ContainerBuilder();
        $service = new Environment(new ArrayLoader());

        $beforeLocale = $service->getExtension(CoreExtension::class)->getTimezone();

        $container->set('twig', $service);
        $listener = new TwigDateRequestListener($container);

        $listener->__invoke($event);

        if ($changed) {
            static::assertNotSame(
                $beforeLocale,
                $service->getExtension(CoreExtension::class)->getTimezone()
            );
        } else {
            static::assertSame(
                $beforeLocale,
                $service->getExtension(CoreExtension::class)->getTimezone()
            );
        }
    }

    public function testListenerKeepsConfiguredTimezoneAsRenderFallback(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [FrontendRouteScope::ID]);
        $request->cookies->set(TwigDateRequestListener::TIMEZONE_COOKIE, 'America/New_York');

        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $container = new ContainerBuilder();
        $twig = new TwigEnvironment(new ArrayLoader(['test' => '{{ testDate|date("Y-m-d") }}']));
        $twig->getExtension(CoreExtension::class)->setTimezone('Europe/Berlin');
        $container->set('twig', $twig);

        new TwigDateRequestListener($container)->__invoke($event);

        static::assertSame('America/New_York', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
        static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
            'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
        ]));
    }
}
