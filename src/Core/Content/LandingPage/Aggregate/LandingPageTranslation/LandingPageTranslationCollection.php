<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LandingPageTranslationEntity>
 */
class LandingPageTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return LandingPageTranslationEntity::class;
    }
}
