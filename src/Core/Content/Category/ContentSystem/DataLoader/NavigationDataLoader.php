<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\ContentSystem\DataLoader;

use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads navigation tree data via NavigationLoaderInterface.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<Tree>
 */
class NavigationDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'navigation';

    public function __construct(
        private readonly NavigationLoaderInterface $navigationLoader,
        private readonly NavigationAliasResolver $aliasResolver,
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: null),
            new ConfigKeySpecification('depth', ConfigKeyKind::Literal, 'integer', required: false, hasDefault: true, default: null),
            new ConfigKeySpecification('activeProperty', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'activeId'),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof NavigationLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $rootId = $config->rootId ?? 'main-navigation';
        $rootId = $this->aliasResolver->resolve($rootId, $context);

        // A recognized alias still resolves to itself when the channel has no such category
        // (service and footer navigation are both optional). Passing that on would reach
        // Uuid::fromHexToBytes() in NavigationRoute and abort the whole render.
        if (!Uuid::isValid($rootId)) {
            return ContentDataLoaderResult::notFound();
        }

        // The property carries the "{{categoryId}}" placeholder by default, which stays literal on a
        // layout not rooted on a category. Anything but an id therefore falls back rather than
        // reaching Uuid::fromHexToBytes() in NavigationRoute.
        $activeProperty = $config->activeProperty;
        $activeId = $element->getProperty($activeProperty);

        if (!\is_string($activeId) || !Uuid::isValid($activeId)) {
            $activeId = $rootId;
        }

        $depth = $config->depth ?? $context->getChannel()->getNavigationCategoryDepth();

        $tree = $this->navigationLoader->load($activeId, $context, $rootId, $depth);

        return ContentDataLoaderResult::cachedExternally($tree);
    }
}
