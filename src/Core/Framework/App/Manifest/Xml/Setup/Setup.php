<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Manifest\Xml\Setup;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;
use Contena\Core\Framework\App\Manifest\XmlParserUtils;

/**
 * @internal only for use by the app-system
 */
class Setup extends XmlElement
{
    protected string $registrationUrl;

    protected ?string $secret = null;

    public function getRegistrationUrl(): string
    {
        return $this->registrationUrl;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    protected static function parse(\DOMElement $element): array
    {
        /** @var array{registrationUrl: string, secret: ?string} $values */
        $values = XmlParserUtils::parseChildren($element);

        return $values;
    }
}
