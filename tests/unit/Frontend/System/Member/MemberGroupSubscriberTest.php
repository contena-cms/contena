<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\System\Member;

use Cocur\Slugify\SlugifyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Aggregate\MemberGroupTranslation\MemberGroupTranslationCollection;
use Contena\Core\System\Member\Aggregate\MemberGroupTranslation\MemberGroupTranslationEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\System\Member\MemberGroupSubscriber;

/**
 * @internal
 */
#[CoversClass(MemberGroupSubscriber::class)]
class MemberGroupSubscriberTest extends TestCase
{
    public function testGeneratedUrlsAreActive(): void
    {
        $context = Context::createDefaultContext();
        $memberGroupId = Uuid::randomHex();
        $languageId = Uuid::randomHex();

        $language = new LanguageEntity();
        $language->setId($languageId);
        $language->setActive(true);

        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setTypeId(Defaults::CHANNEL_TYPE_WEB);
        $channel->setLanguages(new LanguageCollection([$language]));

        $translation = new MemberGroupTranslationEntity();
        $translation->setId(Uuid::randomHex());
        $translation->setLanguageId($languageId);
        $translation->setRegistrationTitle('Registration');

        $memberGroup = new MemberGroupEntity();
        $memberGroup->setId($memberGroupId);
        $memberGroup->setRegistrationActive(true);
        $memberGroup->setRegistrationChannels(new ChannelCollection([$channel]));
        $memberGroup->setTranslations(new MemberGroupTranslationCollection([$translation]));

        $memberGroupRepository = StaticEntityRepository::of(MemberGroupCollection::class, [new MemberGroupCollection([$memberGroup])]);

        $languageRepository = StaticEntityRepository::of(LanguageCollection::class, [new LanguageCollection([$language])]);

        $persister = $this->createMock(SeoUrlPersister::class);
        $persister->expects($this->once())
            ->method('updateSeoUrls')
            ->with(
                static::isInstanceOf(Context::class),
                'frontend.account.member-group-registration.page',
                [$memberGroupId],
                static::callback(static function (iterable $seoUrls): bool {
                    foreach ($seoUrls as $seoUrl) {
                        if ($seoUrl['isDeleted'] !== false) {
                            return false;
                        }
                    }

                    return true;
                }),
                static::isInstanceOf(ChannelEntity::class)
            );

        $slugify = static::createStub(SlugifyInterface::class);
        $slugify->method('slugify')->willReturn('registration');

        $subscriber = new MemberGroupSubscriber(
            $memberGroupRepository,
            static::createStub(EntityRepository::class),
            $languageRepository,
            $persister,
            $slugify,
        );

        /** @var array<string, string> $primaryKey */
        $primaryKey = ['memberGroupId' => $memberGroupId];

        $subscriber->newChannelAddedToMemberGroup(new EntityWrittenEvent(
            'member_group_registration_channel',
            [new EntityWriteResult(
                $primaryKey,
                [],
                'member_group_registration_channel',
                EntityWriteResult::OPERATION_INSERT,
            )],
            $context,
        ));
    }
}
