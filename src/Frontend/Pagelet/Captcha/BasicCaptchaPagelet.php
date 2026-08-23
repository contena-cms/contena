<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Captcha;

use Contena\Frontend\Framework\Captcha\BasicCaptcha\BasicCaptchaImage;
use Contena\Frontend\Pagelet\Pagelet;

class BasicCaptchaPagelet extends Pagelet
{
    protected BasicCaptchaImage $captcha;

    public function setCaptcha(BasicCaptchaImage $captcha): void
    {
        $this->captcha = $captcha;
    }

    public function getCaptcha(): BasicCaptchaImage
    {
        return $this->captcha;
    }
}
