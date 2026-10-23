<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Cocur\Slugify\SlugifyInterface;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Maintains the SEO URLs of user group registration forms for headless (API type)
 * sales channels with an external frontend domain. The frontend counterpart lives in
 * the Frontend bundle (UserGroupSubscriber), so headless setups without the
 * Frontend bundle still get their rows.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\User\Subscriber\UserGroupRegistrationSeoUrlSubscriberTest
 */
class UserGroupRegistrationSeoUrlSubscriber implements EventSubscriberInterface
{
    private const ROUTE_NAME = 'store-api.user-group-registration.config';

    /**
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $userGroupRepository,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EntityRepository $languageRepository,
        private readonly SeoUrlPersister $persister,
        private readonly SlugifyInterface $slugify
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'user_group_translation.written' => 'updatedUserGroup',
            'user_group_registration_channels.written' => 'newChannelAddedToUserGroup',
            'user_group_translation.deleted' => 'deleteUserGroup',
            'channel_domain.written' => 'updatedChannelDomain',
            'channel_domain.deleted' => 'updatedChannelDomain',
        ];
    }

    /**
     * @param EntityWrittenEvent<array<string, string>> $event
     */
    public function newChannelAddedToUserGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['userGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $this->updateSeoUrls($ids, $event->getContext());
    }

    /**
     * @param EntityWrittenEvent<array<string, string>> $event
     */
    public function updatedUserGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getResults()->withPayloadProperties('registrationTitle') as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['userGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $this->updateSeoUrls($ids, $event->getContext());
    }

    /**
     * @param EntityDeletedEvent<array<string, string>> $event
     */
    public function deleteUserGroup(EntityDeletedEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['userGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('foreignKey', $ids))
            ->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        $ids = $this->seoUrlRepository->searchIds($criteria, $event->getContext())->getPrimaryKeyData();
        if ($ids === []) {
            return;
        }

        $this->seoUrlRepository->delete($ids, $event->getContext());
    }

    /**
     * Domain changes (adding or removing domains, toggling the external frontend flag)
     * change which rows may exist, so all rows of the route are recomputed and rows that
     * no longer qualify are removed.
     *
     * @param EntityWrittenEvent<array<string, string>> $event
     */
    public function updatedChannelDomain(EntityWrittenEvent $event): void
    {
        $this->updateSeoUrls(null, $event->getContext());
    }

    /**
     * @param list<string>|null $ids null recomputes all user groups with an active registration
     */
    private function updateSeoUrls(?array $ids, Context $context): void
    {
        $criteria = ($ids === null ? new Criteria() : new Criteria($ids))
            ->addFilter(new EqualsFilter('registrationActive', true))
            ->addAssociations(['registrationChannels.languages', 'registrationChannels.domains', 'translations']);

        $groups = $this->userGroupRepository->search($criteria, $context)->getEntities();
        $buildUrls = [];

        foreach ($groups as $group) {
            if ($group->getRegistrationChannels() === null) {
                continue;
            }

            foreach ($group->getRegistrationChannels() as $registrationChannel) {
                if ($registrationChannel->getTypeId() !== Defaults::CHANNEL_TYPE_API) {
                    continue;
                }

                if ($registrationChannel->getLanguages() === null) {
                    continue;
                }

                $languageIds = $registrationChannel->getLanguages()->getIds();
                $languageCriteria = new Criteria($languageIds);
                $languageCriteria->addFilter(new EqualsFilter('active', true));

                $languageCollection = $this->languageRepository->search($languageCriteria, $context)->getEntities();

                foreach ($languageIds as $languageId) {
                    $language = $languageCollection->get($languageId);
                    if (!$language) {
                        continue;
                    }

                    // rows are only created for languages with an external frontend domain,
                    // mirroring the SEO URL generation of blogs, categories and landing pages
                    if (!$this->hasExternalFrontendDomain($registrationChannel, $languageId)) {
                        continue;
                    }

                    $title = $this->getTranslatedTitle($group->getTranslations(), $language);

                    if ($title === '') {
                        continue;
                    }

                    if (!isset($buildUrls[$languageId])) {
                        $buildUrls[$languageId] = [
                            'languageId' => $languageId,
                            'urls' => [],
                            'channel' => $registrationChannel,
                        ];
                    }

                    $buildUrls[$languageId]['urls'][] = [
                        'channelId' => $registrationChannel->getId(),
                        'foreignKey' => $group->getId(),
                        'routeName' => self::ROUTE_NAME,
                        'pathInfo' => '/store-api/user-group-registration/config/' . $group->getId(),
                        'isCanonical' => true,
                        'isDeleted' => false,
                        'seoPathInfo' => '/' . $this->slugify->slugify($title),
                    ];
                }
            }
        }

        foreach ($buildUrls as $config) {
            $languageContext = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                $context->getCurrencyId(),
                [$config['languageId']]
            );

            $this->persister->updateSeoUrls(
                $languageContext,
                self::ROUTE_NAME,
                array_column($config['urls'], 'foreignKey'),
                $config['urls'],
                $config['channel']
            );
        }

        if ($ids === null) {
            $this->removeObsoleteSeoUrls($buildUrls, $context);
        }
    }

    /**
     * @param array<string, array{languageId: string, urls: list<array<string, mixed>>, channel: ChannelEntity}> $buildUrls
     */
    private function removeObsoleteSeoUrls(array $buildUrls, Context $context): void
    {
        $validKeys = [];

        foreach ($buildUrls as $config) {
            foreach ($config['urls'] as $url) {
                $validKeys[$url['channelId'] . '-' . $config['languageId'] . '-' . $url['foreignKey']] = true;
            }
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        $obsoleteIds = [];

        foreach ($this->seoUrlRepository->search($criteria, $context)->getEntities() as $seoUrl) {
            $key = $seoUrl->getChannelId() . '-' . $seoUrl->getLanguageId() . '-' . $seoUrl->getForeignKey();

            if (!isset($validKeys[$key])) {
                $obsoleteIds[] = ['id' => $seoUrl->getId()];
            }
        }

        if ($obsoleteIds === []) {
            return;
        }

        $this->seoUrlRepository->delete($obsoleteIds, $context);
    }

    private function hasExternalFrontendDomain(ChannelEntity $channel, string $languageId): bool
    {
        $domains = $channel->getDomains();
        if ($domains === null) {
            return false;
        }

        foreach ($domains as $domain) {
            if ($domain->getLanguageId() === $languageId && $domain->getIsExternalFrontend()) {
                return true;
            }
        }

        return false;
    }

    private function getTranslatedTitle(?UserGroupTranslationCollection $translations, LanguageEntity $language): string
    {
        if ($translations === null) {
            return '';
        }

        // Requested translation
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === $language->getId() && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        // Inherited translation
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === $language->getParentId() && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        // System Language
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === Defaults::LANGUAGE_SYSTEM && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        return '';
    }
}
