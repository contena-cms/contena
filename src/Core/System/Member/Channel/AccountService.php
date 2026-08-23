<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\MemberContextRestorer;
use Contena\Core\System\Member\Event\MemberBeforeLoginEvent;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Exception\MemberNotFoundException;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Contena\Core\System\Member\Service\DoubleOptInService;
use Symfony\Component\PasswordHasher\Hasher\CheckPasswordLengthTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AccountService
{
    use CheckPasswordLengthTrait;

    /**
     * Bcrypt hash of a static placeholder password used to equalize timing when the login cannot succeed.
     */
    private const string PLACEHOLDER_PASSWORD_HASH = '$2y$12$PVcA5R6ri9kS.7FnFUBRIOLwqU//bCicx5RFxwecAAccbmZ7V7PKu';

    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MemberContextRestorer $restorer,
        private readonly DoubleOptInService $doubleOptInService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function loginById(string $id, ChannelContext $context): string
    {
        if (!Uuid::isValid($id)) {
            throw MemberException::badCredentials();
        }

        $member = $this->fetchMember(new Criteria([$id]), $context);
        if ($member === null) {
            throw MemberException::memberNotFoundById($id);
        }

        $this->eventDispatcher->dispatch(new MemberBeforeLoginEvent($context, $member->getEmail()));

        return $this->loginByMember($member, $context);
    }

    public function loginByCredentials(string $email, #[\SensitiveParameter] string $password, ChannelContext $context): string
    {
        if ($email === '' || $password === '') {
            throw MemberException::badCredentials();
        }

        $this->eventDispatcher->dispatch(new MemberBeforeLoginEvent($context, $email));

        $member = $this->getMemberByLogin($email, $password, $context);

        return $this->loginByMember($member, $context);
    }

    public function getMemberByLogin(string $email, #[\SensitiveParameter] string $password, ChannelContext $context): MemberEntity
    {
        if ($this->isPasswordTooLong($password)) {
            throw MemberException::badCredentials();
        }

        try {
            $member = $this->getMemberByEmail($email, $context);
        } catch (MemberNotFoundException) {
            // Prevent member enumeration via timing attacks by always running password_verify().
            password_verify($password, self::PLACEHOLDER_PASSWORD_HASH);

            throw MemberException::badCredentials();
        }

        $passwordHash = $member->getPassword();
        if ($passwordHash === null) {
            password_verify($password, self::PLACEHOLDER_PASSWORD_HASH);

            throw MemberException::badCredentials();
        }

        if (!password_verify($password, $passwordHash)) {
            throw MemberException::badCredentials();
        }

        if (!$this->isMemberConfirmed($member)) {
            $this->doubleOptInService->resendDoubleOptInMail($member, $context);
            throw MemberException::memberOptinNotCompleted($member->getId());
        }

        return $member;
    }

    public function getMemberByEmail(string $email, ChannelContext $context): MemberEntity
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('email', $email));

        $member = $this->fetchMember($criteria, $context);
        if ($member === null) {
            throw MemberException::memberNotFound($email);
        }

        return $member;
    }

    private function isMemberConfirmed(MemberEntity $member): bool
    {
        return !$member->getDoubleOptInRegistration() || $member->getDoubleOptInConfirmDate() !== null;
    }

    private function loginByMember(MemberEntity $member, ChannelContext $context): string
    {
        $this->memberRepository->update([
            [
                'id' => $member->getId(),
                'lastLogin' => $this->clock->now(),
            ],
        ], $context->getContext());

        $context = $this->restorer->restore($member->getId(), $context);
        $newToken = $context->getToken();

        $this->eventDispatcher->dispatch(new MemberLoginEvent($context, $member, $newToken));

        return $newToken;
    }

    /**
     * This method filters for standard member constraints such as active state and channel assignment.
     */
    private function fetchMember(Criteria $criteria, ChannelContext $context): ?MemberEntity
    {
        $criteria->setTitle('account-service::fetchMember');
        $criteria->addFilter(new EqualsFilter('channelId', $context->getChannelId()));

        $result = $this->memberRepository->search($criteria, $context->getContext())->getEntities();
        $result = $result->filter(static fn (MemberEntity $member): bool => $member->getActive());

        if ($result->count() > 1) {
            $result->sort(static fn (MemberEntity $a, MemberEntity $b): int => ($a->getCreatedAt() <=> $b->getCreatedAt()) * -1);
        }

        return $result->first();
    }
}
