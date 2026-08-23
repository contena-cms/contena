<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<BlogListingResult>
 */
class BlogSearchDataLoader extends AbstractContentDataLoader
{
    public const string SOURCE = 'blog_search';

    public function __construct(
        private readonly AbstractBlogSearchRoute $searchRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('searchTermProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof BlogSearchLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->searchTermProperty ?? 'searchTerm';
        $searchTerm = $element->getProperty($propertyName);

        if (!\is_string($searchTerm) || $searchTerm === '') {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($element, $config);

        $searchRequest = new Request();
        $searchRequest->request->set('search', $searchTerm);

        $response = $this->searchRoute->load($searchRequest, $context, $criteria);

        return ContentDataLoaderResult::cachedExternally($response->getListingResult());
    }

    private function buildCriteria(ContentElement $element, BlogSearchLoaderConfig $config): Criteria
    {
        $criteria = new Criteria();

        foreach ($config->associations as $association) {
            $criteria->addAssociation($association);
        }

        $elementAssociations = $element->getProperty('associations');
        if (\is_array($elementAssociations)) {
            foreach ($elementAssociations as $association) {
                if (\is_string($association)) {
                    $criteria->addAssociation($association);
                }
            }
        }

        return $criteria;
    }
}
