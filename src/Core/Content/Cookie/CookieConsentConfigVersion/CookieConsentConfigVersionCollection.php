<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\CookieConsentConfigVersion;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CookieConsentConfigVersionEntity>
 */
class CookieConsentConfigVersionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CookieConsentConfigVersionEntity::class;
    }
}
