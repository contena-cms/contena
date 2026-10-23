<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use PHPUnit\Framework\Assert;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * Renders a template through the shared `twig` service inside a Frontend request.
 *
 * `TemplateDataExtension` then resolves real globals instead of caching them empty in the shared environment.
 * The globals are reset before, so a set an earlier test resolved without a request is not reused, and
 * afterwards, so later tests resolve their own.
 *
 * @internal
 */
final class FrontendTwigRenderer
{
    /**
     * @param array<string, mixed> $parameters
     */
    public static function render(ContainerInterface $container, string $template, array $parameters, ChannelContext $context): string
    {
        $twig = $container->get('twig');
        Assert::assertInstanceOf(Environment::class, $twig);
        $requestStack = $container->get('request_stack');
        Assert::assertInstanceOf(RequestStack::class, $requestStack);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        // drop globals an earlier test may have resolved without a request, otherwise Twig hands back that cached set
        $twig->resetGlobals();
        $requestStack->push($request);

        try {
            return $twig->render($template, $parameters);
        } finally {
            $requestStack->pop();
            $twig->resetGlobals();
        }
    }
}
