<?php declare(strict_types=1);

namespace Contena\Core\Framework\Util;

use Contena\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
abstract class XmlElement extends Struct
{
    /**
     * @var list<string>
     */
    protected const array REQUIRED_FIELDS = [];
    private const string FALLBACK_LOCALE = 'en-GB';

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, static::REQUIRED_FIELDS);

        foreach ($data as $property => $value) {
            // @phpstan-ignore property.dynamicName (The XML element is abstract dynamic so we allow all dynamic properties)
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): static
    {
        /** @phpstan-ignore new.static,new.staticInAbstractClassStaticMethod (the usage of "new static" is explicitly wanted) */
        return new static(static::parse($element));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        /** @phpstan-ignore new.static,new.staticInAbstractClassStaticMethod (the usage of "new static" is explicitly wanted) */
        return new static($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        $array = get_object_vars($this);

        unset($array['extensions']);

        return $array;
    }

    public static function kebabCaseToCamelCase(string $string): string
    {
        return new CamelCaseToSnakeCaseNameConverter()->denormalize(str_replace('-', '_', $string));
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected static function parse(\DOMElement $element): array;

    /**
     * @param array<string, string> $translations
     *
     * @return array<string, string>
     */
    protected function ensureTranslationForDefaultLanguageExist(array $translations, string $defaultLocale): array
    {
        if ($translations === []) {
            return $translations;
        }

        if (!\array_key_exists($defaultLocale, $translations)) {
            $translations[$defaultLocale] = $this->getFallbackTranslation($translations);
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $requiredFields
     */
    protected function validateRequiredElements(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw UtilException::xmlElementNotFound($field);
            }
        }
    }

    /**
     * @param array<string, string> $translations
     */
    private function getFallbackTranslation(array $translations): string
    {
        if (\array_key_exists(self::FALLBACK_LOCALE, $translations)) {
            return $translations[self::FALLBACK_LOCALE];
        }

        return array_values($translations)[0];
    }
}
