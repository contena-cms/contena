<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class NumberRangeController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractNumberRangeValueGenerator $valueGenerator
    ) {
    }

    #[Cache(mustRevalidate: true)]
    #[Route(path: '/api/_action/number-range/reserve/{type}', name: 'api.action.number-range.reserve', methods: ['GET'])]
    public function reserve(string $type, Context $context, Request $request): JsonResponse
    {
        $generatedNumber = $this->valueGenerator->getValue($type, $context, $request->query->getBoolean('preview'));

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }

    #[Cache(mustRevalidate: true)]
    #[Route(path: '/api/_action/number-range/{numberRangeId}/preview-pattern', name: 'api.action.number-range.preview-pattern-by-id', requirements: ['numberRangeId' => Uuid::VALID_PATTERN], methods: ['GET'])]
    public function previewPatternByNumberRange(string $numberRangeId, Request $request): JsonResponse
    {
        $generatedNumber = $this->valueGenerator->previewPatternByNumberRangeId(
            $numberRangeId,
            $request->query->has('pattern') ? (string) $request->query->get('pattern') : null,
            $request->query->has('start') ? (int) $request->query->get('start') : null
        );

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }
}
