<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class AdministrationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $newLanguageId = $this->insertOtherLanguage();
        $this->createSearchConfigFieldForNewLanguage($newLanguageId);

        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testSnippetRoute(): void
    {
        $this->getBrowser()->request('GET', '/api/_admin/snippets?locale=de-DE');
        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('de-DE', $response);
        static::assertArrayNotHasKey('en-GB', $response, 'en-GB is not provided because de-DE already provides all snippets, so no fallback snippet is needed');
    }

    public function testSnippetRouteDoesNotDeliverSnippetsTwice(): void
    {
        $this->getBrowser()->request('GET', '/api/_admin/snippets?locale=de-DE');
        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertIsArray($response['de-DE']);
        static::assertNotSame([], $response['de-DE']);

        $duplicates = array_intersect_key(
            $this->flattenSnippets($response['de-DE']),
            $this->flattenSnippets($response['en-GB'] ?? [])
        );

        static::assertSame([], array_keys($duplicates));
    }

    public function testSnippetRouteOnlyDeliversUnauthenticatedNamespacesWithoutAuthentication(): void
    {
        $browser = $this->createClient(authorized: false);
        $browser->request('GET', '/api/_admin/snippets?locale=de-DE');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);

        foreach ($response as $localeSnippets) {
            static::assertSame([], array_values(array_diff(array_keys($localeSnippets), ['sw-login', 'global'])));
        }

        static::assertArrayHasKey('sw-login', ($response['de-DE'] ?? []) + ($response['en-GB'] ?? []));
    }

    public function testResetExcludedSearchTermIncorrectLanguageId(): void
    {
        $this->getBrowser()->setServerParameter('HTTP_sw-language-id', Uuid::randomHex());
        $this->getBrowser()->request('POST', '/api/_admin/reset-excluded-search-term');

        $response = $this->getBrowser()->getResponse();

        static::assertSame(412, $response->getStatusCode());
    }

    public function testValidateEmailSuccess(): void
    {
        $browser = $this->createClient();
        $this->createUser(['email' => 'foo@bar.de']);

        $browser->request(
            'POST',
            '/api/_admin/check-user-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => 'foo1@bar.de',
                'boundChannelId' => null,
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('isValid', $response);
    }

    public function testValidateEmailFail(): void
    {
        $email = 'foo@bar.de';
        $browser = $this->createClient();
        $this->createUser(['email' => 'foo@bar.de']);

        $browser->request(
            'POST',
            '/api/_admin/check-user-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => $email,
                'boundChannelId' => null,
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(400, $browser->getResponse()->getStatusCode());
        static::assertSame('The email address ' . $email . ' is already in use', $response['errors'][0]['detail']);
    }

    public function testValidateEmailSuccessWithSameUserDifferentChannel(): void
    {
        $this->setUserBoundToChannels(true);
        $newChannel = $this->createChannel();

        $browser = $this->createClient();
        $email = 'foo@bar.de';
        $this->createUser(['email' => 'foo@bar.de', 'boundChannelId' => TestDefaults::CHANNEL]);

        $browser->request(
            'POST',
            '/api/_admin/check-user-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => $email,
                'boundChannelId' => $newChannel['id'],
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('isValid', $response);
    }

    public function testValidateEmailFailWithSameUserSameChannel(): void
    {
        $this->setUserBoundToChannels(true);
        $email = 'foo@bar.de';
        $browser = $this->createClient();
        $this->createUser(['email' => 'foo@bar.de', 'boundChannelId' => TestDefaults::CHANNEL]);

        $browser->request(
            'POST',
            '/api/_admin/check-user-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => $email,
                'boundChannelId' => TestDefaults::CHANNEL,
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(400, $browser->getResponse()->getStatusCode());
        static::assertSame('The email address ' . $email . ' is already in use in the Sales Channel Headless', $response['errors'][0]['detail']);
    }

    public function testValidateEmailFailWithSameUserIsAlreadyExistsInAllChannel(): void
    {
        $this->setUserBoundToChannels(true);
        $email = 'foo@bar.de';
        $browser = $this->createClient();
        $this->createUser(['email' => $email]);

        $browser->request(
            'POST',
            '/api/_admin/check-user-email-valid',
            [
                'id' => Uuid::randomHex(),
                'email' => $email,
                'boundChannelId' => TestDefaults::CHANNEL,
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(400, $browser->getResponse()->getStatusCode());
        static::assertSame('The email address ' . $email . ' is already in use', $response['errors'][0]['detail']);
    }

    public function testPreviewSanitizedHtml(): void
    {
        $html = '<img alt="" src="#" /><script type="text/javascript"></script><div>test</div>';
        $browser = $this->createClient();

        $browser->request(
            'POST',
            '/api/_admin/sanitize-html',
            [
                'html' => $html,
                'field' => 'blog_translation.description',
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();

        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertSame('<img alt="" src="#" /><div>test</div>', $response['preview']);

        $browser->request(
            'POST',
            '/api/_admin/sanitize-html',
            [
                'html' => $html,
                'field' => 'mail_template_translation.contentHtml',
            ]
        );

        $content = $this->getBrowser()->getResponse()->getContent();

        static::assertNotFalse($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertSame($html, $response['preview']);
    }

    /**
     * @param array<string, string|bool|int|float|null> $overrideData
     */
    private function createUser(array $overrideData): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = array_merge([
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'random@mail.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'guest' => false,
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ], $overrideData);

        $this->userRepository->create([$user], Context::createDefaultContext());

        return $userId;
    }

    private function insertOtherLanguage(): string
    {
        $langId = $this->connection->executeQuery(
            'SELECT id FROM `language` WHERE `name` = :langName',
            [
                'langName' => 'Vietnamese',
            ]
        )->fetchFirstColumn();

        $localeId = $this->connection->executeQuery(
            'SELECT id FROM `locale` WHERE `code` = :code',
            [
                'code' => 'vi-VN',
            ]
        )->fetchFirstColumn();

        if ($langId) {
            return $langId[0];
        }

        $newLanguageId = Uuid::randomBytes();
        $this->connection->executeStatement(
            '
            INSERT INTO `language` (`id`, `name`, `locale_id`, `translation_code_id`, `created_at`)
            VALUES (?, ?, ?, ?, ?)',
            [$newLanguageId, 'Vietnamese', $localeId[0], $localeId[0], '2021-04-01 04:41:12.045']
        );

        return $newLanguageId;
    }

    private function createSearchConfigFieldForNewLanguage(string $newLanguageId): void
    {
        $configId = $this->connection->executeQuery(
            'SELECT id FROM `blog_search_config` WHERE `language_id` = :languageId',
            [
                'languageId' => $newLanguageId,
            ]
        )->fetchFirstColumn();

        if (!$configId) {
            $newConfigId = Uuid::randomBytes();
            $this->connection->executeStatement(
                'INSERT INTO `blog_search_config` (`id`, `language_id`, `and_logic`, `min_search_length`, `created_at`)
                VALUES (?, ?, ?, ?, ?)',
                [$newConfigId, $newLanguageId, 0, 2, '2021-04-01 04:41:12.045']
            );
        }
    }

    private function setUserBoundToChannels(bool $value): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isUserBoundToChannel', $value);
    }

    /**
     * @param array<string, int|float|string|bool|null> $channelOverride
     *
     * @return array<string, array<int, array<string, string|null>>|bool|float|int|string|null>
     */
    private function createChannel(array $channelOverride = []): array
    {
        $channelRepository = static::getContainer()->get('channel.repository');
        $paymentMethod = $this->getAvailablePaymentMethod();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('domains.url', 'http://localhost'));
        $channelIds = $channelRepository->searchIds($criteria, Context::createDefaultContext());

        if (!isset($channelOverride['domains']) && $channelIds->firstId() !== null) {
            $channelRepository->delete([['id' => $channelIds->firstId()]], Context::createDefaultContext());
        }

        $channel = array_merge([
            'id' => $channelOverride['id'] ?? Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'name' => 'new sales channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $paymentMethod->getId(),
            'paymentMethods' => [['id' => $paymentMethod->getId()]],
            'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(null),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => $channelOverride['languages'] ?? [['id' => Defaults::LANGUAGE_SYSTEM]],
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost',
                ],
            ],
            'countries' => [['id' => $this->getValidCountryId(null)]],
        ], $channelOverride);

        $channelRepository->upsert([$channel], Context::createDefaultContext());

        return $channel;
    }

    /**
     * @param array<string, mixed> $snippets
     *
     * @return array<string, mixed>
     */
    private function flattenSnippets(array $snippets, string $prefix = ''): array
    {
        $flat = [];
        foreach ($snippets as $key => $value) {
            if (\is_array($value)) {
                $flat += $this->flattenSnippets($value, $prefix . $key . '.');

                continue;
            }

            $flat[$prefix . $key] = $value;
        }

        return $flat;
    }
}
