<?php declare(strict_types=1);

namespace Contena\Core\Installer\Configuration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Installer\InstallerException;
use Contena\Core\Maintenance\System\Service\ShopConfigurator;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Installer\Configuration\ShopConfigurationServiceTest
 *
 * @phpstan-type Shop array{
 *     name: string,
 *     locale: string,
 *     currency: string,
 *     additionalCurrencies: null|list<string>,
 *     country: string,
 *     email: string,
 *     host: string,
 *     basePath: string,
 *     schema: string,
 *     blueGreenDeployment: bool
 * }
 */
class ShopConfigurationService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * @param Shop $shop
     */
    public function updateShop(array $shop, Connection $connection): void
    {
        $connection->transactional(fn (Connection $connection) => $this->performUpdate($shop, $connection));
    }

    /**
     * @param Shop $shop
     */
    private function performUpdate(array $shop, Connection $connection): void
    {
        $locale = $shop['locale'] ?? '';
        if (!\is_string($locale) || $locale === '') {
            throw InstallerException::shopConfigurationRequiredValueMissing('locale');
        }

        $host = $shop['host'] ?? '';
        if (!\is_string($host) || $host === '') {
            throw InstallerException::shopConfigurationRequiredValueMissing('host');
        }

        $shopConfigurator = new ShopConfigurator($connection, $this->eventDispatcher, $this->clock);
        $shopConfigurator->updateBasicInformation($shop['name'], $shop['email']);
        $shopConfigurator->setDefaultLanguage($locale);
        $shopConfigurator->setDefaultCurrency($shop['currency']);

        $this->deleteAllChannelCurrencies($connection);

        $newChannelId = Uuid::randomBytes();
        $this->createChannel($newChannelId, $shop, $connection);
        $this->createChannelDomain($newChannelId, $shop, $connection);
    }

    /**
     * @param Shop $shop
     */
    private function createChannel(string $newId, array $shop, Connection $connection): void
    {
        $typeId = Defaults::CHANNEL_TYPE_FRONTEND;

        $paymentMethod = $this->getFirstActivePaymentMethodId($connection);
        $shippingMethod = $this->getFirstActiveShippingMethodId($connection);

        $languageId = $this->getLanguageId($shop['locale'], $connection);

        $currencyId = $this->getCurrencyId($shop['currency'], $connection);

        $countryId = $this->getCountryId($shop['country'], $connection);

        $connection->executeStatement(
            'INSERT INTO channel (
                id,
                type_id, access_key, navigation_category_id, navigation_category_version_id,
                language_id, currency_id, payment_method_id,
                shipping_method_id, country_id, user_group_id, created_at
            ) VALUES (
                ?,
                UNHEX(?), ?, ?, UNHEX(?),
                ?, ?, ?,
                ?, ?, ?, ?
            )',
            [
                $newId,
                $typeId,
                AccessKeyHelper::generateAccessKey('sales-channel'),
                $this->getRootCategoryId($connection), Defaults::LIVE_VERSION,
                $languageId,
                $currencyId,
                $paymentMethod,
                $shippingMethod,
                $countryId,
                $this->getUserGroupId($connection),
                $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->executeStatement(
            'INSERT INTO channel_translation (channel_id, language_id, `name`, created_at)
             VALUES (?, UNHEX(?), ?, ?)',
            [
                $newId, Defaults::LANGUAGE_SYSTEM, $shop['name'], $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->executeStatement(
            'INSERT INTO channel_language (channel_id, language_id)
             VALUES (?, UNHEX(?))',
            [$newId, Defaults::LANGUAGE_SYSTEM]
        );

        $connection->executeStatement(
            'INSERT INTO channel_currency (channel_id, currency_id)
             VALUES (?, ?)',
            [$newId, $currencyId]
        );

        $connection->executeStatement(
            'INSERT INTO channel_payment_method (channel_id, payment_method_id)
             VALUES (?, ?)',
            [$newId, $paymentMethod]
        );

        $connection->executeStatement(
            'INSERT INTO channel_shipping_method (channel_id, shipping_method_id)
             VALUES (?, ?)',
            [$newId, $shippingMethod]
        );

        $connection->executeStatement(
            'INSERT INTO channel_country (channel_id, country_id)
             VALUES (?, ?)',
            [$newId, $countryId]
        );

        $this->addAdditionalCurrenciesToChannel($shop, $newId, $connection);
        $this->removeUnwantedCurrencies($shop, $connection);
    }

    private function getRootCategoryId(Connection $connection): string
    {
        return $connection
            ->fetchOne('SELECT id FROM category WHERE `active` = 1 AND parent_id IS NULL');
    }

    /**
     * @param Shop $shop
     */
    private function createChannelDomain(string $newId, array $shop, Connection $connection): void
    {
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $snippetSetId = $this->getSnippetSet($shop['locale'], $connection)
            ?? $this->getSnippetSet('en-GB', $connection);
        $currencyId = $this->getCurrencyId($shop['currency'], $connection);

        $insertSql = <<<'SQL'
INSERT INTO channel_domain (id, channel_id, language_id, url, currency_id, snippet_set_id, custom_fields, created_at, updated_at)
VALUES (:id, :channelId, :languageId, :url, :currencyId, :snippetSetId, NULL, :createdAt, null)
SQL;

        $insertChannel = $connection->prepare($insertSql);

        StatementHelper::executeStatement($insertChannel, [
            'id' => Uuid::randomBytes(),
            'channelId' => $newId,
            'languageId' => $languageId,
            'url' => 'http://' . $shop['host'] . $shop['basePath'],
            'currencyId' => $currencyId,
            'snippetSetId' => $snippetSetId,
            'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        StatementHelper::executeStatement($insertChannel, [
            'id' => Uuid::randomBytes(),
            'channelId' => $newId,
            'languageId' => $languageId,
            'url' => 'https://' . $shop['host'] . $shop['basePath'],
            'currencyId' => $currencyId,
            'snippetSetId' => $snippetSetId,
            'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getFirstActiveShippingMethodId(Connection $connection): string
    {
        return $connection->fetchOne('SELECT id FROM shipping_method WHERE `active` = 1 ORDER BY position');
    }

    private function getFirstActivePaymentMethodId(Connection $connection): string
    {
        return $connection->fetchOne('SELECT id FROM payment_method WHERE `active` = 1 ORDER BY position');
    }

    private function getLanguageId(string $iso, Connection $connection): ?string
    {
        return $connection->fetchOne(
            'SELECT language.id
             FROM `language`
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE LOWER(locale.code) = LOWER(?)',
            [$iso]
        ) ?: null;
    }

    private function getCurrencyId(string $currencyName, Connection $connection): string
    {
        $fetchCurrencyId = $connection->fetchOne(
            'SELECT id FROM currency WHERE LOWER(iso_code) = LOWER(?)',
            [$currencyName]
        );

        if (!\is_string($fetchCurrencyId) || $fetchCurrencyId === '') {
            throw InstallerException::currencyNotFound($currencyName);
        }

        return $fetchCurrencyId;
    }

    private function getSnippetSet(string $iso, Connection $connection): ?string
    {
        return $connection->fetchOne(
            'SELECT id FROM snippet_set WHERE LOWER(iso) = LOWER(:iso)',
            ['iso' => $iso]
        ) ?: null;
    }

    private function getCountryId(string $iso, Connection $connection): string
    {
        $fetchCountryId = $connection->fetchOne('SELECT id FROM country WHERE LOWER(iso3) = LOWER(?)', [$iso]);

        if (!\is_string($fetchCountryId) || $fetchCountryId === '') {
            throw InstallerException::countryNotFound($iso);
        }

        return $fetchCountryId;
    }

    /**
     * get the id of the sales channel via the sales channel type id
     */
    private function getIdOfChannelViaTypeId(string $typeId, Connection $connection): string
    {
        return $connection->fetchOne('SELECT id FROM channel WHERE type_id = UNHEX(?)', [$typeId]);
    }

    /**
     * @param Shop $shop
     */
    private function addAdditionalCurrenciesToChannel(array $shop, string $channelId, Connection $connection): void
    {
        $idOfHeadlessChannel = $this->getIdOfChannelViaTypeId(Defaults::CHANNEL_TYPE_API, $connection);

        // set the default currency of the headless sales channel
        $defaultCurrencyId = $this->getCurrencyId($shop['currency'], $connection);
        $connection->executeStatement(
            'UPDATE channel SET currency_id = ? WHERE id = ?',
            [$defaultCurrencyId, $idOfHeadlessChannel]
        );

        // remove all currencies from the headless sales channel, except the default currency
        $connection->executeStatement(
            'DELETE FROM channel_currency WHERE channel_id = ? AND currency_id != UNHEX(?)',
            [$idOfHeadlessChannel, $defaultCurrencyId]
        );

        if ($shop['additionalCurrencies'] === null) {
            return;
        }

        $channelsToBeEdited = [];
        $channelsToBeEdited[] = $idOfHeadlessChannel;
        $channelsToBeEdited[] = $channelId;

        // set the currencies of the headless sales channel to the ones from the default sales channel
        foreach ($channelsToBeEdited as $currentChannelId) {
            foreach ($shop['additionalCurrencies'] as $additionalCurrency) {
                $currencyId = $this->getCurrencyId($additionalCurrency, $connection);

                // add additional currencies
                $connection->executeStatement(
                    'INSERT INTO channel_currency (channel_id, currency_id) VALUES (?, ?)',
                    [$currentChannelId, $currencyId]
                );
            }
        }
    }

    /**
     * @param Shop $shop
     */
    private function removeUnwantedCurrencies(array $shop, Connection $connection): void
    {
        // change default currency for dummy sales channel domain to the default currency to avoid foreign key constraints
        $connection->executeStatement(
            'UPDATE channel_domain SET currency_id = :currencyId',
            ['currencyId' => $this->getCurrencyId($shop['currency'], $connection)]
        );

        // remove all currencies except the default currency when no additional currency is selected
        if ($shop['additionalCurrencies'] === null) {
            $connection->executeStatement(
                'DELETE FROM currency WHERE iso_code != :currency',
                ['currency' => $shop['currency']]
            );

            return;
        }

        $selectedCurrencies = $shop['additionalCurrencies'];
        $selectedCurrencies[] = $shop['currency'];

        $connection->executeStatement(
            'DELETE FROM currency WHERE iso_code NOT IN (:currencies)',
            ['currencies' => array_unique($selectedCurrencies)],
            ['currencies' => ArrayParameterType::STRING]
        );
    }

    private function deleteAllChannelCurrencies(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM channel_currency');
    }

    private function getUserGroupId(Connection $connection): string
    {
        return $connection->fetchOne('SELECT user_group_id FROM channel');
    }
}
