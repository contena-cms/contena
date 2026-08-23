<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\Channel\Listing\AbstractBlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
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

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<BlogListingResult>
 */
class BlogListingDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'blog_listing';

    public function __construct(
        private readonly AbstractBlogListingRoute $listingRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
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

        if (!$config instanceof BlogListingLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->property ?? 'navigationId';
        $navigationId = $element->getProperty($propertyName);

        if (!\is_string($navigationId)) {
            return ContentDataLoaderResult::notFound();
        }

        $navigationId = u($navigationId)->lower()->toString();

        $criteria = $this->buildCriteria($element, $config);

        $response = $this->listingRoute->load($navigationId, $request, $context, $criteria);
        $result = $response->getResult();

        // BlogListingRoute internally adds cache tags via CacheTagCollector
        return ContentDataLoaderResult::cachedExternally($result);
    }

    /**
     * Element properties can override requirement config associations.
     */
    private function buildCriteria(ContentElement $element, BlogListingLoaderConfig $config): Criteria
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
