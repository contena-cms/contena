<?php declare(strict_types=1);

namespace Contena\Frontend\Page\LandingPage;

use Contena\Core\Content\LandingPage\Channel\AbstractLandingPageRoute;
use Contena\Core\Content\LandingPage\LandingPageException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
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
        if (!$landingPageId) {
            throw RoutingException::missingRequestParameter('landingPageId', '/landingPageId');
        }

        $landingPage = $this->landingPageRoute->load($landingPageId, $request, $context)->getLandingPage();

        if ($landingPage->getCmsPage() === null) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw new PageNotFoundException($landingPageId); // @phpstan-ignore contena.domainException
            }

            throw LandingPageException::notFound($landingPageId);
        }

        $page = $this->genericPageLoader->load($request, $context);
        $page = LandingPage::createFrom($page);

        $page->setLandingPage($landingPage);
        $page->setNavigationId($landingPage->getId());

        $metaTitle = $landingPage->getTranslation('metaTitle') ?? $landingPage->getTranslation('name');
        $metaDescription = $landingPage->getTranslation('metaDescription');
        $metaKeywords = $landingPage->getTranslation('keywords');

        $metaInformation = new MetaInformation();
        $metaInformation->setMetaTitle((string) $metaTitle);
        $metaInformation->setMetaDescription((string) $metaDescription);
        $metaInformation->setMetaKeywords((string) $metaKeywords);
        $page->setMetaInformation($metaInformation);

        $this->eventDispatcher->dispatch(
            new LandingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
