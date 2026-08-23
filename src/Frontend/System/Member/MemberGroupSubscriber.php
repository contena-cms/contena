<?php declare(strict_types=1);

namespace Contena\Frontend\System\Member;

use Cocur\Slugify\SlugifyInterface;
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
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Aggregate\MemberGroupTranslation\MemberGroupTranslationCollection;
use Contena\Tests\Integration\Frontend\System\Member\MemberGroupSubscriberTest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see MemberGroupSubscriberTest
 */
class MemberGroupSubscriber implements EventSubscriberInterface
{
    private const string ROUTE_NAME = 'frontend.account.member-group-registration.page';

    /**
     * @internal
     *
     * @param EntityRepository<MemberGroupCollection> $memberGroupRepository
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $memberGroupRepository,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EntityRepository $languageRepository,
        private readonly SeoUrlPersister $persister,
        private readonly SlugifyInterface $slugify,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'member_group_translation.written' => 'updatedMemberGroup',
            'member_group_registration_channel.written' => 'newChannelAddedToMemberGroup',
            'member_group_translation.deleted' => 'deleteMemberGroup',
        ];
    }

    /**
     * @param EntityWrittenEvent<array<string, string>> $event
     */
    public function newChannelAddedToMemberGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['memberGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $this->createUrls($ids, $event->getContext());
    }

    /**
     * @param EntityWrittenEvent<array<string, string>> $event
     */
    public function updatedMemberGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getResults()->withPayloadProperties('registrationTitle') as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['memberGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $this->createUrls($ids, $event->getContext());
    }

    /**
     * @param EntityDeletedEvent<array<string, string>> $event
     */
    public function deleteMemberGroup(EntityDeletedEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['memberGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $criteria = new Criteria()
            ->addFilter(new EqualsAnyFilter('foreignKey', $ids))
            ->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        $ids = $this->seoUrlRepository->searchIds($criteria, $event->getContext())->getIds();

        if ($ids === []) {
            return;
        }

        $this->seoUrlRepository->delete(array_map(static fn (string $id) => ['id' => $id], $ids), $event->getContext());
    }

    /**
     * @param list<string> $ids
     */
    private function createUrls(array $ids, Context $context): void
    {
        $criteria = new Criteria($ids)
            ->addFilter(new EqualsFilter('registrationActive', true))
            ->addAssociations(['registrationChannels.languages', 'translations']);

        $criteria->getAssociation('registrationChannels')
            ->addFilter(new NandFilter([new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_API)]));

        $groups = $this->memberGroupRepository->search($criteria, $context)->getEntities();
        $buildUrls = [];

        foreach ($groups as $group) {
            if ($group->getRegistrationChannels() === null) {
                continue;
            }

            foreach ($group->getRegistrationChannels() as $registrationChannel) {
                if ($registrationChannel->getLanguages() === null) {
                    continue;
                }

                if ($registrationChannel->getTypeId() === Defaults::CHANNEL_TYPE_API) {
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

                    $title = $this->getTranslatedTitle($group->getTranslations(), $language);

                    if ($title === '') {
                        continue;
                    }

                    if (!isset($buildUrls[$languageId])) {
                        $buildUrls[$languageId] = [
                            'urls' => [],
                            'channel' => $registrationChannel,
                        ];
                    }

                    $buildUrls[$languageId]['urls'][] = [
                        'channelId' => $registrationChannel->getId(),
                        'foreignKey' => $group->getId(),
                        'routeName' => self::ROUTE_NAME,
                        'pathInfo' => '/member-group-registration/' . $group->getId(),
                        'isCanonical' => true,
                        'isDeleted' => false,
                        'seoPathInfo' => '/' . $this->slugify->slugify($title),
                    ];
                }
            }
        }

        foreach ($buildUrls as $languageId => $config) {
            $languageContext = new Context(
                $context->getSource(),
                [$languageId],
                $context->getVersionId(),
                $context->considerInheritance(),
                $context->getRuleIds(),
            );

            $this->persister->updateSeoUrls(
                $languageContext,
                self::ROUTE_NAME,
                array_column($config['urls'], 'foreignKey'),
                $config['urls'],
                $config['channel'],
            );
        }
    }

    private function getTranslatedTitle(?MemberGroupTranslationCollection $translations, LanguageEntity $language): string
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
