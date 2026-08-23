<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Contena\Core\System\SystemConfig\SystemConfigDefinition;

/**
 * @internal
 */
class SystemConfigExtension extends EntityExtension
{
    public function extendProtections(EntityProtectionCollection $protections): void
    {
        $protections->add(new WriteProtection(Context::SYSTEM_SCOPE, Context::USER_SCOPE));
    }

    public function getEntityName(): string
    {
        return SystemConfigDefinition::ENTITY_NAME;
    }
}
