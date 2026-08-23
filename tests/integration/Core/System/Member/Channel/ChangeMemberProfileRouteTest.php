<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class ChangeMemberProfileRouteTest extends TestCase
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
        $this->memberId = $this->createMember('contenaAdmin', $email);

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
                '/channel-api/account/change-profile',
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);

        $sources = array_column(array_column($response['errors'], 'source'), 'pointer');
        static::assertContains('/name', $sources);
    }

    public function testChangeName(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-profile',
                [
                    'name' => 'Max Member',
                    'phoneNumber' => '123456789',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $this->browser->request('GET', '/channel-api/account/member');
        $member = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('Max Member', $member['name']);
        static::assertSame('123456789', $member['phoneNumber']);
    }

    public function testChangeProfileDataWithPhoneNumber(): void
    {
        $changeData = [
            'name' => 'Max Member',
            'phoneNumber' => '123456789',
        ];
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-profile',
                $changeData
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $member = $this->getMember();

        static::assertSame($changeData['name'], $member->getName());
        static::assertSame($changeData['phoneNumber'], $member->getPhoneNumber());
    }

    public function testChangeProfileDataWithoutPhoneNumber(): void
    {
        $changeData = [
            'name' => 'Private Member',
        ];
        $this->browser->request(
            'POST',
            '/channel-api/account/change-profile',
            $changeData
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $member = $this->getMember();

        static::assertSame($changeData['name'], $member->getName());
    }

    public function testChangeProfileWithCustomFields(): void
    {
        $context = Context::createDefaultContext();

        $this->memberRepository->update([
            [
                'id' => $this->memberId,
                'customFields' => [
                    'initialCustomField' => 'initialValueShouldStay',
                ],
            ],
        ], $context);

        $changeData = [
            'name' => 'Max Member',
            'customFields' => [
                'randomCustomField' => 'randomValue',
            ],
        ];

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-profile',
                $changeData
            );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);
        $response = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $customFields = $this->getMember()->getCustomFields();
        static::assertIsArray($customFields);
        static::assertSame('initialValueShouldStay', $customFields['initialCustomField']);
    }

    private function createMember(string $password, string $email): string
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
        ];

        $this->memberRepository->create([$member], Context::createDefaultContext());

        return $memberId;
    }

    private function getMember(): MemberEntity
    {
        $criteria = new Criteria([$this->memberId]);

        $member = $this->memberRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($member);

        return $member;
    }
}
