<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia;

use Contena\Core\Content\MailTemplate\MailTemplateDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Language\LanguageDefinition;

class MailTemplateMediaDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'mail_template_media';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MailTemplateMediaCollection::class;
    }

    public function getEntityClass(): string
    {
        return MailTemplateMediaEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned mail template media assignment.'),
            new FkField('mail_template_id', 'mailTemplateId', MailTemplateDefinition::class)->addFlags(new ApiAware(), new Required()),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new ApiAware(), new Required()),
            new FkField('media_id', 'mediaId', MediaDefinition::class)->addFlags(new ApiAware(), new Required()),
            new IntField('position', 'position')->addFlags(new ApiAware()),
            new ManyToOneAssociationField('mailTemplate', 'mail_template_id', MailTemplateDefinition::class, 'id', false),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false)->addFlags(new ApiAware()),
        ]);
    }
}
