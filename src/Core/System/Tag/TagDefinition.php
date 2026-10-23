<?php declare(strict_types=1);

namespace Contena\Core\System\Tag;

use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\User\Aggregate\UserTag\UserTagDefinition;
use Contena\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
class TagDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'tag';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TagCollection::class;
    }

    public function getEntityClass(): string
    {
        return TagEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING), new ApiAware()),

            // reverse side of the associations, not available in sales-channel-api
            (new ManyToManyAssociationField('blogs', BlogDefinition::class, BlogTagDefinition::class, 'tag_id', 'blog_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('media', MediaDefinition::class, MediaTagDefinition::class, 'tag_id', 'media_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('categories', CategoryDefinition::class, CategoryTagDefinition::class, 'tag_id', 'category_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('users', UserDefinition::class, UserTagDefinition::class, 'tag_id', 'user_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('landingPages', LandingPageDefinition::class, LandingPageTagDefinition::class, 'tag_id', 'landing_page_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('rules', RuleDefinition::class, RuleTagDefinition::class, 'tag_id', 'rule_id'))->addFlags(new CascadeDelete()),
        ]);

        return $collection;
    }
}
