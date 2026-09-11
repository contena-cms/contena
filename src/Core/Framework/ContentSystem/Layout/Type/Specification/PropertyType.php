<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Specification;

use Contena\Core\Defaults;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Contena\Core\Framework\ContentSystem\Layout\Type\Validation\TranslatableTypeValidator;
use Contena\Core\Framework\ContentSystem\Layout\Type\Validation\TypedEnumValidator;

/**
 * $type accepts primitives (`string`, `integer`, `boolean`, `number`), `object`,
 * class-string<Struct> FQCNs, and lists for union-like declarations.
 * `enum` is ignored for non-primitive types; `translatable` is a declaration error on any type but the lone
 * `string`. {@see TypedEnumValidator} {@see TranslatableTypeValidator}
 *
 * Three members serve the stored tree rather than the published schema: {@see translatable()} reads the flag,
 * {@see storedDefault()} is the one shape rule for a declared default in storage, and {@see admits()} is the one
 * conformance predicate answering whether a stored value matches this declared type.
 *
 * @phpstan-type PropertyTypeSchema = array{
 *     type: string|list<string>,
 *     translatable: bool,
 *     enum: list<string|int|float|bool>|null,
 *     default: string|int|float|bool|null,
 *     properties: array<string, array<string, mixed>>|null
 * }
 */
final readonly class PropertyType
{
    /**
     * The canonical primitive type set: any other `type` value is a `class-string<Struct>` FQCN.
     */
    public const PRIMITIVE_TYPES = ['string', 'integer', 'number', 'boolean'];

    /**
     * @param string|list<string> $type
     * @param list<string|int|float|bool>|null $enum
     * @param array<string, PropertySpecification>|null $properties
     */
    public function __construct(
        private string|array $type,
        private bool $translatable,
        private ?array $enum,
        private string|int|float|bool|null $default,
        private ?array $properties = null,
    ) {
    }

    /**
     * @return PropertyTypeSchema
     */
    public function toSchema(): array
    {
        $properties = null;

        if ($this->properties !== null) {
            $properties = array_map(
                static fn (PropertySpecification $property): array => $property->toSchema(),
                $this->properties
            );
        }

        return [
            'type' => $this->type,
            'translatable' => $this->translatable,
            'enum' => $this->enum,
            'default' => $this->default,
            'properties' => $properties,
        ];
    }

    /**
     * @return string|list<string>
     */
    public function type(): string|array
    {
        return $this->type;
    }

    public function default(): string|int|float|bool|null
    {
        return $this->default;
    }

    public function translatable(): bool
    {
        return $this->translatable;
    }

    /**
     * @return string|int|float|bool|array<string, string|int|float|bool>|null
     */
    public function storedDefault(): string|int|float|bool|array|null
    {
        if ($this->default === null) {
            return null;
        }

        if (!$this->translatable) {
            return $this->default;
        }

        return [Defaults::LANGUAGE_SYSTEM => $this->default];
    }

    public function admits(StoredValue $value): bool
    {
        if ($this->translatable) {
            $raw = $value->jsonSerialize();

            if (!\is_array($raw) || array_is_list($raw)) {
                return false;
            }

            foreach ($raw as $entry) {
                if (!\is_string($entry)) {
                    return false;
                }
            }

            return true;
        }

        if ($value->isNull()) {
            return true;
        }

        $types = \is_string($this->type) ? [$this->type] : $this->type;

        if ($types === [] || \array_diff($types, self::PRIMITIVE_TYPES) !== []) {
            return true;
        }

        $raw = $value->jsonSerialize();

        foreach ($types as $type) {
            if (($type === 'string' && \is_string($raw))
                || ($type === 'integer' && \is_int($raw))
                || ($type === 'number' && (\is_int($raw) || \is_float($raw)))
                || ($type === 'boolean' && \is_bool($raw))) {
                return true;
            }
        }

        return false;
    }

    public function isPrimitive(): bool
    {
        return \in_array($this->type, self::PRIMITIVE_TYPES, true);
    }
}
