<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;

class OneToOneAssociationField extends AssociationField
{
    final public const int PRIORITY = 80;

    public function __construct(
        string $propertyName,
        protected string $storageName,
        string $referenceField,
        string $referenceClass,
        bool $autoload = true,
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
        return OneToOneAssociationFieldSerializer::class;
    }

    protected function getResolverClass(): ?string
    {
        return ManyToOneAssociationFieldResolver::class;
    }
}
