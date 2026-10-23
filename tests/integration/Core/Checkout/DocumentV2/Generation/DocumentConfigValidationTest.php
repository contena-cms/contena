<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\DocumentV2\Generation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Contena\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Contena\Core\Checkout\DocumentV2\DocumentV2Exception;
use Contena\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Contena\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\Builder\Order\OrderBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
class DocumentConfigValidationTest extends TestCase
{
    use DocumentV2Trait;

    public function testInvalidConfigurationLeavesNumberAndOrderVersionsUnchanged(): void
    {
        $this->context = Context::createDefaultContext();
        $addressId = Uuid::randomHex();
        $userId = $this->createUser(
            ['defaultShippingAddressId' => $addressId],
            $this->buildDemoShippingAddress($addressId),
        );
        $ids = new IdsCollection();
        $ids->set('user', $userId);
        $order = (new OrderBuilder($ids, 'order'))
            ->add('languageId', Defaults::LANGUAGE_SYSTEM)
            ->addAddress('billing_address', [
                'id' => $ids->get('billing_address'),
                'country' => ['id' => $this->getValidCountryId()],
                'salutationId' => $this->getValidSalutationId(),
            ])
            ->orderUser('User', 'user')
            ->addTransaction('transaction')
            ->build();
        static::getContainer()->get('order.repository')->create([$order], $this->context);
        $this->seedDemoBaseConfig('invoice');
        static::getContainer()->get(SystemConfigService::class)->set(
            'core.basicInformation.companyCountryId',
            'invalid',
            TestDefaults::CHANNEL,
        );
        static::getContainer()->get(DocumentConfigLoader::class)->reset();

        $connection = static::getContainer()->get(Connection::class);
        $numberGenerator = static::getContainer()->get(AbstractNumberRangeValueGenerator::class);
        $numberBefore = $numberGenerator->getValue('document_invoice', $this->context, TestDefaults::CHANNEL, preview: true);
        $versionsBefore = $connection->fetchOne('SELECT COUNT(*) FROM `version`');

        $this->expectExceptionObject(DocumentV2Exception::configMissingRequiredFields(
            DocumentCompanyInfo::class,
            'invoice',
            'companyCountry',
        ));

        try {
            static::getContainer()->get(DocumentGenerator::class)->generate(
                new DocumentGenerationRequest($ids->get('order'), 'invoice', ['html']),
                $this->context,
            );
        } finally {
            static::assertSame($numberBefore, $numberGenerator->getValue('document_invoice', $this->context, TestDefaults::CHANNEL, preview: true));
            static::assertSame($versionsBefore, $connection->fetchOne('SELECT COUNT(*) FROM `version`'));
            static::assertSame(1, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM `order` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('order'))],
            ));
        }
    }
}
