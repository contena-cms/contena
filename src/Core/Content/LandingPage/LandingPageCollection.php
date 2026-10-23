<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LandingPageEntity>
 *
 * @codeCoverageIgnore
 */
class LandingPageCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LandingPageEntity::class;
    }
}
