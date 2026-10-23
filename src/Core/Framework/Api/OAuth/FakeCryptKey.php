<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\CryptKey;

/**
 * @internal
 */
final class FakeCryptKey extends CryptKey
{
    /**
     * @noinspection MagicMethodsValidityInspection
     *
     * @internal
     */
    public function __construct(public readonly Configuration $configuration)
    {
    }

    public function getKeyContents(): string
    {
        return '';
    }

    public function getKeyPath(): string
    {
        return '';
    }

    public function getPassPhrase(): string
    {
        return '';
    }
}
