<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 *  Collection of {@see CookieGroup} indexed by the group's technicalName
 *
 * @extends Collection<CookieGroup>
 */
class CookieGroupCollection extends Collection
{
    public function set($key, $element): void
    {
        parent::set($element->getTechnicalName(), $element);
    }

    public function add($element): void
    {
        $this->validateType($element);

        parent::set($element->getTechnicalName(), $element);
    }

    public function getApiAlias(): string
    {
        return 'cookie_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return CookieGroup::class;
    }
}
