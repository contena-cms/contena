<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\AllowEmptyString as AllowEmptyStringAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\AllowHtml as AllowHtmlAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\AutoIncrement;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\CustomFields as CustomFieldsAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Inherited as InheritedAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ListField as ListFieldAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Password;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey as PrimaryKeyAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Protection;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ReferenceVersion;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Required as RequiredAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ReverseInherited as ReverseInheritedAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking as SearchRankingAttr;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Serialized;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\State;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Version;
use Contena\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Contena\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Contena\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AsArray;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\SerializedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\WasModifiedByUserField;
use Contena\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @phpstan-type FieldArray array{
 *     type?: string,
 *     name?: string,
 *     class: class-string<DalField>,
 *     flags: array<string, array{class: string, args?: array<string, string|bool|float|null>|list<string>}>,
 *     translated: bool,
 *     args: list<string|int|false>
 * }
 */
class AttributeEntityCompiler
{
    private const array FIELD_ATTRIBUTES = [
        OneToMany::class,
        ManyToMany::class,
        ManyToOne::class,
        OneToOne::class,
        Translations::class,
        AutoIncrement::class,
        Serialized::class,
        ForeignKey::class,
        Version::class,
        Password::class,
        Field::class,
        State::class,
        ReferenceVersion::class,
        ListFieldAttr::class,
        CustomFieldsAttr::class,
    ];

    private const array ASSOCIATIONS = [
        OneToMany::class,
        ManyToMany::class,
        ManyToOne::class,
        OneToOne::class,
    ];

    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    public function __construct()
    {
        $this->converter = new CamelCaseToSnakeCaseNameConverter();
    }

    /**
     * @param class-string<EntityStruct> $class
     *
     * @return list<array{
     *     type: 'entity'|'mapping',
     *     since?: string|null,
     *     parent: string|null,
     *     entity_class: class-string<EntityStruct>,
     *     entity_name: string,
     *     collection_class?: class-string<EntityCollection<EntityStruct>>,
     *     fields: list<FieldArray>,
     *     source?: string,
     *     reference?: string
     * }>
     */
    public function compile(string $class): array
    {
        $reflection = new \ReflectionClass($class);

        $collection = $reflection->getAttributes(Entity::class);

        if ($collection === []) {
            return [];
        }

        $instance = $collection[0]->newInstance();

        $properties = $reflection->getProperties();

        $definitions = [];

        $fields = [];
        foreach ($properties as $property) {
            $field = $this->parseField($instance->name, $property);

            if ($field === null) {
                continue;
            }

            $fields[] = $field;

            if ($field['type'] === ManyToMany::TYPE) {
                $definitions[] = $this->mapping($instance->name, $property);
            }
        }

        $definitions[] = [
            'type' => 'entity',
            'since' => $instance->since,
            'parent' => $instance->parent,
            'inheritance_aware' => $instance->inheritanceAware,
            'entity_class' => $class,
            'entity_name' => $instance->name,
            'hydrator_class' => $instance->hydratorClass,
            'collection_class' => $instance->collectionClass,
            'fields' => $fields,
        ];

        return $definitions;
    }

    /**
     * @template TClassList of object
     *
     * @param class-string<TClassList> ...$list
     *
     * @return \ReflectionAttribute<TClassList>|null
     */
    private function getAttribute(\ReflectionProperty $property, string ...$list): ?\ReflectionAttribute
    {
        foreach ($list as $attribute) {
            $attribute = $property->getAttributes($attribute);
            if ($attribute !== []) {
                return $attribute[0];
            }
        }

        return null;
    }

    /**
     * @return array{
     *     type: string,
     *     name: string,
     *     class: class-string<DalField>,
     *     flags: array<string, array{class: string, args?: array<string, string|bool|float|null>|list<string>}>,
     *     translated: bool,
     *     args: list<string|int|false>
     * }|null
     */
    private function parseField(string $entity, \ReflectionProperty $property): ?array
    {
        $attribute = $this->getAttribute($property, ...self::FIELD_ATTRIBUTES);

        if (!$attribute) {
            return null;
        }
        $field = $attribute->newInstance();

        $field->nullable = $property->getType()?->allowsNull() ?? true;

        return [
            'type' => $field->type,
            'name' => $property->getName(),
            'class' => $this->getFieldClass($field),
            'flags' => $this->getFlags($field, $property),
            'translated' => $field->translated,
            'args' => $this->getFieldArgs($entity, $field, $property),
        ];
    }

