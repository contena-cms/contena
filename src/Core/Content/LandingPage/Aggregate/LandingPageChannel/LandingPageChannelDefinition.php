<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Aggregate\LandingPageChannel;

use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Channel\ChannelDefinition;

class LandingPageChannelDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'landing_page_channel';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isVersionAware(): bool
    {
        return true;
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned landing page channel assignment.'),
            new FkField('landing_page_id', 'landingPageId', LandingPageDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(LandingPageDefinition::class)->addFlags(new PrimaryKey(), new Required()),

            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('landingPage', 'landing_page_id', LandingPageDefinition::class, 'id', false),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
        ]);
    }
}
