<?php declare(strict_types=1);

namespace Contena\Administration\Service;

use Contena\Administration\Framework\Search\CriteriaCollection;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

class AdminSearcher
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionInstanceRegistry $definitionRegistry)
    {
    }

    /**
     * @return array<array-key, array{data: EntityCollection<covariant Entity>, total: int}>
     */
    public function search(CriteriaCollection $entities, Context $context): array
    {
        $result = [];

        foreach ($entities as $entityName => $criteria) {
            if (!$this->definitionRegistry->has($entityName)) {
                continue;
            }

            if (!$context->isAllowed($entityName . ':' . AclRoleDefinition::PRIVILEGE_READ)) {
                continue;
            }

            $repository = $this->definitionRegistry->getRepository($entityName);
            $collection = $repository->search($criteria, $context);

            $result[$entityName] = [
                'data' => $collection->getEntities(),
                'total' => $collection->getTotal(),
            ];
        }

        return $result;
    }
}
