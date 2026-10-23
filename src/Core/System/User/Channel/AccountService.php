<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Event\UserBeforeLoginEvent;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\Exception\AddressNotFoundException;
use Contena\Core\System\User\Exception\BadCredentialsException;
use Contena\Core\System\User\Exception\UserNotFoundByIdException;
use Contena\Core\System\User\Exception\UserNotFoundException;
use Contena\Core\System\User\Exception\UserOptinNotCompletedException;
use Contena\Core\System\User\Extension\LoginByCredentialsExtension;
use Contena\Core\System\User\Password\LegacyPasswordVerifier;
use Contena\Core\System\User\Service\DoubleOptInService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Exception\InvalidUuidException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\Context\CartRestorer;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\CheckPasswordLengthTrait;
use Symfony\Component\Validator\ConstraintViolation;

class AccountService
{
    use CheckPasswordLengthTrait;

    /**
     * Bcrypt hash of a static placeholder password used to equalize timing when the login cannot succeed.
     */
    private const PLACEHOLDER_PASSWORD_HASH = '$2y$12$PVcA5R6ri9kS.7FnFUBRIOLwqU//bCicx5RFxwecAAccbmZ7V7PKu';

    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LegacyPasswordVerifier $legacyPasswordVerifier,
        private readonly AbstractSwitchDefaultAddressRoute $switchDefaultAddressRoute,
        private readonly CartRestorer $restorer,
        private readonly DoubleOptInService $doubleOptInService,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @throws CartException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultBillingAddress(string $addressId, ChannelContext $context, UserEntity $user): void
    {
        $this->switchDefaultAddressRoute->swap($addressId, AbstractSwitchDefaultAddressRoute::TYPE_BILLING, $context, $user);
    }

    /**
     * @throws CartException
     * @throws InvalidUuidException
     * @throws AddressNotFoundException
     */
    public function setDefaultShippingAddress(string $addressId, ChannelContext $context, UserEntity $user): void
    {
        $this->switchDefaultAddressRoute->swap($addressId, AbstractSwitchDefaultAddressRoute::TYPE_SHIPPING, $context, $user);
    }

    /**
     * @throws BadCredentialsException
     * @throws UserNotFoundByIdException
     */
    public function loginById(string $id, ChannelContext $context): string
    {
        if (!Uuid::isValid($id)) {
            throw UserException::badCredentials();
        }

        $user = $this->fetchUser(new Criteria([$id]), $context, true);
        if ($user === null) {
            throw UserException::userNotFoundByIdException($id);
        }

        $event = new UserBeforeLoginEvent($context, $user->getEmail());
        $this->eventDispatcher->dispatch($event);

        return $this->loginByUser($user, $context);
    }

    /**
     * @throws BadCredentialsException
     * @throws UserOptinNotCompletedException
     */
    public function loginByCredentials(string $email, #[\SensitiveParameter] string $password, ChannelContext $context): string
    {
        return $this->extensions->publish(
            name: LoginByCredentialsExtension::NAME,
            extension: new LoginByCredentialsExtension($email, $password, $context),
            function: $this->_loginByCredentials(...),
        );
    }

    /**
     * @throws BadCredentialsException
     * @throws UserOptinNotCompletedException
     */
    public function getUserByLogin(string $email, #[\SensitiveParameter] string $password, ChannelContext $context): UserEntity
    {
        if ($this->isPasswordTooLong($password)) {
            throw UserException::badCredentials();
        }

        try {
            $user = $this->getUserByEmail($email, $context);
        } catch (UserNotFoundException) {
            // Prevent user enumeration via timing attacks by always running password_verify().
            password_verify($password, self::PLACEHOLDER_PASSWORD_HASH);

            throw UserException::badCredentials();
        }

        if ($user->hasLegacyPassword()) {
            if (!$this->legacyPasswordVerifier->verify($password, $user)) {
                // Legacy md5/sha256 verification is far cheaper than bcrypt; match its cost so a wrong password does not reveal migrated accounts.
                password_verify($password, self::PLACEHOLDER_PASSWORD_HASH);

                throw UserException::badCredentials();
            }

            $this->updatePasswordHash($password, $user, $context->getContext());

            return $user;
        }

        $passwordHash = $user->getPassword();
        if ($passwordHash === null) {
            password_verify($password, self::PLACEHOLDER_PASSWORD_HASH);

            throw UserException::badCredentials();
        }

        if (!password_verify($password, $passwordHash)) {
            throw UserException::badCredentials();
        }

        if (!$this->isUserConfirmed($user)) {
            // Make sure to only resend after it has been verified it was a valid login
            $this->doubleOptInService->resendDoubleOptInMail($user, $context);
            throw UserException::userOptinNotCompleted($user->getId());
        }

        return $user;
    }

    /**
     * @throws UserNotFoundException
     */
    public function getUserByEmail(string $email, ChannelContext $context): UserEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('email', $email));

        $user = $this->fetchUser($criteria, $context);
        if ($user === null) {
            throw UserException::userNotFound($email);
        }

        return $user;
    }

