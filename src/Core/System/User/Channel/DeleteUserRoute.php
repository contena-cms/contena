<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Extension\DeleteUserRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class DeleteUserRoute extends AbstractDeleteUserRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractDeleteUserRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/user',
        name: 'store-api.account.user.delete',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function delete(ChannelContext $context, UserEntity $user): NoContentResponse
    {
        return $this->extensions->publish(
            name: DeleteUserRouteExtension::NAME,
            extension: new DeleteUserRouteExtension($context, $user),
            function: $this->_delete(...),
        );
    }

    private function _delete(ChannelContext $context, UserEntity $user): NoContentResponse
    {
        $this->userRepository->delete([['id' => $user->getId()]], $context->getContext());

        return new NoContentResponse();
    }
}
