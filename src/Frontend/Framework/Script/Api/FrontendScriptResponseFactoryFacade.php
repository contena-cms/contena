<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Script\Api;

use Contena\Core\Framework\Script\Api\ScriptResponse;
use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * The `response` service variant available when the Frontend bundle is installed.
 * It adds the `render()` method on top of the core `response` service so frontend scripts can render Twig views.
 *
 * @internal
 */
class FrontendScriptResponseFactoryFacade extends ScriptResponseFactoryFacade
{
    /**
     * @internal
     */
    public function __construct(
        RouterInterface $router,
        private readonly ScriptController $scriptController,
        ChannelContext $channelContext,
    ) {
        parent::__construct($router, null, $channelContext);
    }

    /**
     * The `render()` method allows you to render a twig view with the parameters you provide and create a FrontendResponse.
     *
     * Note that the `render()` method will throw an exception if it is called from outside a `ChannelContext` (e.g. from an `/api` route).
     *
     * @param string $view The name of the twig template you want to render e.g. `@Frontend/frontend/page/content/detail.html.twig`
     * @param array<string, mixed> $parameters The parameters you want to pass to the template, ensure that you pass the `page` parameter from the hook to the templates.
     *
     * @return ScriptResponse The created response object with the rendered template as content, remember to assign it to the hook with `hook.setResponse()`.
     *
     * @example frontend-render/script.twig 3 Fetch a blog, add it to the page and return a rendered response.
     */
    public function render(string $view, array $parameters = []): ScriptResponse
    {
        $inner = $this->scriptController->renderFrontendForScript($view, $parameters);

        return new ScriptResponse($inner, $inner->getStatusCode());
    }
}
