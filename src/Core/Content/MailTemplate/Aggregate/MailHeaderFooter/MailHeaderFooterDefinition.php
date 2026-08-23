<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter;

use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class MailHeaderFooterDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'mail_header_footer';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MailHeaderFooterEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of mail\'s header and footer component.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned mail header and footer.'),
            new BoolField('system_default', 'systemDefault')->addFlags(new ApiAware()),

            // translatable fields->setDescription('Unused field. To be removed in future.')
            new TranslatedField('name')->addFlags(new ApiAware()),
            new TranslatedField('description')->addFlags(new ApiAware()),
            new TranslatedField('headerHtml')->addFlags(new ApiAware()),
            new TranslatedField('headerPlain')->addFlags(new ApiAware()),
            new TranslatedField('footerHtml')->addFlags(new ApiAware()),
            new TranslatedField('footerPlain')->addFlags(new ApiAware()),

            new TranslationsAssociationField(MailHeaderFooterTranslationDefinition::class, 'mail_header_footer_id')->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
