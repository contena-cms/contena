<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Hookable;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Event\EventData\ArrayType;
use Contena\Core\Framework\Event\EventData\EntityCollectionType;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\ObjectType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\BusinessEventEncoder;
use Contena\Core\Framework\Webhook\Hookable;

/**
 * @internal
 */
class HookableBusinessEvent implements Hookable
{
    private function __construct(
        private readonly FlowEventAware $flowEventAware,
        private readonly BusinessEventEncoder $businessEventEncoder
    ) {
    }

    public static function fromBusinessEvent(
        FlowEventAware $flowEventAware,
        BusinessEventEncoder $businessEventEncoder
    ): self {
        return new self($flowEventAware, $businessEventEncoder);
    }

    public function getName(): string
    {
        return $this->flowEventAware->getName();
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return $this->businessEventEncoder->encode($this->flowEventAware);
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        foreach ($this->flowEventAware->getAvailableData()->toArray() as $dataType) {
            if (!$this->checkPermissionsForDataType($dataType, $permissions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $dataType
     */
    private function checkPermissionsForDataType(array $dataType, AclPrivilegeCollection $permissions): bool
    {
        $type = $dataType['type'] ?? null;
        $data = $dataType['data'] ?? null;
        if ($type === ObjectType::TYPE && \is_array($data) && $data !== []) {
            foreach ($data as $nested) {
                if (!$this->checkPermissionsForDataType($nested, $permissions)) {
                    return false;
                }
            }
        }

        $of = $dataType['of'] ?? null;
        if ($type === ArrayType::TYPE && \is_array($of) && $of !== [] && !$this->checkPermissionsForDataType($of, $permissions)) {
            return false;
        }

        if ($type === EntityType::TYPE || $type === EntityCollectionType::TYPE) {
            $entityDefinitionClass = $dataType['entityClass'] ?? null;
            if (!\is_string($entityDefinitionClass) || !is_a($entityDefinitionClass, EntityDefinition::class, true)) {
                throw FrameworkException::invalidEventData(\sprintf(
                    '"entityClass" value of flow event data type "%s" must be a class string of type "%s"',
                    EntityType::TYPE,
                    EntityDefinition::class
                ));
            }

            $definition = new $entityDefinitionClass();
            if (!$permissions->isAllowed($definition->getEntityName(), AclRoleDefinition::PRIVILEGE_READ)) {
                return false;
            }
        }

        return true;
    }
}
