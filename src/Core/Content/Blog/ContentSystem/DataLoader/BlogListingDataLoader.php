<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\Channel\Listing\AbstractBlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
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
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'navigationId'),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'associations', referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $navigationId = $inputs->stringOrNull('property');

        if ($navigationId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $navigationId = u($navigationId)->lower()->toString();

        // An unsubstituted placeholder such as "{{categoryId}}" passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) instead of reaching
        // Uuid::fromHexToBytes() when BlogListingRoute searches the category by id.
        if (!Uuid::isValid($navigationId)) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($inputs);

        // Any ContenaHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: a category assigned to a deleted or filterless blog stream surfaces as
        // EntityNotFoundException or NoFilterException out of BlogStreamBuilder.
        try {
            $response = $this->listingRoute->load($navigationId, $request, $context, $criteria);
        } catch (ContenaHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // BlogListingRoute internally adds cache tags via CacheTagCollector
        return ContentDataLoaderResult::cachedExternally($response->getResult());
    }

    /**
     * The `associationOverride` reference is already folded into `associations` by the input resolver.
     */
    private function buildCriteria(LoaderInputs $inputs): Criteria
    {
        $criteria = new Criteria();

        foreach ($inputs->stringList('associations') as $association) {
            $criteria->addAssociation($association);
        }

        return $criteria;
    }
}
