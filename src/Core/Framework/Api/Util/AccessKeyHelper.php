<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Util;

use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Util\Random;

class AccessKeyHelper
{
    private const USER_IDENTIFIER = 'SWUA';
    private const INTEGRATION_IDENTIFIER = 'SWIA';
    private const CHANNEL_IDENTIFIER = 'SWSC';
    private const BLOG_EXPORT_IDENTIFIER = 'SWPE';

    /**
     * @var array<string, string>
     */
    public static array $mapping = [
        self::USER_IDENTIFIER => 'admin_user',
        self::INTEGRATION_IDENTIFIER => 'integration',
        self::CHANNEL_IDENTIFIER => 'sales-channel',
        self::BLOG_EXPORT_IDENTIFIER => 'blog-export',
    ];

    public static function generateAccessKey(string $identifier): string
    {
        return self::getIdentifier($identifier) . mb_strtoupper(str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(16))));
    }

    public static function generateSecretAccessKey(): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(38)));
    }

    public static function getOrigin(string $accessKey): string
    {
        $identifier = mb_substr($accessKey, 0, 4);

        if (!isset(self::$mapping[$identifier])) {
            throw ApiException::invalidAccessKey();
        }

        return self::$mapping[$identifier];
    }

    private static function getIdentifier(string $origin): string
    {
        $mapping = array_flip(self::$mapping);

        if (!isset($mapping[$origin])) {
            throw ApiException::invalidAccessKeyIdentifier();
        }

        return $mapping[$origin];
    }
}
