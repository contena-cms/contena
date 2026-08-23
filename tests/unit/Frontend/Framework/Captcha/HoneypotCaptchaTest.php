<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Framework\Captcha\HoneypotCaptcha;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(HoneypotCaptcha::class)]
class HoneypotCaptchaTest extends TestCase
{
    /**
     * @param array<string, mixed> $requestParameters
     */
    #[DataProvider('honeypotProvider')]
    #[TestDox('The honeypot captcha is valid only when the honeypot field is empty')]
    public function testIsValid(array $requestParameters, bool $expectedValid): void
    {
        $captcha = new HoneypotCaptcha();

        static::assertSame(
            $expectedValid,
            $captcha->isValid(new Request(request: $requestParameters), [])
        );
    }

    /**
     * @return \Generator<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function honeypotProvider(): \Generator
    {
        yield 'absent honeypot field is valid' => [[], true];
        yield 'empty honeypot field is valid' => [[HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => ''], true];
        // InputBag::get() returns a present-but-null value as null (not the default), so without the
        // null-coalescing fix `null === ''` would wrongly reject this empty submission.
        yield 'present-but-null honeypot field is valid' => [[HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => null], true];
        yield 'filled honeypot field is invalid' => [[HoneypotCaptcha::CAPTCHA_REQUEST_PARAMETER => 'i-am-a-bot'], false];
    }
}
