<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\CmsBlockTranslation;

use Contena\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class AppCmsBlockTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_cms_block_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppCmsBlockTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppCmsBlockTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.4.2.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return AppCmsBlockDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
        ]);
    }
}
