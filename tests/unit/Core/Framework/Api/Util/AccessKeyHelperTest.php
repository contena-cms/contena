<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;

/**
 * @internal
 */
#[CoversClass(AccessKeyHelper::class)]
class AccessKeyHelperTest extends TestCase
{
    #[DataProvider('mappingIdentifier')]
    public function testGenerateAccessKeyWithUserIdentifier(string $origin, string $identifier): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey($identifier);
        static::assertStringContainsString($origin, $accessKey);
    }

    public function testGenerateAccessKeyWithInvalidIdentifier(): void
    {
        $this->expectExceptionObject(ApiException::invalidAccessKeyIdentifier());
        AccessKeyHelper::generateAccessKey('invalid_identifier');
    }

    public function testGenerateOriginWithIntegrationIdentifier(): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $origin = AccessKeyHelper::getOrigin($accessKey);
        static::assertSame('integration', $origin);
    }

    public function testGenerateOriginWithInvalidAccessKey(): void
    {
        $this->expectExceptionObject(ApiException::invalidAccessKey());
        AccessKeyHelper::getOrigin('invalid_access_key');
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function mappingIdentifier(): array
    {
        return [
            ['SWUA', 'user'],
            ['SWIA', 'integration'],
            ['SWCH', 'channel'],
        ];
    }
}
