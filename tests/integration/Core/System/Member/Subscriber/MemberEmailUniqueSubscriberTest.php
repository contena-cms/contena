<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\Validation\Constraint\MemberEmailUnique;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class MemberEmailUniqueSubscriberTest extends TestCase
{
    use AdminApiTestBehaviour;
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->memberRepository = static::getContainer()->get('member.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testRepositoryCreateRejectsDuplicateEmailInSameChannel(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);

        $this->expectMemberEmailViolation(fn () => $this->createMember($email));
    }

    public function testAdminApiCreateRejectsDuplicateEmailInSameChannel(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);

        $this->getBrowser()->jsonRequest('POST', '/api/member', $this->createMemberPayload($email));

        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(MemberEmailUnique::MEMBER_EMAIL_NOT_UNIQUE, $content['errors'][0]['code']);
    }

    public function testSyncApiCreateRejectsDuplicateEmailWithSyncOperationPointer(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/sync', [[
            'key' => 'write-member',
            'action' => 'upsert',
            'entity' => 'member',
            'payload' => [$this->createMemberPayload($email)],
        ]]);

        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('/write-member/0/email', $content['errors'][0]['source']['pointer']);
    }

    public function testRepositoryCreateAllowsDuplicateEmailInDifferentChannels(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $channel = $this->createChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/' . Uuid::randomHex(),
                ],
            ],
        ]);

        $this->createMember($email);
        $this->createMember($email, ['channelId' => $channel['id']]);

        static::addToAssertionCount(1);
    }

    public function testRepositoryUpdateRejectsDuplicateEmail(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);
        $memberId = $this->createMember(Uuid::randomHex() . '@example.com');

        $this->expectMemberEmailViolation(fn () => $this->memberRepository->update([[
            'id' => $memberId,
            'email' => $email,
        ]], $this->context));
    }

    public function testRepositoryCreateRejectsDuplicateEmailInSameWriteBatch(): void
    {
        $email = Uuid::randomHex() . '@example.com';

        $this->expectMemberEmailViolation(fn () => $this->memberRepository->create([
            $this->createMemberPayload($email),
            $this->createMemberPayload($email),
        ], $this->context));
    }

    public function testRepositoryUpdateAllowsEmailSwapInSameWriteBatch(): void
    {
        $firstEmail = Uuid::randomHex() . '@example.com';
        $secondEmail = Uuid::randomHex() . '@example.com';
        $firstMemberId = $this->createMember($firstEmail);
        $secondMemberId = $this->createMember($secondEmail);

        $this->memberRepository->update([
            [
                'id' => $firstMemberId,
                'email' => $secondEmail,
            ],
            [
                'id' => $secondMemberId,
                'email' => $firstEmail,
            ],
        ], $this->context);

        static::addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createMember(string $email, array $overrides = []): string
    {
        $member = $this->createMemberPayload($email, $overrides);

        $this->memberRepository->create([$member], $this->context);

        return $member['id'];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createMemberPayload(string $email, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => Uuid::randomHex(),
            'channelId' => TestDefaults::CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ], $overrides);
    }

    private function expectMemberEmailViolation(\Closure $callback): void
    {
        try {
            $callback();
            static::fail('Expected a member email uniqueness violation.');
        } catch (WriteException $exception) {
            $writeException = $exception->getExceptions()[0] ?? null;

            static::assertInstanceOf(WriteConstraintViolationException::class, $writeException);
            static::assertSame(MemberEmailUnique::MEMBER_EMAIL_NOT_UNIQUE, $writeException->getViolations()->get(0)->getCode());
        }
    }
}
