<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Recovery;

use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Routing\TrustedOriginUrlGenerator;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\AdminUser\AdminUserException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

class AdminUserRecoveryService
{
    /**
     * @param EntityRepository<AdminUserRecoveryCollection> $admin_userRecoveryRepo
     * @param EntityRepository<AdminUserCollection> $admin_userRepo
     * @param EntityRepository<ChannelCollection> $channelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $admin_userRecoveryRepo,
        private readonly EntityRepository $admin_userRepo,
        private readonly RouterInterface $router,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ChannelContextServiceInterface $channelContextService,
        private readonly EntityRepository $channelRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function generateUserRecovery(string $admin_userEmail, Context $context): void
    {
        $admin_user = $this->getUserByEmail($admin_userEmail, $context);

        if (!$admin_user) {
            return;
        }

        $admin_userId = $admin_user->getId();

        $admin_userIdCriteria = new Criteria();
        $admin_userIdCriteria->addFilter(new EqualsFilter('admin_userId', $admin_userId));
        $admin_userIdCriteria->addAssociation('admin_user');

        if ($existingRecovery = $this->getUserRecovery($admin_userIdCriteria, $context)) {
            $this->deleteRecoveryForUser($existingRecovery, $context);
        }

        $recoveryData = [
            'admin_userId' => $admin_userId,
            'hash' => Random::getAlphanumericString(32),
        ];

        $this->admin_userRecoveryRepo->create([$recoveryData], $context);

        $recovery = $this->getUserRecovery($admin_userIdCriteria, $context);

        if (!$recovery) {
            return;
        }

        $hash = $recovery->getHash();

        try {
            $url = TrustedOriginUrlGenerator::generateAdministrationUrl($this->router);
        } catch (RoutingException $e) {
            if ($e->getErrorCode() !== RoutingException::INVALID_APP_URL) {
                throw $e;
            }

            throw AdminUserException::invalidAppUrl((string) $e->getParameter('appUrl'));
        }

        $recoveryUrl = $url . '#/login/admin_user-recovery/' . $hash;

        $channel = $this->getChannel($context);

        $channelContext = $this->channelContextService->get(
            new ChannelContextServiceParameters(
                $channel->getId(),
                Uuid::randomHex(),
                $channel->getLanguageId(),
                $channel->getCurrencyId(),
                null,
                $context,
                null,
            )
        );

        $this->dispatcher->dispatch(
            new AdminUserRecoveryRequestEvent($recovery, $recoveryUrl, $channelContext->getContext()),
            AdminUserRecoveryRequestEvent::EVENT_NAME
        );
    }

    public function checkHash(string $hash, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('hash', $hash)
        );

        $recovery = $this->getUserRecovery($criteria, $context);

        $validDateTime = $this->clock->now()->sub(new \DateInterval('PT2H'));

        return $recovery && $validDateTime < $recovery->getCreatedAt();
    }

    public function updatePassword(string $hash, #[\SensitiveParameter] string $password, Context $context): bool
    {
        if (!$this->checkHash($hash, $context)) {
            return false;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $hash));

        $recovery = $this->getUserRecovery($criteria, $context);
        \assert($recovery instanceof AdminUserRecoveryEntity); // It can't be null as we checked the hash before

        $updateData = [
            'id' => $recovery->getUserId(),
            'password' => $password,
        ];

        $this->admin_userRepo->update([$updateData], $context);

        $this->deleteRecoveryForUser($recovery, $context);

        return true;
    }

    public function getUserByHash(string $hash, Context $context): ?AdminUserEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $hash));
        $criteria->addAssociation('admin_user');

        $admin_user = $this->getUserRecovery($criteria, $context);

        return $admin_user?->getUser();
    }

    private function getUserByEmail(string $admin_userEmail, Context $context): ?AdminUserEntity
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new EqualsFilter('email', $admin_userEmail)
        );

        return $this->admin_userRepo->search($criteria, $context)->getEntities()->first();
    }

    private function getUserRecovery(Criteria $criteria, Context $context): ?AdminUserRecoveryEntity
    {
        return $this->admin_userRecoveryRepo->search($criteria, $context)->getEntities()->first();
    }

    private function deleteRecoveryForUser(AdminUserRecoveryEntity $admin_userRecoveryEntity, Context $context): void
    {
        $recoveryData = [
            'id' => $admin_userRecoveryEntity->getId(),
        ];

        $this->admin_userRecoveryRepo->delete([$recoveryData], $context);
    }

    /**
     * pick a random sales channel to form sales channel context as flow builder requires it
     */
    private function getChannel(Context $context): ChannelEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new NotEqualsFilter('typeId', Defaults::CHANNEL_TYPE_BLOG_COMPARISON));

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->first();

        if (!$channel instanceof ChannelEntity) {
            throw AdminUserException::channelNotFound();
        }

        return $channel;
    }
}
