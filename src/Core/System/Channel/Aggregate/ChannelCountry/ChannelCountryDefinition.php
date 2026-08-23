<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelCountry;

use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Country\CountryDefinition;

class ChannelCountryDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'channel_country';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned channel country assignment.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new FkField('country_id', 'countryId', CountryDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false),
        ]);
    }
}
