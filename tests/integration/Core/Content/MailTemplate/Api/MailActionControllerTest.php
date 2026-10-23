<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\MailTemplate\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Document\FileGenerator\FileTypes;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Service\DocumentGenerator;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
class MailActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testSendSuccess(): void
    {
        $context = Context::createDefaultContext();

        $userId = $this->createUser($context);
        $orderId = $this->createOrder($userId, $context);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderUser');
        $order = static::getContainer()->get('order.repository')->search($criteria, $context)->getEntities()->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        $documentId = $this->createDocumentWithFile($orderId, $context);
        $documentIds = [$documentId];

        $criteria = new Criteria();
        $criteria->setLimit(1);
        /** @var ?MailTemplateEntity $mailTemplate */
        $mailTemplate = static::getContainer()
            ->get('mail_template.repository')
            ->search($criteria, $context)->getEntities()
            ->first();
        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        $criteria = new Criteria([TestDefaults::CHANNEL]);
        $criteria->setLimit(1);
        $channel = static::getContainer()
            ->get('channel.repository')
            ->search($criteria, $context)->getEntities()
            ->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        $entityEncoder = new JsonEntityEncoder(
            new Serializer([new StructNormalizer()], [new JsonEncoder()])
        );
        $orderDefinition = static::getContainer()->get(OrderDefinition::class);
        $orderDecode = $entityEncoder->encode(new Criteria(), $orderDefinition, $order, '/api');
        array_walk_recursive($orderDecode, static function (&$value): void {
            if ($value instanceof \stdClass) {
                $value = json_decode((string) json_encode($value), true, 512, \JSON_THROW_ON_ERROR);
            }
        });

        $channelDefinition = static::getContainer()->get(ChannelDefinition::class);
        $channelDecode = $entityEncoder->encode(new Criteria(), $channelDefinition, $channel, '/api');
        array_walk_recursive($channelDecode, static function (&$value): void {
            if ($value instanceof \stdClass) {
                $value = json_decode((string) json_encode($value), true, 512, \JSON_THROW_ON_ERROR);
            }
        });

        $this->getBrowser()
            ->request(
                'POST',
                '/api/_action/mail-template/send',
                [
                    'contentHtml' => $mailTemplate->getContentHtml(),
                    'contentPlain' => $mailTemplate->getContentPlain(),
                    'mailTemplateData' => [
                        'order' => $orderDecode,
                        'channel' => $channelDecode,
                    ],
                    'documentIds' => $documentIds,
                    'recipients' => ['d.dinh@contena.cn' => 'Duy'],
                    'channelId' => $channel->getId(),
                    'senderName' => $channel->getName(),
                    'subject' => 'New document for your order',
                    'testMode' => false,
                ],
            );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('size', $response);
    }

    public function testPreviewSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/preview',
            [
                'mailTemplateId' => $mailTemplate->getId(),
                'channelId' => TestDefaults::CHANNEL,
                'includeHeaderFooter' => true,
                'templateData' => [
                    'customName' => 'Contena',
                ],
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertSame('success', $response['subject']['type']);
        static::assertSame('Hello Contena', $response['subject']['content']);
        static::assertSame('success', $response['contentHtml']['type']);
        static::assertStringContainsString('Contena', $response['contentHtml']['content']);
    }

    public function testGetDataAndSendSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/get-data-and-send',
            [
                'mailTemplateId' => $mailTemplate->getId(),
                'templateData' => [
                    'customName' => 'Contena',
                ],
                'recipients' => ['d.dinh@contena.cn' => 'Duy'],
                'channelId' => TestDefaults::CHANNEL,
                'testMode' => false,
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('size', $response);
        static::assertGreaterThan(0, $response['size']);
    }

    public function testSimulateSuccess(): void
    {
        TestUser::createNewTestUser(
            $this->getBrowser()->getContainer()->get(Connection::class),
            ['mail_template:update']
        )->authorizeBrowser($this->getBrowser());

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/simulate',
            [
                'templateParts' => [
                    'contentHtml' => '<p>{{ order.id }}</p>',
                ],
                'eventName' => 'checkout.order.placed',
                'channelId' => TestDefaults::CHANNEL,
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertSame('success', $response['contentHtml']['type']);
        static::assertNotSame('', $response['contentHtml']['content']);
    }

    public function testSimulateRequiresMailTemplateUpdatePrivilege(): void
    {
        $browser = $this->getBrowser();
        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['mail_template:read']
        )->authorizeBrowser($browser);

        $browser->request('POST', '/api/_action/mail-template/simulate');

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
    }

    public function testAvailableVariablesSuccess(): void
    {
        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/available-variables',
            [
                'eventName' => 'checkout.order.placed',
                'parentVariablePath' => 'order',
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertContains('lineItems', array_column($response, 'fieldName'));
    }

    private function createUser(Context $context): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'contena',
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
                    'name' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()
            ->get('user.repository')
            ->upsert([$user], $context);

        return $userId;
    }

    private function createOrder(string $userId, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderUser' => [
                'userId' => $userId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'name' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'channelId' => TestDefaults::CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
                    'id' => $billingAddressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [
            ],
            'transactions' => [
                [
                    'paymentMethodId' => $this->getValidPaymentMethodId(),
                    'stateId' => $stateId,
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
            'context' => '{}',
            'payload' => '{}',
        ];

        $orderRepository = static::getContainer()->get('order.repository');

        $orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createDocumentWithFile(string $orderId, Context $context, string $documentType = InvoiceRenderer::TYPE): string
    {
        $documentGenerator = static::getContainer()->get(DocumentGenerator::class);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, []);
        $document = $documentGenerator->generate($documentType, [$orderId => $operation], $context)->getSuccess()->first();

        static::assertNotNull($document);

        return $document->getId();
    }

    private function createSimpleMailTemplate(Context $context): MailTemplateEntity
    {
        $typeCriteria = new Criteria();
        $typeCriteria->setLimit(1);

        /** @var EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = static::getContainer()->get('mail_template_type.repository');
        $mailTemplateType = $mailTemplateTypeRepository->search($typeCriteria, $context)->getEntities()->first();

        static::assertInstanceOf(MailTemplateTypeEntity::class, $mailTemplateType);

        $mailTemplateId = Uuid::randomHex();

        /** @var EntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $mailTemplateRepository->create([[
            'id' => $mailTemplateId,
            'mailTemplateTypeId' => $mailTemplateType->getId(),
            'subject' => 'Hello {{ customName }}',
            'senderName' => 'Contena',
            'contentHtml' => '<p>Hello {{ customName }}</p>',
            'contentPlain' => 'Hello {{ customName }}',
        ]], $context);

        $mailTemplate = $mailTemplateRepository->search(new Criteria([$mailTemplateId]), $context)->getEntities()->first();

        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        return $mailTemplate;
    }
}
