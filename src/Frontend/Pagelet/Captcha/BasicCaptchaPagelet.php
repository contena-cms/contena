<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Captcha;

use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Framework\Captcha\BasicCaptcha\BasicCaptchaImage;
use Contena\Storefront\Pagelet\Pagelet;

#[Package('discovery')]
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
