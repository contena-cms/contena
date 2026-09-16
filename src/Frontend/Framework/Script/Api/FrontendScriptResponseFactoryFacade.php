<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Script\Api;

use Contena\Core\Framework\Script\Api\ScriptResponse;
use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Contena\Frontend\Controller\ScriptController;
use Symfony\Component\Routing\RouterInterface;

/**
 * The `response` service variant available when the Frontend bundle is installed.
 * It adds the `render()` method so frontend scripts can render Twig views.
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
    ) {
        parent::__construct($router);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function render(string $view, array $parameters = []): ScriptResponse
    {
        $inner = $this->scriptController->renderFrontendForScript($view, $parameters);

        return new ScriptResponse($inner, $inner->getStatusCode());
    }
}
