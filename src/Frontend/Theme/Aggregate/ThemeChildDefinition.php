<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\Aggregate;

use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ThemeChildDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'theme_child';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.4.8.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
        ]);
    }
}
