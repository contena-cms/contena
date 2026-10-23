<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataResolver\ResolverContext;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class EntityResolverContext extends ResolverContext
{
    public function __construct(
        ChannelContext $context,
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