    private function _loginByCredentials(string $email, #[\SensitiveParameter] string $password, ChannelContext $context): string
    {
        if ($email === '' || $password === '') {
            throw UserException::badCredentials();
        }

        $event = new UserBeforeLoginEvent($context, $email);
        $this->eventDispatcher->dispatch($event);

        $user = $this->getUserByLogin($email, $password, $context);

        return $this->loginByUser($user, $context);
    }

    private function isUserConfirmed(UserEntity $user): bool
    {
        return !$user->getDoubleOptInRegistration() || $user->getDoubleOptInConfirmDate();
    }

    private function loginByUser(UserEntity $user, ChannelContext $context): string
    {
        $this->userRepository->update([
            [
                'id' => $user->getId(),
                'lastLogin' => $this->clock->now(),
            ],
        ], $context->getContext());

        $context = $this->restorer->restore($user->getId(), $context);
        $newToken = $context->getToken();

        $event = new UserLoginEvent($context, $user, $newToken);
        $this->eventDispatcher->dispatch($event);

        return $newToken;
    }

    /**
     * This method filters for the standard user related constraints like active or the sales channel
     * assignment.
     * Add only filters to the $criteria for values which have an index in the database, e.g. id, or email. The rest
     * should be done via PHP because it's a lot faster to filter a few entities on PHP side with the same email
     * address, than to filter a huge numbers of rows in the DB on a not indexed column.
     */
    private function fetchUser(Criteria $criteria, ChannelContext $context, bool $includeGuest = false): ?UserEntity
    {
        $criteria->setTitle('account-service::fetchUser');

        $result = $this->userRepository->search($criteria, $context->getContext())->getEntities();
        $result = $result->filter(static function (UserEntity $user) use ($includeGuest, $context): bool {
            // Skip not active users
            if (!$user->getActive()) {
                return false;
            }

            // Skip guest if not required
            if (!$includeGuest && $user->getGuest()) {
                return false;
            }

            // If not bound, we still need to consider it
            if ($user->getBoundChannelId() === null) {
                return true;
            }

            // It is bound, but not to the current one. Skip it
            if ($user->getBoundChannelId() !== $context->getChannelId()) {
                return false;
            }

            return true;
        });

        // If there is more than one account we want to return the latest, this is important
        // for guest accounts, real user accounts should only occur once, otherwise the
        // wrong password will be validated
        if ($result->count() > 1) {
            $result->sort(static fn (UserEntity $a, UserEntity $b) => ($a->getCreatedAt() <=> $b->getCreatedAt()) * -1);
        }

        return $result->first();
    }

    private function updatePasswordHash(#[\SensitiveParameter] string $password, UserEntity $user, Context $context): void
    {
        try {
            $this->userRepository->update([
                [
                    'id' => $user->getId(),
                    'password' => $password,
                    'legacyPassword' => null,
                    'legacyEncoder' => null,
                ],
            ], $context);
        } catch (WriteException $writeException) {
            $this->handleWriteExceptionForUpdatingPasswordHash($writeException);
        }
    }

    private function handleWriteExceptionForUpdatingPasswordHash(WriteException $writeException): void
    {
        foreach ($writeException->getExceptions() as $exception) {
            if (!$exception instanceof WriteConstraintViolationException) {
                continue;
            }

            /** @var ConstraintViolation $constraintViolation */
            foreach ($exception->getViolations() as $constraintViolation) {
                if ($constraintViolation->getPropertyPath() === '/password') {
                    throw UserException::passwordPoliciesUpdated();
                }
            }
        }

        throw $writeException;
    }
}
