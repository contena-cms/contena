<?php declare(strict_types=1);

namespace Contena\Frontend\Page;

use Contena\Core\ChannelRequest;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class GenericPageLoader implements GenericPageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(Request $request, ChannelContext $context): Page
    {
        return Profiler::trace('generic-page-loader', function () use ($request, $context) {
            $page = new Page();

            $page->setMetaInformation(new MetaInformation()->assign([
                'revisit' => '15 days',
                'robots' => $this->systemConfigService->getString('core.basicInformation.metaRobots', $context->getChannelId()) ?: 'index,follow',
                'xmlLang' => $request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_LOCALE) ?? '',
                'metaTitle' => $this->systemConfigService->getString('core.basicInformation.siteName', $context->getChannelId()),
            ]));

            $this->eventDispatcher->dispatch(new GenericPageLoadedEvent($page, $context, $request));

            return $page;
        });
    }
}
