<?php

declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * Collection of {@see CookieEntry} indexed by the entry's cookie name
 *
 * @extends Collection<CookieEntry>
 */
class CookieEntryCollection extends Collection
{
    public function set($key, $element): void
    {
        parent::set($element->cookie, $element);
    }

    public function add($element): void
    {
        $this->validateType($element);

        parent::set($element->cookie, $element);
    }

    public function getApiAlias(): string
    {
        return 'cookie_entry_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return CookieEntry::class;
    }
}
