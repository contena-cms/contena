<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Adapter\Cache\Http\CacheAttribute;
use Contena\Core\Framework\Adapter\Cache\Http\CacheStore;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Script\Api\ScriptResponseEncoder;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Api\ResponseFields;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Script\Api\FrontendHook;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class ScriptController extends FrontendController
{
    public function __construct(
        private readonly GenericPageLoaderInterface $pageLoader,
        private readonly ScriptResponseEncoder $scriptResponseEncoder,
    ) {
    }

    #[Route(path: '/frontend/script/{hook}', name: 'frontend.script_endpoint', requirements: ['hook' => '.+'], defaults: ['XmlHttpRequest' => true], methods: ['GET', 'POST'])]
    public function execute(string $hook, Request $request, ChannelContext $context): Response
    {
        $hookName = \str_replace('/', '-', $hook);
        $page = $this->pageLoader->load($request, $context);
        $hook = new FrontendHook($hookName, $request->request->all(), $request->query->all(), $page, $context);

        $this->hook($hook);

        $fields = new ResponseFields(
            RequestParamHelper::get($request, 'includes', []),
            RequestParamHelper::get($request, 'excludes', []),
        );

        $response = $hook->getScriptResponse();
        $symfonyResponse = $this->scriptResponseEncoder->encodeToSymfonyResponse(
            $response,
            $fields,
            \str_replace('-', '_', 'frontend_' . $hookName . '_response'),
        );

        if ($response->getCache()->isEnabled()) {
            $cacheAttribute = new CacheAttribute(
                maxAge: $response->getCache()->getClientMaxAge(),
                sMaxAge: $response->getCache()->getSharedMaxAge(),
                policyModifier: $hookName,
            );

            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, $cacheAttribute);
            $symfonyResponse->headers->set(CacheStore::TAG_HEADER, \json_encode($response->getCache()->getCacheTags(), \JSON_THROW_ON_ERROR));
        }

        return $symfonyResponse;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function renderFrontendForScript(string $view, array $parameters = []): Response
    {
        return $this->renderFrontend($view, $parameters);
    }
}
