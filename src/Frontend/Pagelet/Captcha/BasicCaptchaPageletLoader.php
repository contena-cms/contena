<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Captcha;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Captcha\BasicCaptcha\AbstractBasicCaptchaGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a channel-api route to get or put data.
 */
class BasicCaptchaPageletLoader extends AbstractBasicCaptchaPageletLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBasicCaptchaGenerator $basicCaptchaGenerator,
    ) {
    }

    public function load(Request $request, ChannelContext $context): BasicCaptchaPagelet
    {
        $pagelet = new BasicCaptchaPagelet();
        $pagelet->setCaptcha($this->basicCaptchaGenerator->generate());

        $this->eventDispatcher->dispatch(
            new BasicCaptchaPageletLoadedEvent($pagelet, $context, $request),
        );

        return $pagelet;
    }
}
