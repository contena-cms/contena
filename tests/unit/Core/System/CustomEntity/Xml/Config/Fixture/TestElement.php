<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomEntity\Xml\Config\Fixture;

use Contena\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;

/**
 * @internal
 */
class TestElement extends ConfigXmlElement
{
    public array $extensions = [];

    public string $testData = 'TEST_DATA';

    protected static function parse(\DOMElement $element): array
    {
        return [];
    }
}
