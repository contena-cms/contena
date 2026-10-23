<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Manifest\Xml\Gateway;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal only for use by the app-system
 */
abstract class AbstractGateway extends XmlElement
{
    protected ?string $url = null;

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return array{url: string|null}
     */
    protected static function parse(\DOMElement $element): array
    {
        return ['url' => $element->nodeValue];
    }
}
