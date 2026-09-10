<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\CmsBlock;

use Contena\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition;
use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class AppCmsBlockDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_cms_block';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppCmsBlockCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppCmsBlockEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.2.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of app\'s CMS block.'),
            new StringField('name', 'name')->addFlags(new Required())->setDescription('Name of app\'s CMS block.'),
            new JsonField('block', 'block')->addFlags(new Required())->setDescription('CMS block.'),
            new LongTextField('template', 'template')->addFlags(new Required(), new AllowHtml())->setDescription('Template that defines app CMS block.'),
            new LongTextField('styles', 'styles')->addFlags(new Required())->setDescription('Parameter that relates to the styles or formatting within CMS block.'),
            new TranslatedField('label'),
            new TranslationsAssociationField(AppCmsBlockTranslationDefinition::class, 'app_cms_block_id')->addFlags(new Required()),
            new FkField('app_id', 'appId', AppDefinition::class)->addFlags(new Required())->setDescription('Unique identity of app.'),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
