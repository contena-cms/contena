<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Captcha\BasicCaptcha;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Symfony\Component\Filesystem\Filesystem;

#[Package('discovery')]
class BasicCaptchaGenerator extends AbstractBasicCaptchaGenerator
{
    private const FALLBACK_WIDTH_IMAGE = 390;
    private const FALLBACK_HEIGHT_IMAGE = 65;

    private string $backgroundPath = __DIR__ . '/../../../Resources/app/storefront/dist/assets/captcha/background.png';

    private string $fontPath = __DIR__ . '/../../../Resources/app/storefront/dist/assets/captcha/font.ttf';


    public function setBackgroundPath(string $path): void
    {
        $this->backgroundPath = $path;
    }

    public function getBackgroundPath(): string
    {
        return $this->backgroundPath;
    }

    public function setFontPath(string $fonts): void
    {
        $this->fontPath = $fonts;
    }

    public function getFontPath(): string
    {
        return $this->fontPath;
    }

    /**
     * @param int<1, max> $length
     */
    private function createCaptchaCode(int $length): string
    {
        $alphabetRangeLow = range('a', 'z');
        $alphabetRangeUpp = range('A', 'Z');

        $exclude = ['C', 'c', 'I', 'l', 'O', 'o', 's', 'S', 'U', 'u', 'v', 'V', 'W', 'w', 'X', 'x', 'Z', 'z'];

        $alphabet = array_merge($alphabetRangeLow, $alphabetRangeUpp);
        $alphabet = array_diff($alphabet, $exclude);

        $numericRange = range(1, 9);

        $charList = implode('', $alphabet) . implode('', $numericRange);

        return Random::getString($length, $charList);
    }
}
