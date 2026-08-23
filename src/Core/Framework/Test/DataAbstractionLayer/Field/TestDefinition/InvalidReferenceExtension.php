<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class InvalidReferenceExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new ReferenceVersionField(ExtendedDefinition::class)
        );
    }

    public function getEntityName(): string
    {
        return 'extendable';
    }
}
