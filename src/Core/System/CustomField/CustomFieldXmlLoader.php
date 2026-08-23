<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField;

use Contena\Core\System\CustomField\Xml\CustomFields;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
class CustomFieldXmlLoader
{
    private const string XSD_FILE = __DIR__ . '/Schema/custom-fields-1.0.xsd';

    public static function load(string $xmlFile): CustomFields
    {
        $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);

        $customFields = $doc->getElementsByTagName('custom-fields')->item(0);
        \assert($customFields instanceof \DOMElement);

        return CustomFields::fromXml($customFields);
    }
}
