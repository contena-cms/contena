<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha\BasicCaptcha;

abstract class AbstractBasicCaptchaGenerator
{
    abstract public function generate(): BasicCaptchaImage;
}
