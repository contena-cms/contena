<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha\BasicCaptcha;

use Contena\Core\Framework\Struct\Struct;

class BasicCaptchaImage extends Struct
{
    public function __construct(
        private readonly string $code,
        private readonly string $imageBase64
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function imageBase64(): string
    {
        return $this->imageBase64;
    }
}
