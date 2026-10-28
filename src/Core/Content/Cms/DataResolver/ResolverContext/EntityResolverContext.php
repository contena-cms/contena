<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataResolver\ResolverContext;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class EntityResolverContext extends ResolverContext
{
    public function __construct(
        SalesChannelContext $context,
        Request $request,
        private readonly EntityDefinition $definition,
        private readonly Entity $entity
    ) {
        parent::__construct($context, $request);
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }
}
