<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Document;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Contena\Core\Checkout\Document\DocumentIdCollection;
use Contena\Core\Checkout\Document\FileGenerator\FileTypes;
use Contena\Core\Checkout\Document\Service\DocumentConfigLoader;
use Contena\Core\Checkout\Document\Service\DocumentGenerator;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\DeliveryTime\DeliveryTimeEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
trait DocumentTrait
{
    use IntegrationTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    #[After]
    public function resetDocumentConfigLoader(): void
    {
        static::getContainer()->get(DocumentConfigLoader::class)->reset();
    }

    private function persistCart(Cart $cart): string
    {
        return static::getContainer()->get(CartService::class)->order($cart, $this->channelContext, new RequestDataBag());
    }

    /**
     * @param array<string, string> $options
     * @param array<string, string> $additionalAddress
     */
    private function createUser(array $options = [], ?array $additionalAddress = null): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'guest' => true,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        if ($additionalAddress) {
            $user['addresses'][] = \array_merge($additionalAddress, [
                'userId' => $userId,
            ]);
        }

        $user = \array_merge($user, $options);

        static::getContainer()->get('user.repository')->upsert([$user], $this->context);

        return $userId;
    }

    private function generateDemoCart(int $lineItemCount): Cart
    {
        $cartService = static::getContainer()->get(CartService::class);

        $cart = $cartService->createNew('a-b-c');

        $keywords = ['awesome', 'epic', 'high quality'];

        $blogs = [];

        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        for ($i = 0; $i < $lineItemCount; ++$i) {
            $price = random_int(100, 200000) / 100.0;

            shuffle($keywords);
            $name = ucfirst(implode(' ', $keywords) . ' blog');

            $number = Uuid::randomHex();

            $blog = (new BlogBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-' . Uuid::randomHex(), 7)
                ->visibility()
                ->build();

            $blogs[] = $blog;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->channelContext);
            $this->addTaxDataToChannel($this->channelContext, $blog['tax']);
        }

        static::getContainer()->get('blog.repository')->create($blogs, Context::createDefaultContext());

        return $cartService->add($cart, $lineItems, $this->channelContext);
    }

    /**
     * @param array<int|string, int> $taxes
     */
    private function generateDemoCartWithTaxes(array $taxes): Cart
    {
        $cartService = static::getContainer()->get(CartService::class);

        $cart = $cartService->createNew('A');

        $blogs = [];

        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        foreach ($taxes as $index => $tax) {
            $price = 100.0 + (int) $index;
            $name = 'blog ' . $index;
            $number = 'p' . $index;

            $blog = (new BlogBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-' . Uuid::randomHex(), $tax)
                ->visibility()
                ->build();

            $blogs[] = $blog;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->channelContext);
            $this->addTaxDataToChannel($this->channelContext, $blog['tax']);
        }

        static::getContainer()->get('blog.repository')->create($blogs, Context::createDefaultContext());

        return $cartService->add($cart, $lineItems, $this->channelContext);
    }

    private function createShippingMethod(float $price = 10.0): string
    {
        $shippingMethodId = Uuid::randomHex();
        $repository = static::getContainer()->get('shipping_method.repository');

        $repository->create([[
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method',
            'technicalName' => Uuid::randomHex(),
            'bindShippingfree' => false,
            'active' => true,
            'channels' => [
                ['id' => TestDefaults::CHANNEL],
            ],
            'channelDefaultAssignments' => [
                ['id' => TestDefaults::CHANNEL],
            ],
            'prices' => [[
                'name' => 'Std',
                'currencyPrice' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'net' => $price,
                    'gross' => $price,
                    'linked' => false,
                ]],
                'currencyId' => Defaults::CURRENCY,
                'calculation' => 1,
                'quantityStart' => 1,
            ]],
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'min' => 1,
                'max' => 90,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
        ]], $this->context);

        return $shippingMethodId;
    }

    private function getBaseConfig(string $documentType, ?string $channelId = null): ?DocumentBaseConfigEntity
    {
        /** @var EntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = static::getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $documentType)),
            Context::createDefaultContext()
        )->firstId();

        /** @var EntityRepository<DocumentBaseConfigCollection> $documentBaseConfigRepository */
        $documentBaseConfigRepository = static::getContainer()->get('document_base_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        $criteria->addFilter(new EqualsFilter('global', true));

        if ($channelId !== null) {
            $criteria->addFilter(new EqualsFilter('channels.channelId', $channelId));
            $criteria->addFilter(new EqualsFilter('channels.documentTypeId', $documentTypeId));
        }

        $config = $documentBaseConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if ($config === null) {
            return null;
        }

        static::assertInstanceOf(DocumentBaseConfigEntity::class, $config);

        return $config;
    }

    /**
     * @param array<string, array<string, string>|string> $config
     */
    private function createDocument(string $documentType, string $orderId, array $config, Context $context): DocumentIdCollection
    {
        $operations = [];
        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $config);
        $operations[$orderId] = $operation;

        return static::getContainer()->get(DocumentGenerator::class)->generate($documentType, $operations, $context)->getSuccess();
    }

    /**
     * @param array<string|bool, string|bool|int|array<int, string>> $config
     */
    private function upsertBaseConfig(array $config, string $documentType, ?string $channelId = null): void
    {
        $baseConfig = $this->getBaseConfig($documentType, $channelId);

        /** @var EntityRepository<DocumentTypeCollection> $documentTypeRepository */
        $documentTypeRepository = static::getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $documentType)),
            Context::createDefaultContext()
        )->firstId();

        if ($baseConfig === null) {
            $documentConfigId = Uuid::randomHex();
        } else {
            $documentConfigId = $baseConfig->getId();
        }

        $data = [
            'id' => $documentConfigId,
            'typeId' => $documentTypeId,
            'documentTypeId' => $documentTypeId,
            'config' => $config,
        ];
        if ($baseConfig === null) {
            $data['name'] = $documentConfigId;
        }
        if ($channelId !== null) {
            $data['channels'] = [
                [
                    'documentBaseConfigId' => $documentConfigId,
                    'documentTypeId' => $documentTypeId,
                    'channelId' => $channelId,
                ],
            ];
        }

        /** @var EntityRepository<DocumentBaseConfigCollection> $documentBaseConfigRepository */
        $documentBaseConfigRepository = static::getContainer()->get('document_base_config.repository');
        $documentBaseConfigRepository->upsert([$data], Context::createDefaultContext());
    }

    private function upsertDocumentSellerAddress(string $documentType): void
    {
        $this->upsertBaseConfig([
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
            'companyCountryId' => $this->getValidCountryId(),
        ], $documentType);
    }

    private function orderVersionExists(string $orderId, string $orderVersionId): bool
    {
        return (bool) static::getContainer()->get(Connection::class)->fetchOne('
            SELECT 1 FROM `order` WHERE `id` = :id AND `version_id` = :versionId
        ', [
            'id' => Uuid::fromHexToBytes($orderId),
            'versionId' => Uuid::fromHexToBytes($orderVersionId),
        ]);
    }
}
