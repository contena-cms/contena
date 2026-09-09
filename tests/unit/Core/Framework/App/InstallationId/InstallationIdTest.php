<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\InstallationId;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\InstallationId\InstallationId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InstallationId::class)]
class InstallationIdTest extends TestCase
{
    public function testCreatesInstallationIdFromValidConfig(): void
    {
        $config = ['id' => '123456789', 'version' => 2, 'fingerprints' => [
            'channel_domain_urls' => 'SALES_CHANNEL_DOMAIN_URLS',
            'app_url' => 'APP_URL',
        ]];
        $installationId = InstallationId::fromSystemConfig($config);

        static::assertSame($installationId->id, $config['id']);
        static::assertSame(2, $installationId->version);
        static::assertSame($config['fingerprints'], $installationId->fingerprints);
    }

    public function testThrowsIfSystemConfigIsInvalid(): void
    {
        $this->expectExceptionObject(AppException::invalidInstallationIdConfiguration());

        InstallationId::fromSystemConfig(['foo' => 'bar']);
    }

    public function testPutsFingerprintsOn(): void
    {
        $fingerprints = [
            'channel_domain_urls' => 'SALES_CHANNEL_DOMAIN_URLS',
            'app_url' => 'APP_URL',
        ];

        $installationId = InstallationId::create('123456789', $fingerprints);

        static::assertSame($fingerprints['channel_domain_urls'], $installationId->getFingerprint('channel_domain_urls'));
        static::assertSame($fingerprints['app_url'], $installationId->getFingerprint('app_url'));
        static::assertNull($installationId->getFingerprint('installation_path'));
    }
}
