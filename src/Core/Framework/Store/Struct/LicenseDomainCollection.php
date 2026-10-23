<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<LicenseDomainStruct>
 */
class LicenseDomainCollection extends Collection
{
    public function add($element): void
    {
        $this->validateType($element);

        $this->elements[$element->getDomain()] = $element;
    }

    public function set($key, $element): void
    {
        parent::set($element->getDomain(), $element);
    }

    public function getApiAlias(): string
    {
        return 'store_license_domain_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return LicenseDomainStruct::class;
    }
}
