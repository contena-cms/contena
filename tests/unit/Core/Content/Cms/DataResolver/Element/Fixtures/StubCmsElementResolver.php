<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver\Element\Fixtures;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 */
class StubCmsElementResolver extends AbstractCmsElementResolver
{
    public function runResolveEntityValue(?Entity $entity, string $path): mixed
    {
        return $this->resolveEntityValue($entity, $path);
    }

    public function runResolveEntityValueToString(?Entity $entity, string $path, EntityResolverContext $resolverContext): string
    {
        return $this->resolveEntityValueToString($entity, $path, $resolverContext);
    }

    public function getType(): string
    {
        return 'abstract-test';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
    }
}
