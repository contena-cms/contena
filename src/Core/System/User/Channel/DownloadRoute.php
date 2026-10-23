<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Content\Media\File\DownloadResponseGenerator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Extension\DownloadRouteExtension;
use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class DownloadRoute extends AbstractDownloadRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderLineItemDownloadCollection> $downloadRepository
     */
    public function __construct(
        private readonly EntityRepository $downloadRepository,
        private readonly DownloadResponseGenerator $downloadResponseGenerator,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractDownloadRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/order/download/{orderId}/{downloadId}',
        name: 'store-api.account.order.single.download',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function load(Request $request, ChannelContext $context): Response
    {
        return $this->extensions->publish(
            name: DownloadRouteExtension::NAME,
            extension: new DownloadRouteExtension($request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, ChannelContext $context): Response
    {
        $user = $context->getUser();
        $downloadId = $request->attributes->get('downloadId');
        $orderId = $request->attributes->get('orderId');

        if (!$user) {
            throw UserException::userNotLoggedIn();
        }

        if ($downloadId === null || $orderId === null) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw RoutingException::missingRequestParameter(!$downloadId ? 'downloadId' : 'orderId');
            }
            throw UserException::missingRequestParameter(!$downloadId ? 'downloadId' : 'orderId');
        }

        $criteria = (new Criteria([$downloadId]))
            ->addAssociation('media')
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('orderLineItem.order.id', $orderId),
                new EqualsFilter('orderLineItem.order.orderUser.userId', $user->getId()),
                new EqualsFilter('accessGranted', true),
            ]));

        $download = $this->downloadRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$download || !$download->getMedia()) {
            throw UserException::downloadFileNotFound($downloadId);
        }

        $media = $download->getMedia();

        return $this->downloadResponseGenerator->getResponse($media, $context);
    }
}
