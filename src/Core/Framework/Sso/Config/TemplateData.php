<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\Config;


/**
 * @internal
 */
final class TemplateData implements \JsonSerializable
{
    use JsonSerializableTrait;
use Contena\Core\Framework\Struct\JsonSerializableTrait;

    public function __construct(
        public readonly bool $useDefault,
        public readonly ?string $url,
        public readonly bool $passwordRecoveryEnabled = true,
    ) {
    }
}
