<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Exception;

use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\App\InstallationId\Fingerprint\AppUrl;
use Contena\Core\Framework\App\InstallationId\Fingerprint\InstallationPath;
use Contena\Core\Framework\App\InstallationId\FingerprintComparisonResult;
use Contena\Core\Framework\App\InstallationId\FingerprintMatch;
use Contena\Core\Framework\App\InstallationId\FingerprintMismatch;
use Contena\Core\Framework\App\InstallationId\InstallationId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InstallationIdChangeSuggestedException::class)]
class InstallationIdChangeSuggestedExceptionTest extends TestCase
{
    public function testException(): void
    {
        $result = new FingerprintComparisonResult(
            [
                InstallationPath::IDENTIFIER => new FingerprintMatch(
                    InstallationPath::IDENTIFIER,
                    '/old/path',
                    100
                ),
            ],
            [
                AppUrl::IDENTIFIER => new FingerprintMismatch(
                    AppUrl::IDENTIFIER,
                    'https://old.url',
                    'https://new.url',
                    100
                ),
            ],
            75,
        );

        $e = new InstallationIdChangeSuggestedException($installationId = InstallationId::create('123456789'), $result);

        static::assertSame(500, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_INSTALLATION_ID_CHANGE_SUGGESTED', $e->getErrorCode());
        static::assertSame('Changes in your system were detected that suggest a change of the installation ID.', $e->getMessage());
        static::assertSame($installationId, $e->installationId);
        static::assertSame($result, $e->comparisonResult);
    }
}
