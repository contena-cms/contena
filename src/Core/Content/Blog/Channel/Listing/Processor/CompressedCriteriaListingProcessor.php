<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Processor;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * This processor adds support of BlogListingCriteria fields passed in the compressed criteria payload.
 * It should run before any other filter/processor that relies on request parameters.
 * Store API requests are not handled here, the CompressedCriteriaRequestListener already copied all fields of the payload.
 *
 * @internal
 */
class CompressedCriteriaListingProcessor extends AbstractListingProcessor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CompressedCriteriaDecoder $compressedCriteriaDecoder,
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        if (!$request->isMethod(Request::METHOD_GET)) {
            return;
        }

        if (!$request->query->has('_criteria')) {
            return;
        }

        // the listener copied the fields as query-string values, copying them again would overwrite them with their JSON types
        if (\in_array(StoreApiRouteScope::ID, (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []), true)) {
            return;
        }

        $payload = $this->compressedCriteriaDecoder->decode((string) $request->query->get('_criteria'));
        foreach ($payload as $param => $value) {
            if (!\in_array($param, RequestCriteriaBuilder::KNOWN_FIELDS, true)) {
                // adding compressed criteria fields to the request query parameters simulating normal request parameters
                // mutating request is not ideal, but this way existing plugins with custom filters will continue to work without changes
                $request->query->set($param, $value);
            }
        }
    }

    public function process(Request $request, BlogListingResult $result, ChannelContext $context): void
    {
    }
}
