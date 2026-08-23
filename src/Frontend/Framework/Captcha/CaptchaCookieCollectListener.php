<?php

declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha;

use Contena\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Content\Cookie\Struct\CookieEntry;
use Contena\Core\Content\Cookie\Struct\CookieEntryCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class CaptchaCookieCollectListener
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $channelId = $event->getChannelContext()->getChannelId();
        $googleRecaptchaActive = $this->systemConfigService->getBool(
            'core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV2::CAPTCHA_NAME . '.isActive',
            $channelId
        ) || $this->systemConfigService->getBool(
            'core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV3::CAPTCHA_NAME . '.isActive',
            $channelId
        );

        if (!$googleRecaptchaActive) {
            return;
        }

        $requiredCookieGroup = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        if (!$requiredCookieGroup || !$requiredCookieGroup->isRequired) {
            return;
        }

        $entries = $requiredCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $requiredCookieGroup->setEntries($entries);
        }

        $entryRequiredCaptcha = new CookieEntry('_GRECAPTCHA');
        $entryRequiredCaptcha->name = 'cookie.groupRequiredCaptcha';
        $entryRequiredCaptcha->value = '1';

        $entries->add($entryRequiredCaptcha);
    }
}
