<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class ChangeEmailRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    private string $memberId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);
        $this->memberRepository = static::getContainer()->get('member.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->memberId = $this->createMember('contenaAdmin', $email, $this->ids->get('channel'));

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contenaAdmin',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-email',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::MEMBER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChangeInvalidPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-email',
                [
                    'password' => 'invalid-password',
                    'email' => 'changed@example.com',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::MEMBER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChange(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-email',
                [
                    'password' => 'contenaAdmin',
                    'email' => 'changed@example.com',
                    'emailConfirmation' => 'changed@example.com',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/channel-api/account/member',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('changed@example.com', $response['email']);
    }

    public function testChangeAndDeleteOldRecoveryEntities(): void
    {
        $recoveryData = [
            'memberId' => $this->memberId,
            'hash' => Random::getAlphanumericString(32),
        ];

        /** @var EntityRepository<MemberRecoveryCollection> $memberRecoveryRepository */
        $memberRecoveryRepository = $this->getContainer()->get('member_recovery.repository');
        $memberRecoveryRepository->create([$recoveryData], Context::createDefaultContext());

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-email',
                [
                    'password' => 'contenaAdmin',
                    'email' => 'changed@example.com',
                    'emailConfirmation' => 'changed@example.com',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/channel-api/account/member',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $criteria = new Criteria()->addFilter(new EqualsFilter('memberId', $this->memberId));
        $ids = $memberRecoveryRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertSame('changed@example.com', $response['email']);
        static::assertCount(0, $ids);
    }

    public function testChangeSuccessWithSameEmailOnDiffChannel(): void
    {
        $newEmail = 'changed@example.com';

        $secondBrowser = $this->createCustomChannelBrowser([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/' . Uuid::randomHex(),
                ],
            ],
        ]);
        $secondChannelId = $secondBrowser->getServerParameter('test-channel-id');
        static::assertIsString($secondChannelId);
        $this->createMember('contenaAdmin', $newEmail, $secondChannelId);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-email',
                [
                    'password' => 'contenaAdmin',
                    'email' => $newEmail,
                    'emailConfirmation' => $newEmail,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/channel-api/account/member',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($newEmail, $response['email']);
    }

    public function testChangeFailWithSameEmailOnSameChannel(): void
    {
        $newEmail = 'changed@example.com';

        $this->createMember('contenaAdmin', $newEmail, $this->ids->get('channel'));

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-email',
                [
                    'password' => 'contenaAdmin',
                    'email' => $newEmail,
                    'emailConfirmation' => $newEmail,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertNotEmpty($response['errors']);
        static::assertSame('VIOLATION::MEMBER_EMAIL_NOT_UNIQUE', $response['errors'][0]['code']);

        $this->browser
            ->request(
                'GET',
                '/channel-api/account/member',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotSame($newEmail, $response['email']);
    }

    private function createMember(string $password, string $email, string $channelId): string
    {
        $memberId = Uuid::randomHex();

        $member = [
            'id' => $memberId,
            'channelId' => $channelId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => $password,
            'name' => 'Max Member',
            'memberNumber' => $memberId,
            'active' => true,
        ];

        $this->memberRepository->create([$member], Context::createDefaultContext());

        return $memberId;
    }
}
