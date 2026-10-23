<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream\Service;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\BlogStream\BlogStreamCollection;
use Contena\Core\Content\BlogStream\BlogStreamEntity;
use Contena\Core\Content\BlogStream\BlogStreamException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Contena\Core\Framework\Feature;

class BlogStreamBuilder extends AbstractBlogStreamBuilder implements BlogStreamBuilderInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogStreamCollection> $repository
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
     * @deprecated tag:v6.8.0 - Will be removed, use AbstractBlogStreamBuilder::enrichCriteria instead.
     */
    public function buildFilters(string $id, Context $context): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'AbstractBlogStreamBuilder::enrichCriteria')
        );

        return $this->parseFilters($this->loadStream($id, $context), $id);
    }

    private function loadStream(string $id, Context $context): BlogStreamEntity
    {
        $criteria = new Criteria([$id]);

        $stream = $this->repository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);

        if (!$stream) {
            throw BlogStreamException::blogStreamNotFound($id);
        }

        return $stream;
    }

    /**
     * @return list<Filter>
     */
    private function parseFilters(BlogStreamEntity $stream, string $id): array
    {
        $data = $stream->getApiFilter();
        if (!$data) {
            // Empty api_filter ([]) on a valid stream means all filters were removed; a broken/invalid
            // stream (api_filter null or invalid) stays a NoFilterException so callers can tell them apart.
            if ($data === [] && !$stream->isInvalid()) {
                throw BlogStreamException::emptyBlogStream($id);
            }

            throw BlogStreamException::noFilters($id);
        }

        $filters = [];
        $exception = new SearchRequestException();

        foreach ($data as $filter) {
            $filters[] = QueryStringParser::fromArray($this->blogDefinition, $filter, $exception, '');
        }

        return $filters;
    }
}
