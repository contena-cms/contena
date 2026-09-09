<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\App\Validation\AppNameValidator;
use Contena\Core\Framework\App\Validation\Error\AppNameError;
use Contena\Core\Framework\Util\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppNameValidator::class)]
class AppNameValidatorTest extends TestCase
{
    private AppNameValidator $appNameValidator;

    protected function setUp(): void
    {
        $resolver = static::createStub(SourceResolver::class);
        $resolver->method('filesystemForManifest')->willReturnCallback(
            static fn (Manifest $manifest): Filesystem => new Filesystem($manifest->getPath())
        );
        $this->appNameValidator = new AppNameValidator($resolver);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $violations = $this->appNameValidator->validate($manifest, null);
        static::assertCount(0, $violations);
    }

    public function testValidateNonCaseSensitive(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $manifest->getMetadata()->assign(['name' => 'TeSt']);

        $violations = $this->appNameValidator->validate($manifest, null);
        static::assertCount(0, $violations);
    }

    public function testValidateReturnsErrors(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidAppName/manifest.xml');

        $violations = $this->appNameValidator->validate($manifest, null);

        static::assertCount(1, $violations);
        static::assertInstanceOf(AppNameError::class, $violations[0]);
        static::assertStringContainsString('The technical app name "notSameAppNameAsFolder" in the "manifest.xml" and the folder name must be equal.', $violations[0]->getMessage());
    }
}
