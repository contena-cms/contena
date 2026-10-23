<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogComment;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\User\UserDefinition;

/**
 * A reader comment on a blog article.
 *
 * @codeCoverageIgnore
 */
class BlogCommentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_comment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogCommentCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogCommentEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the comment.'),
            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new Required())->setDescription('Reference to the commented blog article.'),
            new ReferenceVersionField(BlogDefinition::class, 'blog_version_id'),

            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new ApiAware())->setDescription('Reference to the logged in user, if the comment was written by a registered user.'),
            (new FkField('channel_id', 'channelId', ChannelDefinition::class))->addFlags(new Required(), new ApiAware())->setDescription('Reference to the channel the comment was written in.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required(), new ApiAware())->setDescription('Reference to the language the comment was written in.'),

            (new StringField('external_user', 'externalUser'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Display name of an anonymous commenter.'),
            (new StringField('external_email', 'externalEmail'))->setDescription('Email of an anonymous commenter.'),
            (new StringField('title', 'title'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Optional headline of the comment.'),
            (new LongTextField('content', 'content'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('The comment text.'),
            (new BoolField('status', 'status'))->setDescription('Whether the comment is visible on the article.'),

            new CreatedAtField(),
            new UpdatedAtField(),

            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
        ]);
    }
}
