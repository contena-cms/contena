<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Config;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
abstract class ConfigXmlElement extends XmlElement
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions']);

        return $data;
    }
}
