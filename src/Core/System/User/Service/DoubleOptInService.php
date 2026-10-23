<?php declare(strict_types=1);

namespace Contena\Core\System\User\Service;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Event\DoubleOptInGuestOrderEvent;
use Contena\Core\System\User\Event\UserConfirmRegisterUrlEvent;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DoubleOptInService
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<ChannelDomainCollection> $channelDomainRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $channelDomainRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function sendDoubleOptInMail(
        UserEntity $user,
        ChannelContext $context,
        string $domainUrl,
        ?string $redirectTo = null,
        ?string $redirectParameters = null
    ): void {
        $url = $domainUrl . $this->buildConfirmPath($user, $context);

        if ($redirectTo) {
            $params = \is_string($redirectParameters) ? (\json_decode($redirectParameters, true) ?? []) : [];
            $url .= '&' . \http_build_query(array_merge(['redirectTo' => $redirectTo], $params));
        }

        $event = $user->getGuest()
            ? new DoubleOptInGuestOrderEvent($user, $context, $url)
            : new UserDoubleOptInRegistrationEvent($user, $context, $url);

        $this->eventDispatcher->dispatch($event);
    }

    public function resendDoubleOptInMail(UserEntity $user, ChannelContext $context): void
    {
        $resendInterval = $this->systemConfigService->getInt(
            'core.loginRegistration.doubleOptInResendInterval',
            $context->getChannelId()
        );

        if ($resendInterval <= 0) {
            return;
        }

        $sentDate = $user->getDoubleOptInEmailSentDate();
        if ($sentDate === null) {
            return;
        }

        $threshold = $this->clock->now()->modify('-' . $resendInterval . ' hours');
        if ($sentDate > $threshold) {
            return;
        }

        $this->sendDoubleOptInMail($user, $context, $this->resolveDomainUrl($context, $user->getLanguageId()));

        // Update sent date as this serves as cooldown for subsequent login attempts
        $this->userRepository->update([
            ['id' => $user->getId(), 'doubleOptInEmailSentDate' => $this->clock->now()],
        ], $context->getContext());
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    public function mapUserDoubleOptInData(array $user, ChannelContext $context): array
    {
        if (!$this->isDoubleOptInEnabled((bool) $user['guest'], $context)) {
            return $user;
        }

        $user['doubleOptInRegistration'] = true;
        $user['doubleOptInEmailSentDate'] = $this->clock->now();
        $user['hash'] = Uuid::randomHex();

        return $user;
    }

    public function isDoubleOptInEnabled(bool $isGuest, ChannelContext $context): bool
    {
        $configKey = $isGuest
            ? 'core.loginRegistration.doubleOptInGuestOrder'
            : 'core.loginRegistration.doubleOptInRegistration';

        return $this->systemConfigService->getBool($configKey, $context->getChannelId());
    }

    private function buildConfirmPath(UserEntity $user, ChannelContext $context): string
    {
        $urlTemplate = $this->systemConfigService->getString(
            'core.loginRegistration.confirmationUrl',
            $context->getChannelId()
        ) ?: '/registration/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%';

        $emailHash = Hasher::hash($user->getEmail(), 'sha1');

        $urlEvent = new UserConfirmRegisterUrlEvent(
            $context,
            $urlTemplate,
            $emailHash,
            $user->getHash() ?? '',
            $user
        );
        $this->eventDispatcher->dispatch($urlEvent);

        return str_replace(
            ['%%HASHEDEMAIL%%', '%%SUBSCRIBEHASH%%'],
            [$emailHash, $user->getHash() ?? ''],
            $urlEvent->getConfirmUrl()
        );
    }

    /**
     * Resolves the base domain URL for confirmation links.
     *
     * Uses the `core.loginRegistration.doubleOptInDomain` system config when set,
     * and falls back to the first configured domain of the sales channel otherwise.
     * Intended for contexts where no HTTP request is available (e.g. Store API login).
     */
    private function resolveDomainUrl(ChannelContext $context, string $languageId): string
    {
        $domainUrl = $this->systemConfigService->getString(
            'core.loginRegistration.doubleOptInDomain',
            $context->getChannelId()
        );

        if ($domainUrl) {
            return $domainUrl;
        }

        $domain = null;

        $domains = $context->getChannel()->getDomains();
        // Domains should never be null, as they are loaded in the `BaseChannelContextFactory`
        if ($domains !== null) {
            // If the domain id has been set, by the request, we use this domain id
            $domainId = $context->getDomainId();
            if ($domainId !== null) {
                $domain = $domains->get($domainId);
            }

            // Try to determine the correct domain by the user language id
            if ($domain === null) {
                foreach ($domains as $d) {
                    if ($d->getLanguageId() === $languageId) {
                        $domain = $d;
                        break;
                    }
                }
            }
        }

        if ($domain === null) {
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('channelId', $context->getChannelId()))
                ->setLimit(1);

            $domain = $this->channelDomainRepository
                ->search($criteria, $context->getContext())
                ->getEntities()
                ->first();
        }

        return $domain?->getUrl() ?? '';
    }
}
