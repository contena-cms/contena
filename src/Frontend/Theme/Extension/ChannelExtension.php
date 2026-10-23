<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Extension;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeChannelDefinition;
use Contena\Frontend\Theme\ThemeDefinition;

class ChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new ManyToManyAssociationField('themes', ThemeDefinition::class, ThemeChannelDefinition::class, 'channel_id', 'theme_id')
        );
    }

    public function getEntityName(): string
    {
        return ChannelDefinition::ENTITY_NAME;
    }
}
