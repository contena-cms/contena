<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class ResetPasswordRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);
        $this->memberRepository = static::getContainer()->get('member.repository');
    }

    public function testWithInvalidHash(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password-confirm',
                [
                    'hash' => 'invalid-hash',
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('SYSTEM__MEMBER_RECOVERY_HASH_EXPIRED', $response['errors'][0]['code']);
    }

    public function testSuccessReset(): void
    {
        $memberId = $this->createMember('contenaAdmin', 'member@example.com');

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password',
                [
                    'email' => 'member@example.com',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('memberId', $memberId));

        /** @var EntityRepository<MemberRecoveryCollection> $repo */
        $repo = static::getContainer()->get('member_recovery.repository');

        $recovery = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(MemberRecoveryEntity::class, $recovery);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password-confirm',
                [
                    'hash' => $recovery->getHash(),
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => 'member@example.com',
                    'password' => 'password123456',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testSuccessResetConfirmsUnconfirmedDoubleOptInMember(): void
    {
        $memberId = $this->createMember('contenaAdmin', 'double-opt-in@example.com', true);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password',
                [
                    'email' => 'double-opt-in@example.com',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('memberId', $memberId));

        /** @var EntityRepository<MemberRecoveryCollection> $repo */
        $repo = static::getContainer()->get('member_recovery.repository');

        $recovery = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(MemberRecoveryEntity::class, $recovery);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password-confirm',
                [
                    'hash' => $recovery->getHash(),
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $member = $this->fetchMember($memberId);
        static::assertNotNull($member->getDoubleOptInConfirmDate());

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => 'double-opt-in@example.com',
                    'password' => 'password123456',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    private function createMember(string $password, ?string $email = null, bool $doubleOptInRegistration = false): string
    {
        $memberId = Uuid::randomHex();
        $member = [
            'id' => $memberId,
            'channelId' => $this->ids->get('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => $password,
            'name' => 'Max Member',
            'memberNumber' => $memberId,
            'active' => true,
            'doubleOptInRegistration' => $doubleOptInRegistration,
        ];

        $this->memberRepository->create([
            $member,
        ], Context::createDefaultContext());

        return $memberId;
    }

    private function fetchMember(string $memberId): MemberEntity
    {
        $criteria = new Criteria([$memberId]);

        $member = $this->memberRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(MemberEntity::class, $member);

        return $member;
    }
}
