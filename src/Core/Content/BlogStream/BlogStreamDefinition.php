<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream;

use Contena\Core\Content\Blog\Aggregate\BlogStreamMapping\BlogStreamMappingDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterDefinition;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamTranslation\BlogStreamTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @codeCoverageIgnore
 */
class BlogStreamDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_stream';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getDefaults(): array
    {
        return [
            'internal' => false,
            'displayAsGroup' => true,
        ];
    }

    public function getCollectionClass(): string
    {
        return BlogStreamCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogStreamEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogStreamHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of blog stream.'),
            (new JsonField('api_filter', 'apiFilter'))->addFlags(new WriteProtected())->setDescription('Internal field.'),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected())->setDescription('When the boolean value is `true`, the BlogStream is no more available for usage.'),

            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new BoolField('internal', 'internal'))->addFlags(new ApiAware())->setDescription('When the boolean value is `true` indicating that it is for internal use only and will not appear in blog stream listings.'),
            (new BoolField('display_as_group', 'displayAsGroup'))->addFlags(new ApiAware())->setDescription('When enabled, matching variants are grouped according to the blog presentation setting. Disable to show them individually.'),
            (new TranslationsAssociationField(BlogStreamTranslationDefinition::class, 'blog_stream_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('filters', BlogStreamFilterDefinition::class, 'blog_stream_id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('categories', CategoryDefinition::class, 'blog_stream_id'),

            (new ManyToManyAssociationField('blogs', BlogDefinition::class, BlogStreamMappingDefinition::class, 'blog_stream_id', 'blog_id'))->addFlags(new CascadeDelete())->setDescription('Blogs this blog stream includes'),
        ]);
    }
}
