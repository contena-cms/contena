<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelDomain;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;

class ChannelDomainDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'channel_domain';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ChannelDomainEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ChannelDomainCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ChannelDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of channel domain.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned channel domain.'),

            new StringField('url', 'url', 255)->addFlags(new ApiAware(), new Required())->setDescription('URL of the channel domain.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of channel.'),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language used.'),
            new FkField('snippet_set_id', 'snippetSetId', SnippetSetDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of snippet set.'),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('snippetSet', 'snippet_set_id', SnippetSetDefinition::class, 'id', false),
            new OneToOneAssociationField('channelDefaultHreflang', 'id', 'hreflang_default_domain_id', ChannelDefinition::class, false)->addFlags(new ApiAware()),
            new BoolField('hreflang_use_only_locale', 'hreflangUseOnlyLocale')->addFlags(new ApiAware())->setDescription('This is used to toggle the language configurations, say between DE and DE-DE for instance.'),
            new BoolField('is_external_frontend', 'isExternalFrontend')->addFlags(new ApiAware())->setDescription('Whether the domain points to an external (headless) frontend.'),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
