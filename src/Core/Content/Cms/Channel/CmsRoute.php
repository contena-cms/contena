<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Channel;

use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Content\Cms\Extension\CmsRouteExtension;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class CmsRoute extends AbstractCmsRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly ChannelCmsPageLoaderInterface $cmsPageLoader, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractCmsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/cms/{id}',
        name: 'channel-api.cms.detail',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $id, Request $request, ChannelContext $context): CmsRouteResponse
    {
        return $this->extensions->publish(
            name: CmsRouteExtension::NAME,
            extension: new CmsRouteExtension($id, $request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $id, Request $request, ChannelContext $context): CmsRouteResponse
    {
        $criteria = new Criteria([$id]);

        $slots = RequestParamHelper::get($request, 'slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (\is_array($slots) && $slots !== []) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        $cmsPage = $this->cmsPageLoader->load($request, $criteria, $context)->getEntities()->first();
        if ($cmsPage === null) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore contena.domainException (Will be fixed with next major) */
                throw new PageNotFoundException($id);
            }
            throw CmsException::pageNotFound($id);
        }

        return new CmsRouteResponse($cmsPage);
    }
}
