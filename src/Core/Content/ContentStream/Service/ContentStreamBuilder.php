<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Service;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\ContentStream\ContentStreamCollection;
use Contena\Core\Content\ContentStream\ContentStreamEntity;
use Contena\Core\Content\ContentStream\ContentStreamException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;

class ContentStreamBuilder extends AbstractContentStreamBuilder implements ContentStreamBuilderInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<ContentStreamCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly EntityDefinition $blogDefinition
    ) {
    }

    public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
    {
        $stream = $this->loadStream($id, $context);
        $criteria->addFilter(...$this->parseFilters($stream, $id));

        if (!$stream->isDisplayAsGroup()) {
            $criteria->addState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use AbstractContentStreamBuilder::enrichCriteria instead.
     */
    public function buildFilters(string $id, Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'AbstractContentStreamBuilder::enrichCriteria')
        );

        return $this->parseFilters($this->loadStream($id, $context), $id);
    }

    private function loadStream(string $id, Context $context): ContentStreamEntity
    {
        $criteria = new Criteria([$id]);

        $stream = $this->repository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);

        if (!$stream) {
            throw ContentStreamException::contentStreamNotFound($id);
        }

        return $stream;
    }

    /**
     * @return list<Filter>
     */
    private function parseFilters(ContentStreamEntity $stream, string $id): array
    {
        $data = $stream->getApiFilter();
        if (!$data) {
            // Empty api_filter ([]) on a valid stream means all filters were removed; a broken/invalid
            // stream (api_filter null or invalid) stays a NoFilterException so callers can tell them apart.
            if ($data === [] && !$stream->isInvalid()) {
                throw ContentStreamException::emptyBlogStream($id);
            }

            throw ContentStreamException::noFilters($id);
        }

        $filters = [];
        $exception = new SearchRequestException();

        foreach ($data as $filter) {
            $filters[] = QueryStringParser::fromArray($this->blogDefinition, $filter, $exception, '');
        }

        return $filters;
    }
}
