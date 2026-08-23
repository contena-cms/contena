<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate;

use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\WasModifiedByUserField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailTemplateDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'mail_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MailTemplateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MailTemplateCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned mail template.'),

            new FkField('mail_template_type_id', 'mailTemplateTypeId', MailTemplateTypeDefinition::class)->addFlags(new Required()),
            new BoolField('system_default', 'systemDefault')->addFlags(new ApiAware()),
            new WasModifiedByUserField(),

            // translatable fields
            new TranslatedField('senderName')->addFlags(new ApiAware()),
            new TranslatedField('description')->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('subject')->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('contentHtml')->addFlags(new ApiAware()),
            new TranslatedField('contentPlain')->addFlags(new ApiAware()),
            new TranslatedField('customFields')->addFlags(new ApiAware()),

            new TranslationsAssociationField(MailTemplateTranslationDefinition::class, 'mail_template_id')->addFlags(new ApiAware(), new Required()),
            new ManyToOneAssociationField('mailTemplateType', 'mail_template_type_id', MailTemplateTypeDefinition::class, 'id')
                ->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            new OneToManyAssociationField('media', MailTemplateMediaDefinition::class, 'mail_template_id', 'id')->addFlags(new ApiAware(), new CascadeDelete()),
        ]);

        return $fields;
    }
}
