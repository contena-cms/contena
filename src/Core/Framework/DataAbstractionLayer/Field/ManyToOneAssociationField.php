<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;

/**
 * @codeCoverageIgnore
 */
class ManyToOneAssociationField extends AssociationField
{
    final public const PRIORITY = 80;

    public function __construct(
        string $propertyName,
        protected string $storageName,
        string $referenceClass,
        string $referenceField = 'id',
        bool $autoload = false,
    ) {
        parent::__construct($propertyName);

        $this->referenceClass = $referenceClass;
        $this->referenceField = $referenceField;
        $this->autoload = $autoload;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }

    protected function getSerializerClass(): string
    {
        return ManyToOneAssociationFieldSerializer::class;
    }

    protected function getResolverClass(): ?string
    {
        return ManyToOneAssociationFieldResolver::class;
    }
}
