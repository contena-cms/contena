<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\Config;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class TemplateData implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public readonly bool $useDefault,
        public readonly ?string $url,
        public readonly bool $passwordRecoveryEnabled = true,
    ) {
    }
}
