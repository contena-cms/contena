<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomEntity\Xml\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\System\CustomEntity\Xml\Field\Field;
use Contena\Core\System\CustomEntity\Xml\Field\StringField;

/**
 * @internal
 */
#[CoversClass(Field::class)]
class FieldTest extends TestCase
{
    public function testFromXmlMergesAttributesAndChildElements(): void
    {
        $field = StringField::fromXml(self::element(<<<'XML'
        <string name="title" channel-api-aware="true" translatable="true">
            <default>Hello</default>
        </string>
        XML));

        static::assertSame('title', $field->getName());
        static::assertTrue($field->isChannelApiAware());
        static::assertTrue($field->isTranslatable());
        static::assertFalse($field->isRequired());
        static::assertSame('Hello', $field->getDefault());
    }

    public function testFromXmlPrefersAttributesOverChildElements(): void
    {
        $field = StringField::fromXml(self::element(<<<'XML'
        <string name="title" channel-api-aware="false">
            <name>ignored</name>
        </string>
        XML));

        static::assertSame('title', $field->getName());
        static::assertFalse($field->isChannelApiAware());
    }

    public function testJsonSerializeStripsExtensions(): void
    {
        $field = StringField::fromXml(self::element('<string name="title" channel-api-aware="true"/>'));
        $field->addExtension('meta', new ArrayStruct(['foo' => 'bar']));

        $data = $field->jsonSerialize();

        static::assertArrayNotHasKey('extensions', $data);
        static::assertSame('title', $data['name']);
        static::assertSame('string', $data['type']);
        static::assertTrue($data['channelApiAware']);
    }

    /**
     * @param non-empty-string $xml
     */
    private static function element(string $xml): \DOMElement
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        \assert($dom->documentElement instanceof \DOMElement);

        return $dom->documentElement;
    }
}
