<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataResolver;

use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Framework\Struct\Struct;

class FieldConfig extends Struct
{
    final public const SOURCE_STATIC = 'static';
    final public const SOURCE_MAPPED = 'mapped';
    final public const SOURCE_DEFAULT = 'default';
    final public const SOURCE_BLOG_STREAM = 'blog_stream';

    /**
     * @param array<mixed>|bool|float|int|string|null $value
     */
    public function __construct(
        protected string $name,
        protected string $source,
        protected mixed $value,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return array<mixed>|bool|float|int|string|null
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return array<mixed>
     */
    public function getArrayValue(): array
    {
        if (\is_array($this->value)) {
            return $this->value;
        }

        throw CmsException::unexpectedFieldConfigValueType($this->name, 'array', \gettype($this->value));
    }

    public function getStringValue(): string
    {
        if (!\is_array($this->value)) {
            return (string) $this->value;
        }

        throw CmsException::unexpectedFieldConfigValueType($this->name, 'string', \gettype($this->value));
    }

    public function getIntValue(): int
    {
        if (!\is_array($this->value)) {
            return (int) $this->value;
        }

        throw CmsException::unexpectedFieldConfigValueType($this->name, 'int', \gettype($this->value));
    }

    public function getFloatValue(): float
    {
        if (!\is_array($this->value)) {
            return (float) $this->value;
        }

        throw CmsException::unexpectedFieldConfigValueType($this->name, 'float', \gettype($this->value));
    }

    public function getBoolValue(): bool
    {
        return (bool) $this->value;
    }

    public function isStatic(): bool
    {
        return $this->source === self::SOURCE_STATIC;
    }

    public function isMapped(): bool
    {
        return $this->source === self::SOURCE_MAPPED;
    }

    public function isBlogStream(): bool
    {
        return $this->source === self::SOURCE_BLOG_STREAM;
    }

    public function isDefault(): bool
    {
        return $this->source === self::SOURCE_DEFAULT;
    }

    public function getApiAlias(): string
    {
        return 'cms_data_resolver_field_config';
    }
}
