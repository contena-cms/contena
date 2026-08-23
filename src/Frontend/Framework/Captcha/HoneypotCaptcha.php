<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha;

use Symfony\Component\HttpFoundation\Request;

class HoneypotCaptcha extends AbstractCaptcha
{
    final public const string CAPTCHA_NAME = 'honeypot';
    final public const string CAPTCHA_REQUEST_PARAMETER = 'contena_surname_confirm';

    /**
     * {@inheritdoc}
     */
    public function isValid(Request $request, array $captchaConfig): bool
    {
        return ($request->request->get(self::CAPTCHA_REQUEST_PARAMETER) ?? '') === '';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }
}