    /**
     * @return class-string<DalField>
     */
    private function getFieldClass(Field $field): string
    {
        if (is_a($field->type, DalField::class, true)) {
            return $field->type;
        }

        return match ($field->type) {
            FieldType::UUID => IdField::class,
            FieldType::TEXT => LongTextField::class,
            FieldType::INT => IntField::class,
            FieldType::FLOAT => FloatField::class,
            FieldType::BOOL => BoolField::class,
            FieldType::ENUM => EnumField::class,
            FieldType::JSON => JsonField::class,
            FieldType::DATETIME => DateTimeField::class,
            FieldType::DATE => DateField::class,
            FieldType::DATE_INTERVAL => DateIntervalField::class,
            FieldType::TIME_ZONE => TimeZoneField::class,
            FieldType::EMAIL => EmailField::class,
            OneToMany::TYPE => OneToManyAssociationField::class,
            OneToOne::TYPE => OneToOneAssociationField::class,
            ManyToOne::TYPE => ManyToOneAssociationField::class,
            ManyToMany::TYPE => ManyToManyAssociationField::class,
            AutoIncrement::TYPE => AutoIncrementField::class,
            Serialized::TYPE => SerializedField::class,
            Password::TYPE => PasswordField::class,
            ForeignKey::TYPE => FkField::class,
            State::TYPE => StateMachineStateField::class,
            Version::TYPE => VersionField::class,
            ReferenceVersion::TYPE => ReferenceVersionField::class,
            Translations::TYPE => TranslationsAssociationField::class,
            CustomFieldsAttr::TYPE => CustomFields::class,
            ListFieldAttr::TYPE => ListField::class,
            default => StringField::class,
        };
    }

    /**
     * @return list<mixed>
     */
    private function getFieldArgs(
        string $entity,
        OneToMany|ManyToMany|ManyToOne|OneToOne|Field|Serialized|AutoIncrement|Password|ListFieldAttr $field,
        \ReflectionProperty $property
    ): array {
        if ($field->column) {
            $column = $field->column;
            $fk = $column;
        } else {
            $column = $this->converter->normalize($property->getName());
            $fk = $column . '_id';
        }

        return match (true) {
            $field instanceof State => [$column, $property->getName(), $field->machine, $field->scopes],
            $field instanceof Translations => [$entity . '_translation', $entity . '_id'],
            $field instanceof ForeignKey => [$column, $property->getName(), $field->entity],
            $field instanceof OneToOne => [$property->getName(), $fk, $field->ref, $field->entity, false],
            $field instanceof ManyToOne => [$property->getName(), $fk, $field->entity, $field->ref],
            $field instanceof OneToMany => [$property->getName(), $field->entity, $field->ref, 'id'],
            $field instanceof ManyToMany => [$property->getName(), $field->entity, self::mappingName($entity, $field), $entity . '_id', $field->entity . '_id'],
            $field instanceof AutoIncrement, $field instanceof Version => [],
            $field instanceof ReferenceVersion => [$field->entity, $column],
            $field instanceof Serialized => [$column, $property->getName(), $field->serializer],
            $field instanceof Password => [$column, $property->getName(), $field->algorithm, $field->hashOptions, $field->for],
            $field instanceof ListFieldAttr => [$column, $property->getName(), $field->fieldType],
            $field->type === FieldType::ENUM => [$column, $property->getName(), $this->getFirstEnumCase($property)],
            $field->type === FieldType::STRING,
            $field->type === FieldType::EMAIL => [$column, $property->getName(), $field->maxLength],
            default => [$column, $property->getName()],
        };
    }

    private static function mappingName(string $entity, ManyToMany $field): string
    {
        if ($field->mapping !== null) {
            return $field->mapping;
        }

        $items = [$entity, $field->entity];
        sort($items);

        return implode('_', $items);
    }

    /**
     * @return array<string, array{class: string, args?: array<string, string|bool|float|null>|list<string>}>
     */
    private function getFlags(Field $field, \ReflectionProperty $property): array
    {
        $flags = [];

        if (!$field->nullable) {
            $flags[Required::class] = ['class' => Required::class];
        }

        if ($this->getAttribute($property, RequiredAttr::class)) {
            $flags[Required::class] = ['class' => Required::class];
        }

        // Translation association fields need to be marked as required,
        // because otherwise required fields in the association are not validated
        if ($field instanceof Translations) {
            $flags[Required::class] = ['class' => Required::class];
        }

        if ($this->getAttribute($property, PrimaryKeyAttr::class)) {
            $flags[PrimaryKey::class] = ['class' => PrimaryKey::class];
            $flags[Required::class] = ['class' => Required::class];
        }

        if ($inherited = $this->getAttribute($property, InheritedAttr::class)) {
            $instance = $inherited->newInstance();
            $flags[Inherited::class] = ['class' => Inherited::class, 'args' => ['foreignKey' => $instance->foreignKey]];
        }

        if ($reverseInherited = $this->getAttribute($property, ReverseInheritedAttr::class)) {
            $instance = $reverseInherited->newInstance();
            $flags[ReverseInherited::class] = ['class' => ReverseInherited::class, 'args' => ['propertyName' => $instance->propertyName]];
        }

        if ($this->getAttribute($property, AllowEmptyStringAttr::class)) {
            $flags[AllowEmptyString::class] = ['class' => AllowEmptyString::class];
        }

        if ($attr = $this->getAttribute($property, AllowHtmlAttr::class)) {
            $instance = $attr->newInstance();
            $flags[AllowHtml::class] = ['class' => AllowHtml::class, 'args' => ['sanitized' => $instance->sanitized]];
        }

        if ($field->api !== false) {
            $aware = [];
            if (\is_array($field->api)) {
                if (isset($field->api['admin-api']) && $field->api['admin-api'] === true) {
                    $aware[] = AdminApiSource::class;
                }
            }

            $flags[ApiAware::class] = ['class' => ApiAware::class, 'args' => $aware];
        }

        if ($protection = $this->getAttribute($property, Protection::class)) {
            $protection = $protection->newInstance();

            $flags[WriteProtected::class] = ['class' => WriteProtected::class, 'args' => $protection->write];
        }

        if ($this->getAttribute($property, ManyToMany::class, OneToMany::class, Translations::class)) {
            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                $flags[AsArray::class] = ['class' => AsArray::class];
            }
        }

