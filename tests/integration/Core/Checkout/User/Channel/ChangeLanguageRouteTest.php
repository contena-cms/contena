<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class ChangeLanguageRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);
    }

    public function testNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-language',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
    }

    public function testValidLanguage(): void
    {
        $languageId = $this->getDeDeLanguageId();

        static::getContainer()->get('channel.repository')->update(
            [
                [
                    'id' => $this->ids->get('sales-channel'),
                    'languages' => [
                        [
                            'id' => $this->getDeDeLanguageId(),
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
                '/store-api/account/change-language',
                [
                    'languageId' => $languageId,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('success', $response);

        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $user = $connection->fetchAllAssociative('SELECT * FROM user WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame($languageId, Uuid::fromBytesToHex($user[0]['language_id']));
    }

    public function testInvalidLanguage(): void
    {
        $languageId = $this->getDeDeLanguageId();

        $id = $this->login($this->browser);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-language',
                [
                    'languageId' => $languageId,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('The "language" entity with id "' . $languageId . '" does not exist.', $response['errors'][0]['detail']);

        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $user = $connection->fetchAllAssociative('SELECT * FROM user WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($user[0]['language_id']));
    }
}
