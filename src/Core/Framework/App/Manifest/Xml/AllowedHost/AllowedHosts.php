<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Manifest\Xml\AllowedHost;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;
use Contena\Core\Framework\App\Manifest\XmlParserUtils;

/**
 * @internal only for use by the app-system
 */
class AllowedHosts extends XmlElement
{
    /**
     * @var list<string>
     */
    protected array $allowedHosts;

    /**
     * @return list<string>
     */
    public function getHosts(): array
    {
        return $this->allowedHosts;
    }

    protected static function parse(\DOMElement $element): array
    {
        return ['allowedHosts' => XmlParserUtils::parseChildrenAsList($element)];
    }
}
