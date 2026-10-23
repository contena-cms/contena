<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Checkout\User;

use Cocur\Slugify\SlugifyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationCollection;
use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationEntity;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Checkout\User\UserGroupSubscriber;

/**
 * @internal
 */
#[CoversClass(UserGroupSubscriber::class)]
class UserGroupSubscriberTest extends TestCase
{
    public function testGeneratedUrlsAreActive(): void
    {
        $context = Context::createDefaultContext();
        $userGroupId = Uuid::randomHex();
        $languageId = Uuid::randomHex();

        $language = new LanguageEntity();
        $language->setId($languageId);
        $language->setActive(true);

        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setTypeId(Defaults::CHANNEL_TYPE_FRONTEND);
        $channel->setLanguages(new LanguageCollection([$language]));

        $translation = new UserGroupTranslationEntity();
        $translation->setId(Uuid::randomHex());
        $translation->setLanguageId($languageId);
        $translation->setRegistrationTitle('Registration');

        $userGroup = new UserGroupEntity();
        $userGroup->setId($userGroupId);
        $userGroup->setRegistrationActive(true);
        $userGroup->setRegistrationChannels(new ChannelCollection([$channel]));
        $userGroup->setTranslations(new UserGroupTranslationCollection([$translation]));

        $userGroupRepository = new StaticEntityRepository([new UserGroupCollection([$userGroup])]);

        $languageRepository = new StaticEntityRepository([new LanguageCollection([$language])]);

        $persister = $this->createMock(SeoUrlPersister::class);
        $persister->expects($this->once())
            ->method('updateSeoUrls')
            ->with(
                static::isInstanceOf(Context::class),
                'frontend.account.user-group-registration.page',
                [$userGroupId],
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

        $subscriber = new UserGroupSubscriber(
            $userGroupRepository,
            static::createStub(EntityRepository::class),
            $languageRepository,
            $persister,
            $slugify
        );

        /** @var array<string, string> $primaryKey */
        $primaryKey = ['userGroupId' => $userGroupId];

        $subscriber->newChannelAddedToUserGroup(new EntityWrittenEvent(
            'user_group_registration_channels',
            [new EntityWriteResult(
                $primaryKey,
                [],
                'user_group_registration_channels',
                EntityWriteResult::OPERATION_INSERT
            )],
            $context
        ));
    }
}
