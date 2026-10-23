<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogFeatureSetTranslation;

use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogFeatureSetTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = BlogFeatureSetDefinition::ENTITY_NAME . '_translation';

    public function getCollectionClass(): string
    {
        return BlogFeatureSetTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogFeatureSetTranslationEntity::class;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return BlogFeatureSetDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('description', 'description'))->addFlags(new ApiAware()),
        ]);
    }
}
