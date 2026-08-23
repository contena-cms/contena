<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer;

class TranslationsAssociationField extends OneToManyAssociationField
{
    final public const int PRIORITY = 90;

    public function __construct(
        string $referenceClass,
        string $referenceField,
        string $propertyName = 'translations',
        string $localField = 'id'
    ) {
        parent::__construct($propertyName, $referenceClass, $referenceField, $localField);
        $this->addFlags(new CascadeDelete());
    }

    public function getLanguageField(): string
    {
        return 'language_id';
    }

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }

    protected function getSerializerClass(): string
    {
        return TranslationsAssociationFieldSerializer::class;
    }
}
