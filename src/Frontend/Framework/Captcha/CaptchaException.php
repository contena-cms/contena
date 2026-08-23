<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class CaptchaException extends HttpException
{
    public const string INVALID_CAPTCHA_ERROR = 'FRAMEWORK__INVALID_CAPTCHA_VALUE';

    public static function invalid(AbstractCaptcha $captcha): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::INVALID_CAPTCHA_ERROR,
            'The provided value for captcha "{{ captcha }}" is not valid.',
            [
                'captcha' => $captcha::class,
            ]
        );
    }
}
