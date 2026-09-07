<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Suggest\AbstractBlogSuggestRoute;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
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
class BlogSuggestDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'blog_suggest';

    public function __construct(
        private readonly AbstractBlogSuggestRoute $suggestRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('searchTermProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'searchTerm'),
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
        $searchTerm = $inputs->stringOrNull('searchTermProperty');

        if ($searchTerm === null || $searchTerm === '') {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = $this->buildCriteria($inputs);

        $searchRequest = new Request();
        $searchRequest->request->set('search', $searchTerm);

        // Any ContenaHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: a stored term of "0" survives the empty-string check above but fails
        // BlogSuggestRoute's falsy check, throwing BlogException or RoutingException depending on the
        // v6.8.0.0 flag, and a default sorting naming a deleted sorting entity surfaces as
        // BlogException::sortingNotFoundException() out of SortingListingProcessor.
        try {
            $response = $this->suggestRoute->load($searchRequest, $context, $criteria);
        } catch (ContenaHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($response->getListingResult());
    }

    private function buildCriteria(LoaderInputs $inputs): Criteria
    {
        $criteria = new Criteria();

        foreach ($inputs->stringList('associations') as $association) {
            $criteria->addAssociation($association);
        }

        return $criteria;
    }
}
