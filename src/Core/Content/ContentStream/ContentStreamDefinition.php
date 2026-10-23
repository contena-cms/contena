<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\ContentStreamMapping\ContentStreamMappingDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterDefinition;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamTranslation\ContentStreamTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class ContentStreamDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'content_stream';

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
        return ContentStreamCollection::class;
    }

    public function getEntityClass(): string
    {
        return ContentStreamEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ContentStreamHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of blog stream.'),
            (new JsonField('api_filter', 'apiFilter'))->addFlags(new WriteProtected())->setDescription('Internal field.'),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected())->setDescription('When the boolean value is `true`, the ContentStream is no more available for usage.'),

            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new BoolField('internal', 'internal'))->addFlags(new ApiAware())->setDescription('When the boolean value is `true` indicating that it is for internal use only and will not appear in blog stream listings.'),
            (new BoolField('display_as_group', 'displayAsGroup'))->addFlags(new ApiAware())->setDescription('When enabled, matching variants are grouped according to the blog presentation setting. Disable to show them individually.'),
            (new TranslationsAssociationField(ContentStreamTranslationDefinition::class, 'content_stream_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('filters', ContentStreamFilterDefinition::class, 'content_stream_id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('blogCrossSellings', BlogCrossSellingDefinition::class, 'content_stream_id'),
            new OneToManyAssociationField('categories', CategoryDefinition::class, 'content_stream_id'),

            (new ManyToManyAssociationField('blogs', BlogDefinition::class, ContentStreamMappingDefinition::class, 'content_stream_id', 'blog_id'))->addFlags(new CascadeDelete())->setDescription('Blogs this blog stream includes'),
        ]);
    }
}
