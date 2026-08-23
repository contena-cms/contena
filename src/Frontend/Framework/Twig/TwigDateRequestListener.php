<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig;

use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Extension\CoreExtension;

/**
 * @internal
 */
class TwigDateRequestListener
{
    final public const TIMEZONE_COOKIE = 'timezone';

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!\in_array(FrontendRouteScope::ID, $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []), true)) {
            return;
        }

        $timezone = (string) $request->cookies->get(self::TIMEZONE_COOKIE);

        if ($timezone === 'UTC' || !$timezone || !\in_array($timezone, timezone_identifiers_list(), true)) {
            // Default will be UTC @see https://symfony.com/doc/current/reference/configuration/twig.html#timezone
            return;
        }

        $twig = $this->container->get('twig');

        if (!$twig->hasExtension(CoreExtension::class)) {
            return;
        }

        if ($twig instanceof TwigEnvironment) {
            $twig->overrideTimezone($timezone);

            return;
        }

        $twig->getExtension(CoreExtension::class)->setTimezone($timezone);
    }
}
