<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Response\Type\Api;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;
use Contena\Core\Framework\Api\Response\JsonApiResponse;
use Contena\Core\Framework\Api\Response\Type\JsonFactoryBase;
use Contena\Core\Framework\Api\Serializer\JsonApiEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\Api\ResponseFields;
use Contena\Core\System\Channel\Api\StructEncoder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonApiType extends JsonFactoryBase
{
    /**
     * @internal
     */
    public function __construct(
        private readonly JsonApiEncoder $serializer,
        private readonly StructEncoder $structEncoder
    ) {
    }

    public function supports(string $contentType, ContextSource $origin): bool
    {
        return $contentType === 'application/vnd.api+json' && $origin instanceof AdminApiSource;
    }

    public function createDetailResponse(
        Criteria $criteria,
        Entity $entity,
        EntityDefinition $definition,
        Request $request,
        Context $context,
        bool $setLocationHeader = false
    ): Response {
        $headers = [];

        $entityBaseUrl = $this->getEntityBaseUrl($request, $definition);
        if ($setLocationHeader) {
            $headers['Location'] = $entityBaseUrl . '/' . $entity->getUniqueIdentifier();
        }

        $rootNode = [
            'links' => [
                'self' => $request->getUri(),
            ],
        ];

        $response = $this->serializer->encode(
            $criteria,
            $definition,
            $entity,
            $this->getApiBaseUrl($request),
            $rootNode
        );

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, $headers, true);
    }

    public function createListingResponse(
        Criteria $criteria,
        EntitySearchResult $searchResult,
        EntityDefinition $definition,
        Request $request,
        Context $context
    ): Response {
        $baseUrl = $this->getBaseUrl($request);
        $uri = $baseUrl . $request->getPathInfo();

        $rootNode = [
            'links' => $this->createPaginationLinks($searchResult, $uri, $request->query->all()),
        ];

        $rootNode['links']['self'] = $request->getUri();

        $rootNode['meta'] = [
            'totalCountMode' => $searchResult->getCriteria()->getTotalCountMode(),
            'total' => $searchResult->getTotal(),
        ];

        if ($searchResult->getStates() !== []) {
            $rootNode['meta']['states'] = $searchResult->getStates();
        }

        $fields = new ResponseFields(
            RequestParamHelper::get($request, 'includes', []),
            RequestParamHelper::get($request, 'excludes', []),
        );

        $aggregations = [];
        foreach ($searchResult->getAggregations() as $aggregation) {
            $aggregations[$aggregation->getName()] = $this->structEncoder->encode(
                $aggregation,
                $fields
            );
        }

        $rootNode['aggregations'] = $aggregations;

        $response = $this->serializer->encode(
            $criteria,
            $definition,
            $searchResult->getEntities(),
            $this->getApiBaseUrl($request),
            $rootNode
        );

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, [], true);
    }

    protected function getApiBaseUrl(Request $request): string
    {
        return $this->getBaseUrl($request) . '/api';
    }
}
