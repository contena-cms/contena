<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use Contena\Core\Content\Breadcrumb\Channel\AbstractBreadcrumbRoute;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
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
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
            new ConfigKeySpecification('type', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'blog'),
            new ConfigKeySpecification('referrerCategoryProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: null),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof BreadcrumbLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->property ?? 'entityId';
        $entityId = $element->getProperty($propertyName);

        if (!\is_string($entityId)) {
            return ContentDataLoaderResult::notFound();
        }

        $entityId = u($entityId)->lower()->toString();

        $clonedRequest = clone $request;
        $clonedRequest->attributes->set('id', $entityId);
        $clonedRequest->query->set('type', $config->type);

        if ($config->referrerCategoryProperty !== null) {
            $referrerCategoryId = $element->getProperty($config->referrerCategoryProperty);
            if (\is_string($referrerCategoryId)) {
                $clonedRequest->query->set('referrerCategoryId', u($referrerCategoryId)->lower()->toString());
            }
        }

        $response = $this->breadcrumbRoute->load($clonedRequest, $context);

        return ContentDataLoaderResult::cachedExternally($response->getBreadcrumbCollection());
    }
}
