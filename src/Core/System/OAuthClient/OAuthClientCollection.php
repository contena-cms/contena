<?php declare(strict_types=1);

namespace Contena\Core\System\OAuthClient;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OAuthClientEntity>
 *
 * @codeCoverageIgnore
 */
class OAuthClientCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OAuthClientEntity::class;
    }
}
