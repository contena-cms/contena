<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogFeatureSet;

use Contena\Core\Content\Blog\Aggregate\BlogFeatureSetTranslation\BlogFeatureSetTranslationDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogFeatureSetDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_feature_set';

    final public const TYPE_BLOG_ATTRIBUTE = 'blog';
    final public const TYPE_BLOG_PROPERTY = 'property';
    final public const TYPE_BLOG_CUSTOM_FIELD = 'customField';
    final public const TYPE_BLOG_REFERENCE_PRICE = 'referencePrice';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogFeatureSetCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogFeatureSetEntity::class;
    }

    public function since(): ?string
    {
        return '6.3.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogFeatureSetHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of blog feature set.'),
            new TranslatedField('name'),
            new TranslatedField('description'),
            (new JsonField('features', 'features'))->setDescription('The actual blog features.'),
            (new OneToManyAssociationField('blogs', BlogDefinition::class, 'blog_feature_set_id', 'id'))->addFlags(new SetNullOnDelete(), new ReverseInherited('featureSet')),
            (new TranslationsAssociationField(BlogFeatureSetTranslationDefinition::class, 'blog_feature_set_id'))->addFlags(new Required()),
        ]);
    }
}