        if ($this->getAttribute($property, ReferenceVersion::class)) {
            $flags[Required::class] = ['class' => Required::class];
        }

        if ($association = $this->getAttribute($property, ...self::ASSOCIATIONS)) {
            $association = $association->newInstance();

            $flags['cascade'] = match ($association->onDelete) {
                OnDelete::CASCADE => ['class' => CascadeDelete::class],
                OnDelete::SET_NULL => ['class' => SetNullOnDelete::class],
                OnDelete::RESTRICT => ['class' => RestrictDelete::class],
                default => null,
            };

            if ($flags['cascade'] === null) {
                unset($flags['cascade']);
            }
        }

        if ($searchRanking = $this->getAttribute($property, SearchRankingAttr::class)) {
            $instance = $searchRanking->newInstance();
            $flags[SearchRanking::class] = ['class' => SearchRanking::class, 'args' => ['ranking' => $instance->ranking, 'tokenize' => $instance->tokenize]];
        }

        if ($field->type === AutoIncrement::TYPE) {
            unset($flags[Required::class]);
        }
        if ($field->type === CustomFieldsAttr::TYPE) {
            unset($flags[Required::class]);
        }
        if (is_a($field->type, WasModifiedByUserField::class, true)) {
            unset($flags[Required::class]);
        }

        return $flags;
    }

    /**
     * @return array{
     *     type: 'mapping',
     *     parent: null,
     *     entity_class: class-string<ArrayEntity>,
     *     entity_name: string,
     *     fields: list<FieldArray>,
     *     source: string,
     *     reference: string
     * }
     */
    private function mapping(string $entity, \ReflectionProperty $property): array
    {
        $attribute = $this->getAttribute($property, ManyToMany::class);

        if (!$attribute instanceof \ReflectionAttribute) {
            throw DataAbstractionLayerException::canNotFindAttribute(ManyToMany::class, $property->getName());
        }
        $field = $attribute->newInstance();

        $srcProperty = $this->converter->denormalize($entity);
        $refProperty = $this->converter->denormalize($field->entity);

        $fields = [
            [
                'class' => FkField::class,
                'translated' => false,
                'args' => [$entity . '_id', $srcProperty . 'Id', $entity],
                'flags' => [
                    PrimaryKey::class => ['class' => PrimaryKey::class],
                    Required::class => ['class' => Required::class],
                ],
            ],
            [
                'class' => FkField::class,
                'translated' => false,
                'args' => [$field->entity . '_id', $refProperty . 'Id', $field->entity],
                'flags' => [
                    PrimaryKey::class => ['class' => PrimaryKey::class],
                    Required::class => ['class' => Required::class],
                ],
            ],
            [
                'class' => ManyToOneAssociationField::class,
                'translated' => false,
                'args' => [$srcProperty, $entity . '_id', $entity, 'id'],
                'flags' => [],
            ],
            [
                'class' => ManyToOneAssociationField::class,
                'translated' => false,
                'args' => [$refProperty, $field->entity . '_id', $field->entity, 'id'],
                'flags' => [],
            ],
        ];

        return [
            'type' => 'mapping',
            'parent' => null,
            'entity_class' => ArrayEntity::class,
            'entity_name' => self::mappingName($entity, $field),
            'fields' => $fields,
            'source' => $entity,
            'reference' => $field->entity,
        ];
    }

    private function getFirstEnumCase(\ReflectionProperty $property): \BackedEnum
    {
        $enumType = $property->getType();
        if (!$enumType instanceof \ReflectionNamedType) {
            throw DataAbstractionLayerException::invalidEnumField($property->getName(), $enumType?->__toString() ?? 'null');
        }

        $enumClass = $enumType->getName();
        if (!is_a($enumClass, \BackedEnum::class, true)) {
            throw DataAbstractionLayerException::invalidEnumField($property->getName(), $enumClass);
        }

        return $enumClass::cases()[0];
    }
}
