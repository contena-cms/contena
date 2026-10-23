<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Captcha;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Controller\ErrorController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
readonly class CaptchaRouteListener implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param iterable<AbstractCaptcha> $captchas
     */
    public function __construct(
        private iterable $captchas,
        private SystemConfigService $systemConfigService,
        private ContainerInterface $container
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['validateCaptcha', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
        ];
    }

    public function validateCaptcha(ControllerEvent $event): void
    {
        /** @var bool $captchaAnnotation */
        $captchaAnnotation = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CAPTCHA, false);

        if ($captchaAnnotation === false) {
            return;
        }

        /** @var ChannelContext|null $context */
        $context = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        $channelId = $context ? $context->getChannelId() : null;

        $activeCaptchas = (array) ($this->systemConfigService->get('core.basicInformation.activeCaptchasV2', $channelId) ?? []);
        $request = $event->getRequest();

        foreach ($this->captchas as $captcha) {
            $captchaConfig = $activeCaptchas[$captcha->getName()] ?? [];
            if (!$captcha->supports($request, $captchaConfig)) {
                continue;
            }

            $violations = $captcha->validate($request, $captchaConfig);
            if ($violations->count() === 0) {
                continue;
            }

            if ($captcha->shouldBreak() && !$request->isXmlHttpRequest()) {
                throw CaptchaException::invalid($captcha);
            }

            $event->setController(fn () => $this->container->get(ErrorController::class)->onCaptchaFailure($violations, $request));

            // Return on first invalid captcha
            return;
        }
    }
}
