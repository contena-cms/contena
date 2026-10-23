<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Exception\AppValidationException;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\ErrorCollection;
use Contena\Core\Framework\App\Validation\ManifestValidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ManifestValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ManifestValidator $manifestValidator;

    protected function setUp(): void
    {
        $this->manifestValidator = static::getContainer()->get(ManifestValidator::class);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $error = null;
        $message = '';
        try {
            $this->manifestValidator->validate($manifest, Context::createDefaultContext());
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    public function testValidateInvalidManifest(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidManifest/manifest.xml');

        $this->expectExceptionObject(new AppValidationException('invalidManifestName', new ErrorCollection()));
        $this->manifestValidator->validate($manifest, Context::createDefaultContext());
    }
}
