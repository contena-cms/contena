<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Menu\Offcanvas;

use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
class MenuOffcanvasPageletLoader implements MenuOffcanvasPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NavigationLoaderInterface $navigationLoader
    ) {
    }

}
