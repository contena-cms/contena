<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Channel;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Snippet\Extension\SnippetRouteExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SnippetRoute extends AbstractSnippetRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ChannelSnippetLoader $snippetLoader,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/snippet',
        name: 'store-api.snippet',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, ChannelContext $context): SnippetRouteResponse
    {
        return $this->extensions->publish(
            name: SnippetRouteExtension::NAME,
            extension: new SnippetRouteExtension($request, $context),
            function: $this->_load(...),
        );
    }

    public function getDecorated(): AbstractSnippetRoute
    {
        throw new DecorationPatternException(self::class);
    }

    private function _load(Request $request, ChannelContext $context): SnippetRouteResponse
    {
        $results = $this->snippetLoader->load(
            $this->parseList($request->query->getString('languageIds')),
            $this->parseList($request->query->getString('prefixes')),
            $context
        );

        foreach ($results as $result) {
            $this->cacheTagCollector->addTag(Translator::tag($result->snippetSetId));
        }

        $response = new SnippetRouteResponse(new SnippetSetResultList(...$results));

        $hashes = array_map(static fn (SnippetSetResult $result): string => $result->hash, $results);
        // a single set keeps its own hash as etag, multiple sets are combined into one
        $etag = \count($hashes) === 1 ? implode('', $hashes) : Hasher::hash(implode('-', $hashes));

        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }

    /**
     * @return list<string>
     */
    private function parseList(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== ''
        ));
    }
}
