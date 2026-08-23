<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Sorting;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class BlogSortingDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'blog_sorting';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BlogSortingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BlogSortingCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogSortingHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new LockedField(),
            new StringField('url_key', 'key')->addFlags(new ApiAware(), new Required()),
            new IntField('priority', 'priority')->addFlags(new ApiAware(), new Required()),
            new BoolField('active', 'active')->addFlags(new Required()),
            new JsonField('fields', 'fields')->addFlags(new Required()),
            new TranslatedField('label')->addFlags(new ApiAware()),
            new TranslationsAssociationField(BlogSortingTranslationDefinition::class, 'blog_sorting_id')->addFlags(new Inherited(), new Required()),
        ]);
    }
}
