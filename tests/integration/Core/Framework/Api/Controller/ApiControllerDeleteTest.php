<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerDeleteTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the sales channel with id "%s".';

    public function testDelete(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => $id,
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/blog', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotNull($response->headers->get('Location'));
        static::assertSame('http://localhost/api/blog/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'blog', $id);

        $this->getBrowser()->jsonRequest('DELETE', '/api/blog/' . $id);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $this->assertEntityNotExists($this->getBrowser(), 'blog', $id);
    }

    public function testDeleteWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tax',
            'taxRate' => 15,
        ];

        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['tax:read', 'tax:create']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/tax', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $browser->jsonRequest('DELETE', '/api/tax/' . $id, ['name' => 'foo']);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());

        $this->assertEntityExists($browser, 'tax', $id);
    }

    public function testDeleteOneToMany(): void
    {
        $id = Uuid::randomHex();
        $stateId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                ['id' => $stateId, 'shortCode' => 'test', 'name' => 'test'],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/country', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotNull($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'country', $id);
        $this->assertEntityExists($this->getBrowser(), 'country-state', $stateId);

        $this->getBrowser()->jsonRequest('DELETE', '/api/country/' . $id . '/states/' . $stateId, $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $this->assertEntityExists($this->getBrowser(), 'country', $id);
        $this->assertEntityNotExists($this->getBrowser(), 'country-state', $stateId);
    }

    public function testDeleteOneToManyWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $stateId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                ['id' => $stateId, 'shortCode' => 'test', 'name' => 'test'],
            ],
        ];

        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['country_state:create', 'country_state:read', 'country:create', 'country:read']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/country', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotNull($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'country', $id);
        $this->assertEntityExists($browser, 'country-state', $stateId);

        $browser->jsonRequest('DELETE', '/api/country/' . $id . '/states/' . $stateId, $data);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $this->assertEntityExists($browser, 'country', $id);
        $this->assertEntityExists($browser, 'country-state', $stateId);
    }

    public function testDeleteManyToOne(): void
    {
        $dropStatement = <<<EOF
DROP TABLE IF EXISTS `named`;
DROP TABLE IF EXISTS `named_optional_group`;
EOF;

        $namedOptionalGroupStatement = <<<EOF
CREATE TABLE `named_optional_group` (
    `id` binary(16) NOT NULL,
    `name` varchar(255) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY `id` (`id`)
);
EOF;

        $namedStatement = <<<EOF
CREATE TABLE `named` (
    `id` binary(16) NOT NULL,
    `name` varchar(255) NOT NULL,
    `optional_group_id` varbinary(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY `id` (`id`),
    CONSTRAINT `fk` FOREIGN KEY (`optional_group_id`) REFERENCES `named_optional_group` (`id`) ON DELETE SET NULL
);
EOF;
        $connection = static::getContainer()->get(Connection::class);

        // Roll back the transaction that the trait started
        $connection->rollBack();

        $connection->executeStatement($dropStatement);
        $connection->executeStatement($namedOptionalGroupStatement);
        $connection->executeStatement($namedStatement);

        $id = Uuid::randomHex();
        $groupId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'Test blog',
            'optionalGroup' => [
                'id' => $groupId,
                'name' => 'Gramm',
            ],
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/named', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotNull($response->headers->get('Location'));
        static::assertSame('http://localhost/api/named/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'named', $id);
        $this->assertEntityExists($this->getBrowser(), 'named-optional-group', $groupId);

        $this->getBrowser()->jsonRequest('DELETE', '/api/named/' . $id . '/optional-group/' . $groupId);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $this->assertEntityExists($this->getBrowser(), 'named', $id);
        $this->assertEntityNotExists($this->getBrowser(), 'named-optional-group', $groupId);

        $connection->executeStatement($dropStatement);

        // Start a new transaction for the trait
        $connection->beginTransaction();
    }

    public function testDeleteManyToMany(): void
    {
        $id = Uuid::randomHex();
        $category = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['id' => $category, 'name' => 'Test'],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/blog', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotNull($response->headers->get('Location'));
        static::assertSame('http://localhost/api/blog/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($this->getBrowser(), 'blog', $id);
        $this->assertEntityExists($this->getBrowser(), 'category', $category);

        $this->getBrowser()->jsonRequest('DELETE', '/api/blog/' . $id . '/categories/' . $category);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $a = static::getContainer()
            ->get(Connection::class)
            ->executeQuery(
                'SELECT * FROM blog_category WHERE blog_id = :pid AND category_id = :cid',
                ['pid' => Uuid::fromHexToBytes($id), 'cid' => Uuid::fromHexToBytes($category)]
            )->fetchAllAssociative();
        static::assertCount(0, $a);

        $this->assertEntityExists($this->getBrowser(), 'blog', $id);
        $this->assertEntityExists($this->getBrowser(), 'category', $category);
    }

    public function testDeleteManyToManyWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $category = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'stock' => 12,
            'blogNumber' => '1',
            'manufacturer' => ['name' => 'test'],
            'categories' => [
                ['id' => $category, 'name' => 'Test'],
            ],
        ];

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/blog', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotNull($response->headers->get('Location'));
        static::assertSame('http://localhost/api/blog/' . $id, $response->headers->get('Location'));

        $this->assertEntityExists($browser, 'blog', $id);
        $this->assertEntityExists($browser, 'category', $category);

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['blog:read', 'category:read']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('DELETE', '/api/blog/' . $id . '/categories/' . $category);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $a = static::getContainer()->get(Connection::class)->executeQuery('SELECT * FROM blog_category WHERE blog_id = :pid AND category_id = :cid', ['pid' => Uuid::fromHexToBytes($id), 'cid' => Uuid::fromHexToBytes($category)])->fetchAllAssociative();
        static::assertNotCount(0, $a);

        $this->assertEntityExists($browser, 'blog', $id);
        $this->assertEntityExists($browser, 'category', $category);
    }

    public function testPreventDeletionOfDefaultChannelLanguageFromLanguageList(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannel($channelId);

        $browser = $this->getBrowser();
        $browser->jsonRequest('DELETE', '/api/sales-channel/' . $channelId . '/languages/' . Defaults::LANGUAGE_SYSTEM);

        $response = $browser->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $error = $content['errors'][0];

        static::assertSame(\sprintf(self::DELETE_VALIDATION_MESSAGE, $channelId), $error['detail']);
    }

    private function createChannel(string $id): void
    {
        $data = $this->getChannelData($id);

        static::getContainer()->get('channel.repository')->create([$data], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function getChannelData(string $channelId, string $languageId = Defaults::LANGUAGE_SYSTEM): array
    {
        return [
            'id' => $channelId,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => $languageId]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];
    }
}
