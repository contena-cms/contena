<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Captcha\BasicCaptcha;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Frontend\Framework\Captcha\BasicCaptcha\BasicCaptchaGenerator;

/**
 * @internal
 */
class BasicCaptchaGeneratorTest extends TestCase
{
    use KernelTestBehaviour;

    private BasicCaptchaGenerator $captcha;

    protected function setUp(): void
    {
        $this->captcha = static::getContainer()->get(BasicCaptchaGenerator::class);
    }

    public function testGetCaptchaImage(): void
    {
        $basicCaptchaImage = $this->captcha->generate();
        static::assertTrue($this->isValid64base($basicCaptchaImage->imageBase64()));
        static::assertNotEmpty($basicCaptchaImage->getCode());
    }

    private function isValid64base(string $string): bool
    {
        $decoded = base64_decode($string, true);

        if (!$decoded) {
            return false;
        }

        return base64_encode($decoded) === $string;
    }
}
