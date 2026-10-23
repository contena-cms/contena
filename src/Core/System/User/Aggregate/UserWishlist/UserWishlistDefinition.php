<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserWishlist;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\User\Aggregate\UserWishlistBlog\UserWishlistBlogDefinition;
use Contena\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
class UserWishlistDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'user_wishlist';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UserWishlistEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UserWishlistCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.4.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return UserDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the user\'s wishlist.'),
            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the user.'),
            (new FkField('channel_id', 'channelId', ChannelDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the sales channel.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),

            (new OneToManyAssociationField('blogs', UserWishlistBlogDefinition::class, 'user_wishlist_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
        ]);
    }
}
