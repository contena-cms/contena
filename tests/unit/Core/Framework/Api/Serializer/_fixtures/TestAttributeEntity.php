<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Serializer\_fixtures;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 */
class TestAttributeEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'user')]
    public ?string $userId = null;

    /**
     * @var array<string, BlogEntity>|null
     */
    #[ManyToMany(entity: 'blog', onDelete: OnDelete::CASCADE)]
    public ?array $blogs = null;

    #[ManyToOne(entity: 'user', onDelete: OnDelete::SET_NULL)]
    public ?UserEntity $user;
}
