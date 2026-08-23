<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Contena\Core\Content\MailTemplate\MailTemplateDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'mail_template_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MailTemplateTypeEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MailTemplateTypeCollection::class;
    }

    public function getTranslationDefinitionClass(): ?string
    {
        return MailTemplateTypeTranslationDefinition::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of mail template type.'),

            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new StringField('technical_name', 'technicalName')->addFlags(new ApiAware(), new Required())->setDescription('Technical name of mail template.'),
            new JsonField('available_entities', 'availableEntities')->setDescription('Defines  which entities are compatible with a given mail template type, ensuring that the right templates can be used for the appropriate purposes within the system'),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new TranslationsAssociationField(MailTemplateTypeTranslationDefinition::class, 'mail_template_type_id')->addFlags(new ApiAware(), new Required()),
            new OneToManyAssociationField('mailTemplates', MailTemplateDefinition::class, 'mail_template_type_id')->addFlags(new SetNullOnDelete()),
        ]);
    }
}
