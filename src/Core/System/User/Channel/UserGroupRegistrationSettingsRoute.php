<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Extension\UserGroupRegistrationSettingsRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class UserGroupRegistrationSettingsRoute extends AbstractUserGroupRegistrationSettingsRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     */
    public function __construct(private readonly EntityRepository $userGroupRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractUserGroupRegistrationSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Though this is a GET route, caching was not added as the output may be altered depending on dynamic rules,
     * which is not taken into account during the cache hash calculation.
     */
    #[Route(path: '/store-api/user-group-registration/config/{userGroupId}', name: 'store-api.user-group-registration.config', methods: ['GET'])]
    public function load(string $userGroupId, ChannelContext $context): UserGroupRegistrationSettingsRouteResponse
    {
        return $this->extensions->publish(
            name: UserGroupRegistrationSettingsRouteExtension::NAME,
            extension: new UserGroupRegistrationSettingsRouteExtension($userGroupId, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $userGroupId, ChannelContext $context): UserGroupRegistrationSettingsRouteResponse
    {
        $criteria = (new Criteria([$userGroupId]))
            ->addFilter(new EqualsFilter('registrationActive', 1))
            ->addFilter(new EqualsFilter('registrationChannels.id', $context->getChannelId()));

        $result = $this->userGroupRepository->search($criteria, $context->getContext());
        if ($result->getTotal() === 0) {
            throw UserException::userGroupRegistrationConfigurationNotFound($userGroupId);
        }

        $userGroup = $result->getEntities()->first();
        \assert($userGroup !== null);

        return new UserGroupRegistrationSettingsRouteResponse($userGroup);
    }
}
