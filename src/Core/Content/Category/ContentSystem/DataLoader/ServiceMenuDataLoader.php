<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\ContentSystem\DataLoader;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<CategoryCollection>
 */
class ServiceMenuDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'service_menu';

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
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof ServiceMenuLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $alias = $config->rootId ?? 'service-navigation';
        $rootId = $this->aliasResolver->resolve($alias, $context);

        // If the alias was not resolved (service category not configured), return empty collection
        if ($rootId === $alias && $alias === 'service-navigation') {
            return ContentDataLoaderResult::cachedExternally(new CategoryCollection());
        }

        try {
            $tree = $this->navigationLoader->load($rootId, $context, $rootId, 1);
        } catch (CategoryNotFoundException) {
            return ContentDataLoaderResult::notFound();
        }

        $categories = new CategoryCollection(array_map(
            static fn (TreeItem $treeItem) => $treeItem->getCategory(),
            $tree->getTree()
        ));

        return ContentDataLoaderResult::cachedExternally($categories);
    }
}
