<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\InstallationId\Fingerprint;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\InstallationId\Fingerprint\AppUrl;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppUrl::class)]
class AppUrlTest extends TestCase
{
    use EnvTestBehaviour;

    public function testIdentifier(): void
    {
        $fingerprint = new AppUrl();

        static::assertSame('app_url', $fingerprint->getIdentifier());
    }

    public function testScore(): void
    {
        $fingerprint = new AppUrl();

        static::assertSame(100, $fingerprint->getScore());
    }

    public function testTakesAppUrlFromEnv(): void
    {
        $fingerprint = new AppUrl();

        $this->setEnvVars(['APP_URL' => 'https://example.com']);
        static::assertSame('https://example.com', $fingerprint->getStamp());

        $this->setEnvVars(['APP_URL' => 'https://foo.bar.com']);
        static::assertSame('https://foo.bar.com', $fingerprint->getStamp());
    }

    public function testThrowsIfAppUrlEnvVarIsNotSet(): void
    {
        $fingerprint = new AppUrl();

        $this->setEnvVars(['APP_URL' => null]);

        $this->expectExceptionObject(AppException::appUrlNotConfigured());

        $fingerprint->getStamp();
    }
}
