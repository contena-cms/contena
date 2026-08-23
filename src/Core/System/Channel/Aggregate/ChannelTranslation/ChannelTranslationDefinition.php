<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;

class ChannelTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'channel_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ChannelTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ChannelTranslationEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'homeEnabled' => true,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ChannelDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new BoolField('home_enabled', 'homeEnabled')->addFlags(new Required()),
            new StringField('home_name', 'homeName'),
            new StringField('home_meta_title', 'homeMetaTitle'),
            new StringField('home_meta_description', 'homeMetaDescription'),
            new StringField('home_keywords', 'homeKeywords'),
            new CustomFields()->addFlags(new ApiAware()),
        ]);

        return $fields;
    }
}
