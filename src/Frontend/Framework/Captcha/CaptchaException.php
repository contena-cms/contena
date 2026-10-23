<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class CaptchaException extends HttpException
{
    public const INVALID_CAPTCHA_ERROR = 'FRAMEWORK__INVALID_CAPTCHA_VALUE';

    // Used by the frontend when the cookies reCAPTCHA needs were not accepted.
    public const RECAPTCHA_COOKIE_REQUIRED_VIOLATION = 'VIOLATION::RECAPTCHA_COOKIE_REQUIRED';

    // No reCAPTCHA token was submitted, for a reason the server cannot narrow down.
    public const RECAPTCHA_TOKEN_REQUIRED_VIOLATION = 'VIOLATION::RECAPTCHA_TOKEN_REQUIRED';

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
