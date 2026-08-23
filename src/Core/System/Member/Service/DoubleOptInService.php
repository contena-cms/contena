<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Service;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Event\MemberConfirmRegisterUrlEvent;
use Contena\Core\System\Member\Event\MemberDoubleOptInRegistrationEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DoubleOptInService
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $memberRepository
     * @param EntityRepository<ChannelDomainCollection> $channelDomainRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $channelDomainRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function sendDoubleOptInMail(
        MemberEntity $member,
        ChannelContext $context,
        string $domainUrl,
        ?string $redirectTo = null,
        ?string $redirectParameters = null,
    ): void {
        $url = $domainUrl . $this->buildConfirmPath($member, $context);

        if ($redirectTo) {
            $parameters = \is_string($redirectParameters) ? (json_decode($redirectParameters, true) ?? []) : [];
            $url .= '&' . http_build_query(array_merge(['redirectTo' => $redirectTo], $parameters));
        }

        $this->eventDispatcher->dispatch(new MemberDoubleOptInRegistrationEvent($member, $context, $url));
    }

    public function resendDoubleOptInMail(MemberEntity $member, ChannelContext $context): void
    {
        $resendInterval = $this->systemConfigService->getInt(
            'core.loginRegistration.doubleOptInResendInterval',
            $context->getChannelId(),
        );

        if ($resendInterval <= 0) {
            return;
        }

        $sentDate = $member->getDoubleOptInEmailSentDate();
        if ($sentDate === null) {
            return;
        }

        $threshold = $this->clock->now()->modify('-' . $resendInterval . ' hours');
        if ($sentDate > $threshold) {
            return;
        }

        $this->sendDoubleOptInMail($member, $context, $this->resolveDomainUrl($context, $member->getLanguageId()));

        $this->memberRepository->update([
            ['id' => $member->getId(), 'doubleOptInEmailSentDate' => $this->clock->now()],
        ], $context->getContext());
    }

    /**
     * @param array<string, mixed> $member
     *
     * @return array<string, mixed>
     */
    public function mapMemberDoubleOptInData(array $member, ChannelContext $context): array
    {
        if (!$this->systemConfigService->getBool('core.loginRegistration.doubleOptInRegistration', $context->getChannelId())) {
            return $member;
        }

        $member['doubleOptInRegistration'] = true;
        $member['doubleOptInEmailSentDate'] = $this->clock->now();
        $member['hash'] = Uuid::randomHex();

        return $member;
    }

    private function buildConfirmPath(MemberEntity $member, ChannelContext $context): string
    {
        $urlTemplate = $this->systemConfigService->getString(
            'core.loginRegistration.confirmationUrl',
            $context->getChannelId(),
        )
            ?: '/registration/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%';

        $emailHash = Hasher::hash($member->getEmail(), 'sha1');

        $urlEvent = new MemberConfirmRegisterUrlEvent(
            $context,
            $urlTemplate,
            $emailHash,
            $member->getHash(),
            $member,
        );
        $this->eventDispatcher->dispatch($urlEvent);

        return str_replace(
            ['%%HASHEDEMAIL%%', '%%SUBSCRIBEHASH%%'],
            [$emailHash, $member->getHash() ?? ''],
            $urlEvent->getConfirmUrl(),
        );
    }

    private function resolveDomainUrl(ChannelContext $context, string $languageId): string
    {
        $domainUrl = $this->systemConfigService->getString(
            'core.loginRegistration.doubleOptInDomain',
            $context->getChannelId(),
        );
        if ($domainUrl !== '') {
            return $domainUrl;
        }

        $domain = null;
        $domains = $context->getChannel()->getDomains();
        if ($domains !== null) {
            $domainId = $context->getDomainId();
            if ($domainId !== null) {
                $domain = $domains->get($domainId);
            }

            if ($domain === null) {
                foreach ($domains as $candidate) {
                    if ($candidate->getLanguageId() === $languageId) {
                        $domain = $candidate;
                        break;
                    }
                }
            }
        }

        if ($domain === null) {
            $criteria = new Criteria()
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
