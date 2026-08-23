<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
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
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Member\Channel\LoginRoute;
use Contena\Core\System\Member\Exception\BadCredentialsException;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberException;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class LoginRouteTest extends TestCase
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

    public function testInvalidCredentials(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => 'foo',
                    'password' => 'foo12345',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('Unauthorized', $response['errors'][0]['title']);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(MemberException::MEMBER_AUTH_BAD_CREDENTIALS, $response['errors'][0]['code']);
    }

    public function testValidLogin(): void
    {
        $email = Uuid::randomHex() . '@exämple.com';
        $this->createMember($email);

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

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testItDoesNotUpdateMemberLanguageIdOnValidLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $chineseLanguageId = $this->getChineseLanguageId();
        $memberId = $this->createMember($email, true, $chineseLanguageId);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contenaAdmin',
                ],
            );

        static::assertSame(
            $chineseLanguageId,
            $this->memberRepository->search(
                new Criteria([$memberId]),
                Context::createDefaultContext()
            )->getEntities()->first()?->getLanguageId()
        );
    }

    public function testValidLoginWithOneInactive(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $memberId = $this->createMember($email);
        $this->cloneMemberWithDuplicateEmail($memberId, Uuid::randomHex(), false);

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

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testLoginWithInvalidChannelId(): void
    {
        static::expectException(BadCredentialsException::class);

        $email = Uuid::randomHex() . '@example.com';
        $this->createMember($email);

        $otherChannelContext = $this->createChannelContext([
            'id' => Uuid::randomHex(),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/' . Uuid::randomHex(),
                ],
            ],
        ]);

        $loginRoute = static::getContainer()->get(LoginRoute::class);

        $requestDataBag = new RequestDataBag(['email' => $email, 'password' => 'contenaAdmin']);

        $loginRoute->login($requestDataBag, $otherChannelContext);
    }

    public function testLoginSuccessRestoresMemberContext(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $memberId = $this->createMember($email);
        $contextToken = Uuid::randomHex();

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            $this->ids->get('channel'),
            [ChannelContextService::MEMBER_ID => $memberId],
        );

        $loginRoute = static::getContainer()->get(LoginRoute::class);

        $request = new RequestDataBag(['email' => $email, 'password' => 'contenaAdmin']);

        $response = $loginRoute->login($request, $channelContext);

        // Token is replaced as there is no member token in the database
        static::assertNotSame($contextToken, $oldToken = $response->getToken());

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            '123456789',
            $this->ids->get('channel'),
            [ChannelContextService::MEMBER_ID => $memberId],
        );

        $response = $loginRoute->login($request, $channelContext);

        // Previous token is restored
        static::assertSame($oldToken, $response->getToken());
        static::assertInstanceOf(ContextTokenResponse::class, $response);
    }

    private function createMember(?string $email = null, bool $active = true, ?string $languageId = null): string
    {
        $memberId = Uuid::randomHex();

        $member = [
            'id' => $memberId,
            'channelId' => $this->ids->get('channel'),
            'languageId' => $languageId ?? Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Member',
            'memberNumber' => $memberId,
            'active' => $active,
        ];

        $this->memberRepository->create([$member], Context::createDefaultContext());

        return $memberId;
    }

    private function cloneMemberWithDuplicateEmail(string $sourceMemberId, string $memberId, bool $active): void
    {
        $connection = static::getContainer()->get(Connection::class);
        /** @var list<array{Field: string, Extra: string}> $columns */
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM `member`');

        $insertColumns = [];
        $selectExpressions = [];

        foreach ($columns as $column) {
            if (str_contains($column['Extra'], 'auto_increment')) {
                continue;
            }

            $field = $column['Field'];
            $insertColumns[] = '`' . $field . '`';
            $selectExpressions[] = match ($field) {
                'id' => ':memberId',
                'active' => ':active',
                'member_number' => ':memberNumber',
                'created_at' => ':createdAt',
                'updated_at' => 'NULL',
                default => '`' . $field . '`',
            };
        }

        // This test covers login behavior with legacy duplicate member rows that normal writes now reject.
        $connection->executeStatement(
            'INSERT INTO `member` (' . implode(', ', $insertColumns) . ')
             SELECT ' . implode(', ', $selectExpressions) . '
             FROM `member`
             WHERE `id` = :sourceMemberId',
            [
                'active' => (int) $active,
                'createdAt' => '2022-10-22 10:00:00',
                'memberId' => Uuid::fromHexToBytes($memberId),
                'memberNumber' => $memberId,
                'sourceMemberId' => Uuid::fromHexToBytes($sourceMemberId),
            ],
        );
    }

    private function getChineseLanguageId(): string
    {
        /** @var EntityRepository<LanguageCollection> $repository */
        $repository = static::getContainer()->get('language.repository');

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('translationCode.code', 'zh-CN'));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($id);

        return $id;
    }
}
