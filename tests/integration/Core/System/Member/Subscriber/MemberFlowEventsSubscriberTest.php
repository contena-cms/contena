<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\Subscriber\MemberFlowEventsSubscriber;
use Contena\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class MemberFlowEventsSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testAdminApiLanguageErrorDeletesCreatedMember(): void
    {
        $memberId = $this->createMember();
        $expectedException = ChannelException::providedLanguageNotAvailable('de-DE', ['en-GB']);

        $restorer = $this->createMock(ChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByMember')
            ->willThrowException($expectedException);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $memberIndexer = $this->createMock(EntityIndexer::class);
        $memberIndexer->expects($this->never())->method('handle');

        $subscriber = new MemberFlowEventsSubscriber(
            $dispatcher,
            $restorer,
            $memberIndexer,
            static::getContainer()->get(Connection::class),
        );

        $event = new EntityWrittenEvent('member', [
            new EntityWriteResult(
                $memberId,
                [
                    'createdAt' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'id' => $memberId,
                ],
                'member',
                EntityWriteResult::OPERATION_INSERT,
            ),
        ], Context::createDefaultContext(new AdminApiSource(null)));

        try {
            $subscriber->onMemberWritten($event);
            static::fail('Expected a channel language exception.');
        } catch (ChannelException $exception) {
            static::assertSame($expectedException, $exception);
        }

        static::assertFalse($this->memberExists($memberId));
    }

    private function createMember(): string
    {
        $memberId = Uuid::randomHex();

        /** @var EntityRepository<MemberCollection> $memberRepository */
        $memberRepository = static::getContainer()->get('member.repository');
        $memberRepository->create([[
            'id' => $memberId,
            'channelId' => TestDefaults::CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ]], Context::createDefaultContext());

        static::assertTrue($this->memberExists($memberId));

        return $memberId;
    }

    private function memberExists(string $memberId): bool
    {
        return (bool) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT 1 FROM `member` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($memberId)],
        );
    }
}
