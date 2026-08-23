<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('channel-api')]
class ChangeLanguageRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->createChineseLanguage();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);
    }

    public function testNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-language',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CHANNEL_MEMBER_NOT_LOGGED_IN, $response['errors'][0]['code']);
    }

    public function testValidLanguage(): void
    {
        $languageId = $this->ids->get('language');

        static::getContainer()->get('channel.repository')->update(
            [
                [
                    'id' => $this->ids->get('channel'),
                    'languages' => [
                        [
                            'id' => $languageId,
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $id = $this->login($this->browser);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-language',
                [
                    'languageId' => $languageId,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('success', $response);

        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $member = $connection->fetchAllAssociative('SELECT * FROM member WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame($languageId, Uuid::fromBytesToHex($member[0]['language_id']));
    }

    public function testInvalidLanguage(): void
    {
        $languageId = $this->ids->get('language');

        $id = $this->login($this->browser);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/change-language',
                [
                    'languageId' => $languageId,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('The "language" entity with id "' . $languageId . '" does not exist.', $response['errors'][0]['detail']);

        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $member = $connection->fetchAllAssociative('SELECT * FROM member WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($member[0]['language_id']));
    }

    private function createChineseLanguage(): void
    {
        static::getContainer()->get('locale.repository')->create([
            [
                'id' => $this->ids->create('locale'),
                'code' => 'zh-CN-x-member-change-' . mb_substr($this->ids->get('locale'), 0, 8),
                'name' => 'Chinese member language test locale',
                'territory' => 'China',
            ],
        ], Context::createDefaultContext());

        static::getContainer()->get('language.repository')->create([
            [
                'id' => $this->ids->create('language'),
                'name' => 'Simplified Chinese member test',
                'localeId' => $this->ids->get('locale'),
                'translationCodeId' => $this->ids->get('locale'),
                'active' => true,
            ],
        ], Context::createDefaultContext());
    }
}
