<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Template;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<TemplateEntity>
 *
 * @codeCoverageIgnore
 */
class TemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TemplateEntity::class;
    }
}
