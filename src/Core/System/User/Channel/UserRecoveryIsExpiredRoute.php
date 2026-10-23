<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Extension\UserRecoveryIsExpiredRouteExtension;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class UserRecoveryIsExpiredRoute extends AbstractUserRecoveryIsExpiredRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserRecoveryCollection> $userRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $userRecoveryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractUserRecoveryIsExpiredRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/user-recovery-is-expired', name: 'store-api.account.user.recovery.is.expired', methods: ['POST'])]
    public function load(RequestDataBag $data, ChannelContext $context): UserRecoveryIsExpiredResponse
    {
        return $this->extensions->publish(
            name: UserRecoveryIsExpiredRouteExtension::NAME,
            extension: new UserRecoveryIsExpiredRouteExtension($data, $context),
            function: $this->_load(...),
        );
    }

    private function _load(RequestDataBag $data, ChannelContext $context): UserRecoveryIsExpiredResponse
    {
        $this->validateHash($data, $context);

        $hash = $data->get('hash');

        $userHashCriteria = new Criteria();
        $userHashCriteria->addFilter(new EqualsFilter('hash', $hash));

        $userRecovery = $this->userRecoveryRepository->search($userHashCriteria, $context->getContext())->getEntities()->first();
        if (!$userRecovery) {
            throw UserException::userNotFoundByHash($hash);
        }

        return new UserRecoveryIsExpiredResponse($this->isExpired($userRecovery));
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateHash(DataBag $data, ChannelContext $context): void
    {
        $definition = new DataValidationDefinition('user.recovery.get');

        $hashLength = 32;

        $definition->add('hash', new NotBlank(), new Type('string'), new Length($hashLength));

        $this->dispatchValidationEvent($definition, $data, $context->getContext());

        $this->validator->validate($data->all(), $definition);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    private function isExpired(UserRecoveryEntity $userRecovery): bool
    {
        $validDateTime = $this->clock->now()->sub(new \DateInterval('PT2H'));

        return $validDateTime > $userRecovery->getCreatedAt();
    }
}
