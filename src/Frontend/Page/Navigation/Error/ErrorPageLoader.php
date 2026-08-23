<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation\Error;

use Contena\Core\Content\LandingPage\Channel\AbstractLandingPageRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class ErrorPageLoader implements ErrorPageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractLandingPageRoute $landingPageRoute,
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(string $landingPageId, Request $request, ChannelContext $context): ErrorPage
    {
        $page = ErrorPage::createFrom($this->genericLoader->load($request, $context));
        $landingPage = $this->landingPageRoute->load($landingPageId, $request, $context)->getLandingPage();

        $page->setLandingPage($landingPage);

        $metaInformation = new MetaInformation();
        $metaInformation->setMetaTitle((string) ($landingPage->getTranslation('metaTitle') ?? $landingPage->getTranslation('name')));
        $metaInformation->setMetaDescription((string) $landingPage->getTranslation('metaDescription'));
        $metaInformation->setMetaKeywords((string) $landingPage->getTranslation('keywords'));
        $page->setMetaInformation($metaInformation);

        $this->eventDispatcher->dispatch(new ErrorPageLoadedEvent($page, $context, $request));

        return $page;
    }
}
