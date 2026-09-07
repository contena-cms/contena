<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use Contena\Core\Content\Breadcrumb\Channel\AbstractBreadcrumbRoute;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<BreadcrumbCollection>
 */
class BreadcrumbDataLoader extends AbstractContentDataLoader
{
    public const string SOURCE = 'breadcrumb';

    public function __construct(
        private readonly AbstractBreadcrumbRoute $breadcrumbRoute
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'entityId'),
            new ConfigKeySpecification('type', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'blog'),
            new ConfigKeySpecification('referrerCategoryProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $entityId = $inputs->stringOrNull('property');

        if ($entityId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $entityId = u($entityId)->lower()->toString();

        // An unsubstituted placeholder such as "{{blogId}}" passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) instead of reaching
        // Uuid::fromHexToBytes() in the DAL id lookups behind CategoryBreadcrumbBuilder. BreadcrumbRoute
        // declares the same domain on the HTTP side, requirements: ['id' => '[0-9a-f]{32}'].
        if (!Uuid::isValid($entityId)) {
            return ContentDataLoaderResult::notFound();
        }

        $clonedRequest = clone $request;
        $clonedRequest->attributes->set('id', $entityId);
        $clonedRequest->query->set('type', $inputs->string('type'));

        $referrerCategoryId = $inputs->stringOrNull('referrerCategoryProperty');

        // The referrer is optional (its key declares default: null): unconfigured, the query parameter stays
        // unset, which is BreadcrumbRoute's own default. A resolved referrer is an entity id like any other
        // and carries the same guard.
        if ($referrerCategoryId !== null) {
            $referrerCategoryId = u($referrerCategoryId)->lower()->toString();

            if (!Uuid::isValid($referrerCategoryId)) {
                return ContentDataLoaderResult::notFound();
            }

            $clonedRequest->query->set('referrerCategoryId', $referrerCategoryId);
        }

        // Any ContenaHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: BreadcrumbRoute reaches CategoryBreadcrumbBuilder, which throws
        // BreadcrumbException::categoryNotFoundForBlog() and BreadcrumbException::blogNotFound()
        // (a BlogNotFoundException the route catches only on the blog branch).
        try {
            $response = $this->breadcrumbRoute->load($clonedRequest, $context);
        } catch (ContenaHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cachedExternally($response->getBreadcrumbCollection());
    }
}
