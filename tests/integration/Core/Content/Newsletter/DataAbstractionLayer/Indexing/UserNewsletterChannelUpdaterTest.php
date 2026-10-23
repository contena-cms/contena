<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Newsletter\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Contena\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexer;
use Contena\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexingMessage;
use Contena\Core\Content\Newsletter\Channel\NewsletterSubscribeRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
class UserNewsletterChannelsUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    public function testUpdatesUserOnNewsletterSubscription(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);
        $alternativeChannel = $this->createChannel([
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/a',
            ]],
        ]);

        // create unrelated newsletter recipient which should not be involved when updating the user's newsletter sales channel ids
        $this->createNewsletterRecipient($context, 'foobar@example.com', TestDefaults::CHANNEL);

        // subscribe to default sales channel and assert that array contains only that id
        $newsletterRecipientA = $this->createNewsletterRecipient($context, $email, TestDefaults::CHANNEL);

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);

        static::assertNotNull($user->getNewsletterChannelIds());
        static::assertCount(1, $user->getNewsletterChannelIds());
        static::assertContains(TestDefaults::CHANNEL, $user->getNewsletterChannelIds());

        // subscribe to alternative sales channel and assert that array contains ids of both sales channels
        $newsletterRecipientB = $this->createNewsletterRecipient($context, $email, $alternativeChannel['id'], NewsletterSubscribeRoute::STATUS_DIRECT);

        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);

        static::assertNotNull($user->getNewsletterChannelIds());
        static::assertCount(2, $user->getNewsletterChannelIds());
        static::assertContains(TestDefaults::CHANNEL, $user->getNewsletterChannelIds());
        static::assertContains($alternativeChannel['id'], $user->getNewsletterChannelIds());

        // unsubscribe first newsletter
        $this->unsubscribeNewsletterRecipient($context, $newsletterRecipientA);

        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);

        static::assertNotNull($user->getNewsletterChannelIds());
        static::assertCount(1, $user->getNewsletterChannelIds());
        static::assertContains($alternativeChannel['id'], $user->getNewsletterChannelIds());

        // unsubscribe second newsletter
        $this->unsubscribeNewsletterRecipient($context, $newsletterRecipientB);

        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);

        static::assertNull($user->getNewsletterChannelIds());
    }

    public function testUpdatesUserOnCreationWithExistingNewsletterSubscription(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $this->createNewsletterRecipient($context, $email, TestDefaults::CHANNEL);
        $userId = $this->createUser($email);

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);

        static::assertNotNull($user->getNewsletterChannelIds());
        static::assertCount(1, $user->getNewsletterChannelIds());
        static::assertContains(TestDefaults::CHANNEL, $user->getNewsletterChannelIds());
    }

    public function testDeleteNewsletterRecipientUpdatesUser(): void
    {
        $context = Context::createDefaultContext();
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);
        $alternativeChannel = $this->createChannel([
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/a',
            ]],
        ]);

        $newsletterRecipientA = $this->createNewsletterRecipient($context, $email, TestDefaults::CHANNEL);
        $newsletterRecipientB = $this->createNewsletterRecipient($context, $email, $alternativeChannel['id'], NewsletterSubscribeRoute::STATUS_DIRECT);

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);

        static::assertNotNull($user->getNewsletterChannelIds());
        static::assertCount(2, $user->getNewsletterChannelIds());
        static::assertContains(TestDefaults::CHANNEL, $user->getNewsletterChannelIds());
        static::assertContains($alternativeChannel['id'], $user->getNewsletterChannelIds());

        // delete first newsletter recipient
        $this->deleteNewsletterRecipient($context, $newsletterRecipientA);

        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);

        static::assertNotNull($user->getNewsletterChannelIds());
        static::assertCount(1, $user->getNewsletterChannelIds());
        static::assertContains($alternativeChannel['id'], $user->getNewsletterChannelIds());

        // delete second newsletter recipient
        $this->deleteNewsletterRecipient($context, $newsletterRecipientB);

        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);

        static::assertNull($user->getNewsletterChannelIds());
    }

    #[DataProvider('createDataProvider')]
    public function testUpdateEmailNewsletterRecipientUpdateUser(\Closure $newsletterRecipientClosure, \Closure $criteriaClosure): void
    {
        $context = Context::createDefaultContext();

        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);

        $newsletterRecipientIds = $newsletterRecipientClosure($context, $email, $this);
        $criteria = $newsletterRecipientIds === [] ? $criteriaClosure(new Criteria(), $email) : $criteriaClosure(new Criteria(), $newsletterRecipientIds);

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);
        /** @var EntityRepository<NewsletterRecipientCollection> $newsletterRecipientRepository */
        $newsletterRecipientRepository = static::getContainer()->get('newsletter_recipient.repository');
        $newsletterRecipients = $newsletterRecipientRepository->search($criteria, $context)->getEntities();

        static::assertCount($newsletterRecipients->count(), $newsletterRecipientIds);
        static::assertSame($user->getEmail(), $email);

        foreach ($newsletterRecipients as $newsletterRecipient) {
            static::assertSame($newsletterRecipient->getEmail(), $email);
            static::assertSame($newsletterRecipient->getEmail(), $user->getEmail());
        }

        static::getContainer()->get('user.repository')->upsert(
            [['id' => $userId, 'email' => 'ytn@contena.cn']],
            $context
        );

        $user = $userRepository->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($user);
        $newsletterRecipients = $newsletterRecipientRepository->search($criteria, $context)->getEntities();

        static::assertCount($newsletterRecipients->count(), $newsletterRecipientIds);
        static::assertSame($user->getEmail(), 'ytn@contena.cn');

        foreach ($newsletterRecipients as $newsletterRecipient) {
            static::assertSame($newsletterRecipient->getEmail(), 'ytn@contena.cn');
            static::assertSame($newsletterRecipient->getEmail(), $user->getEmail());
        }
    }

    public function testEmailChangeRevokesConfirmedNewsletterOptIn(): void
    {
        $context = Context::createDefaultContext();

        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);

        $recipientId = $this->createNewsletterRecipient(
            $context,
            $email,
            TestDefaults::CHANNEL,
            NewsletterSubscribeRoute::STATUS_OPT_IN,
            '2025-01-01 10:00:00'
        );

        /** @var EntityRepository<NewsletterRecipientCollection> $newsletterRecipientRepository */
        $newsletterRecipientRepository = static::getContainer()->get('newsletter_recipient.repository');

        $recipient = $newsletterRecipientRepository->search(new Criteria([$recipientId]), $context)->getEntities()->first();
        static::assertNotNull($recipient);
        static::assertSame(NewsletterSubscribeRoute::STATUS_OPT_IN, $recipient->getStatus());
        static::assertNotNull($recipient->getConfirmedAt());

        static::getContainer()->get('user.repository')->upsert(
            [['id' => $userId, 'email' => 'someone-else@example.com']],
            $context
        );

        $recipient = $newsletterRecipientRepository->search(new Criteria([$recipientId]), $context)->getEntities()->first();
        static::assertNotNull($recipient);
        static::assertSame('someone-else@example.com', $recipient->getEmail());
        static::assertSame(NewsletterSubscribeRoute::STATUS_NOT_SET, $recipient->getStatus());
        static::assertNull($recipient->getConfirmedAt());
    }

    public function testEmailChangeKeepsNewsletterStatusWithoutDoubleOptIn(): void
    {
        $context = Context::createDefaultContext();

        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);

        $recipientId = $this->createNewsletterRecipient(
            $context,
            $email,
            TestDefaults::CHANNEL,
            NewsletterSubscribeRoute::STATUS_DIRECT
        );

        static::getContainer()->get('user.repository')->upsert(
            [['id' => $userId, 'email' => 'someone-else@example.com']],
            $context
        );

        /** @var EntityRepository<NewsletterRecipientCollection> $newsletterRecipientRepository */
        $newsletterRecipientRepository = static::getContainer()->get('newsletter_recipient.repository');
        $recipient = $newsletterRecipientRepository->search(new Criteria([$recipientId]), $context)->getEntities()->first();
        static::assertNotNull($recipient);
        static::assertSame('someone-else@example.com', $recipient->getEmail());
        static::assertSame(NewsletterSubscribeRoute::STATUS_DIRECT, $recipient->getStatus());
    }

    public static function createDataProvider(): \Generator
    {
        yield 'Email Newsletter Recipient Not Registered' => [
            static fn (Context $context, string $email): array => [],
            static fn (Criteria $criteria, string $email): Criteria => $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('email', $email),
                new EqualsFilter('email', 'ytn@contena.cn'),
            ])),
        ];

        yield 'Email Newsletter Recipient Registered' => [
            static function (Context $context, string $email, self $me): array {
                $newsletterRecipientId = $me->createNewsletterRecipient($context, $email, TestDefaults::CHANNEL);

                return [
                    $newsletterRecipientId,
                ];
            },
            static fn (Criteria $criteria, array $ids): Criteria => $criteria->setIds($ids),
        ];

        yield 'Email Newsletter Recipient Registered Multiple' => [
            static function (Context $context, string $email, self $me): array {
                $channel = $me->createChannel();

                $newsletterRecipientId = $me->createNewsletterRecipient($context, $email, TestDefaults::CHANNEL);
                $newsletterRecipientId2 = $me->createNewsletterRecipient($context, $email, $channel['id']);

                return [
                    $newsletterRecipientId,
                    $newsletterRecipientId2,
                ];
            },
            static fn (Criteria $criteria, array $ids): Criteria => $criteria->setIds($ids),
        ];
    }

    private function createNewsletterRecipient(
        Context $context,
        string $email,
        string $channelId,
        string $status = NewsletterSubscribeRoute::STATUS_OPT_IN,
        ?string $confirmedAt = null
    ): string {
        $id = Uuid::randomHex();

        $newsletterRecipient = [
            'id' => $id,
            'email' => $email,
            'status' => $status,
            'hash' => Uuid::randomHex(),
            'channelId' => $channelId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'confirmedAt' => $confirmedAt,
        ];

        static::getContainer()
            ->get('newsletter_recipient.repository')
            ->upsert([$newsletterRecipient], $context);

        return $id;
    }

    private function unsubscribeNewsletterRecipient(
        Context $context,
        string $id
    ): string {
        $newsletterRecipient = [
            'id' => $id,
            'status' => NewsletterSubscribeRoute::STATUS_OPT_OUT,
        ];

        static::getContainer()
            ->get('newsletter_recipient.repository')
            ->upsert([$newsletterRecipient], $context);

        return $id;
    }

    private function deleteNewsletterRecipient(
        Context $context,
        string $id
    ): void {
        $newsletterRecipient = [
            'id' => $id,
        ];

        static::getContainer()
            ->get('newsletter_recipient.repository')
            ->delete([$newsletterRecipient], $context);

        $messageBus = static::getContainer()->get('messenger.default_bus');
        static::assertInstanceOf(TraceableMessageBus::class, $messageBus);

        foreach ($messageBus->getDispatchedMessages() as $message) {
            if (isset($message['message']) && $message['message'] instanceof NewsletterRecipientIndexingMessage) {
                static::getContainer()->get(NewsletterRecipientIndexer::class)->handle($message['message']);
            }
        }
    }
}
