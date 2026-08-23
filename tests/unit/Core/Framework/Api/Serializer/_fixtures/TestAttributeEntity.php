<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Serializer\_fixtures;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\Member\MemberEntity;

/**
 * @internal
 */
class TestAttributeEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'member')]
    public ?string $memberId = null;

    /**
     * @var array<string, BlogEntity>|null
     */
    #[ManyToMany(entity: 'blog', onDelete: OnDelete::CASCADE)]
    public ?array $blogs = null;

    #[ManyToOne(entity: 'member', onDelete: OnDelete::SET_NULL)]
    public ?MemberEntity $member;
}
