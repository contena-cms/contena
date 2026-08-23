<?php declare(strict_types=1);

namespace Contena\Frontend\Page\LandingPage;

use Contena\Core\Content\LandingPage\Channel\AbstractLandingPageRoute;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a Channel API route.
 */
class LandingPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly AbstractLandingPageRoute $landingPageRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(Request $request, ChannelContext $context): LandingPage
    {
        $landingPageId = $request->attributes->get('landingPageId');
        if (!\is_string($landingPageId) || $landingPageId === '') {
            throw RoutingException::missingRequestParameter('landingPageId', '/landingPageId');
        }

        $landingPage = $this->landingPageRoute->load($landingPageId, $request, $context)->getLandingPage();
        $page = LandingPage::createFrom($this->genericPageLoader->load($request, $context));
        $page->setLandingPage($landingPage);
        $page->setNavigationId($landingPage->getId());

        $metaInformation = new MetaInformation();
        $metaInformation->setMetaTitle((string) ($landingPage->getTranslation('metaTitle') ?? $landingPage->getTranslation('name')));
        $metaInformation->setMetaDescription((string) $landingPage->getTranslation('metaDescription'));
        $metaInformation->setMetaKeywords((string) $landingPage->getTranslation('keywords'));
        $page->setMetaInformation($metaInformation);

        $this->eventDispatcher->dispatch(new LandingPageLoadedEvent($page, $context, $request));

        return $page;
    }
}
