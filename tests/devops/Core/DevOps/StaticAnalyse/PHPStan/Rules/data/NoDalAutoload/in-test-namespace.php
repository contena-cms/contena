<?php declare(strict_types=1);

namespace Contena\Core\Test\Entity;

use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

/**
 * @internal
 */
class Definition
{
    public const string ENTITY_NAME = 'my-entity';

    public function __construct()
    {
        new OneToOneAssociationField('prop', 'storageName', 'referenceField', 'referenceClass', true);
        new ManyToOneAssociationField('prop', 'storageName', 'referenceClass', 'referenceField', true);
    }
}
