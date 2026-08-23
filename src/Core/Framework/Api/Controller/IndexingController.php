<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class IndexingController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityIndexerRegistry $registry,
    ) {
    }

    #[Route(path: '/api/_action/indexing', name: 'api.action.indexing', methods: ['POST'])]
    public function indexing(Request $request, Context $context): JsonResponse
    {
        $indexingSkips = array_filter(explode(',', (string) $request->headers->get(PlatformRequest::HEADER_INDEXING_SKIP, '')));

        $this->registry->sendIndexingMessage([], $indexingSkips, context: $context);

        return new JsonResponse();
    }

    #[Route(path: '/api/_action/indexing/{indexer}', name: 'api.action.indexing.iterate', methods: ['POST'])]
    public function iterate(string $indexer, Request $request, Context $context): JsonResponse
    {
        $indexingSkips = array_filter(explode(',', (string) $request->headers->get(PlatformRequest::HEADER_INDEXING_SKIP, '')));

        if (!$request->request->has('offset')) {
            throw ApiException::missingRequestParameter('offset');
        }

        $indexer = $this->registry->getIndexer($indexer);

        $offset = ['offset' => RequestParamHelper::get($request, 'offset')];
        $message = $indexer ? $indexer->iterate($offset) : null;

        if ($message === null) {
            return new JsonResponse(['finish' => true]);
        }

        $message->setContext($context);
        $message->addSkip(...$indexingSkips);

        if ($indexer) {
            $indexer->handle($message);
        }

        return new JsonResponse(['finish' => false, 'offset' => $message->getOffset()]);
    }
}
