<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\CookieConsentLog;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CookieConsentLogEntity>
 */
class CookieConsentLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CookieConsentLogEntity::class;
    }
}
