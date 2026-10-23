<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\EntityProtection;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<EntityProtection>
 */
class EntityProtectionCollection extends Collection
{
    /**
     * @param EntityProtection $element
     */
    public function add($element): void
    {
        $this->set($element::class, $element);
    }

    /**
     * @param string|int $key
     * @param EntityProtection $element
     */
    public function set($key, $element): void
    {
        parent::set($element::class, $element);
    }

    public function getApiAlias(): string
    {
        return 'dal_protection_collection';
    }
}
