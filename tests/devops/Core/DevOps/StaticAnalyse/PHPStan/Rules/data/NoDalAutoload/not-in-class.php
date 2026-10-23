<?php declare(strict_types=1);

use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

new OneToOneAssociationField('prop', 'storageName', 'referenceField', 'referenceClass', true);
new ManyToOneAssociationField('prop', 'storageName', 'referenceClass', 'referenceField', true);
