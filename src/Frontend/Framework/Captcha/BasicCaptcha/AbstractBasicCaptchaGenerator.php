<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha\BasicCaptcha;

use Contena\Core\Framework\Log\Package;

abstract class AbstractBasicCaptchaGenerator
{
    abstract public function generate(): BasicCaptchaImage;
}
