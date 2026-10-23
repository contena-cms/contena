<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogReview;

use Contena\Core\System\User\UserDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Content\Blog\Channel\Review\BlogReviewSaveRouteTest
 */
class BlogReviewDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_review';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogReviewCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogReviewEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogReviewHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $externalUser = (new StringField('external_user', 'externalUser'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('External admin_user name.');
        $points = new FloatField('points', 'points');

        // @deprecated tag:v6.8.0 - externalUser and points will be required unconditionally, points will only accept values from 1 to 5
        if (Feature::isActive('v6.8.0.0')) {
            $externalUser->addFlags(new Required());
            $points = (new FloatField('points', 'points', 1, 5))->addFlags(new Required());
        }

        $points->addFlags(new ApiAware())->setDescription('A floating point number given to rate a blog.');

        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the blog\'s review.'),
            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the blog.'),
            (new ReferenceVersionField(BlogDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('user_id', 'userId', UserDefinition::class))->setDescription('Unique identity of the user.'),
            (new FkField('channel_id', 'channelId', ChannelDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the sales channel.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the language.'),
            $externalUser,
            (new StringField('external_email', 'externalEmail'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('External admin_user email address.'),
            (new StringField('title', 'title'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING))->setDescription('Title of blog review.'),
            (new LongTextField('content', 'content'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING))->setDescription('Short description or subject of the project review.'),
            $points,
            (new BoolField('status', 'status'))->addFlags(new ApiAware())->setDescription('When status is set, the rating is made visible.'),
            (new LongTextField('comment', 'comment'))->addFlags(new ApiAware())->setDescription('Detailed review about the blog.'),
            (new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
