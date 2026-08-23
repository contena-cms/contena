<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class BasicCaptcha extends AbstractCaptcha
{
    final public const string CAPTCHA_NAME = 'basicCaptcha';
    final public const string CAPTCHA_REQUEST_PARAMETER = 'contena_basic_captcha_confirm';
    final public const string BASIC_CAPTCHA_SESSION = 'basic_captcha_session';
    final public const string INVALID_CAPTCHA_CODE = 'captcha.basic-captcha-invalid';

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function supports(Request $request, array $captchaConfig): bool
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        $channelId = $context instanceof ChannelContext ? $context->getChannelId() : null;

        $activeCaptchas = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2', $channelId);

        if (!\is_array($activeCaptchas) || $activeCaptchas === []) {
            return false;
        }

        return $request->isMethod(Request::METHOD_POST)
            && \array_key_exists(self::CAPTCHA_NAME, $activeCaptchas)
            && $activeCaptchas[self::CAPTCHA_NAME]['isActive'];
    }

    public function isValid(Request $request, array $captchaConfig): bool
    {
        $basicCaptchaValue = $request->request->get(self::CAPTCHA_REQUEST_PARAMETER);

        if ($basicCaptchaValue === null) {
            return false;
        }

        $session = $this->requestStack->getSession();
        $captchaSession = $session->get(RequestParamHelper::get($request, 'formId') . self::BASIC_CAPTCHA_SESSION);
        $session->remove(RequestParamHelper::get($request, 'formId') . self::BASIC_CAPTCHA_SESSION);

        if ($captchaSession === null) {
            return false;
        }

        return strtolower((string) $basicCaptchaValue) === strtolower((string) $captchaSession);
    }

    public function shouldBreak(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }

    public function getViolations(): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            '',
            '',
            [],
            '',
            '/' . self::CAPTCHA_REQUEST_PARAMETER,
            '',
            null,
            self::INVALID_CAPTCHA_CODE
        ));

        return $violations;
    }
}
