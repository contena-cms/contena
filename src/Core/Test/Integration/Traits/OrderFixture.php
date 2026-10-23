<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
trait OrderFixture
{
    use BasicTestDataBehaviour;
    use EntityFixturesBase;

    /**
     * @throws \JsonException
     *
     * @return list<array<string, mixed>>
     */
    private function getOrderData(string $orderId, Context $context, ?string $userId = null): array
    {
        $orderUserId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $userId = $userId ?? Uuid::randomHex();
        $orderNumber = Uuid::randomHex();
        $deliveryId = Uuid::randomHex();

        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');

        $channel = $channelRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('id', TestDefaults::CHANNEL)),
            $context
        )->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        $paymentMethodId = $channel->getPaymentMethodId();
        $shippingMethodId = $channel->getShippingMethodId();
        $salutationId = $this->getValidSalutationId();
        $countryId = $this->getValidCountryId(TestDefaults::CHANNEL);

        $order = [
            [
                'id' => $orderId,
                'orderNumber' => $orderNumber,
                'versionId' => Defaults::LIVE_VERSION,
                'paymentMethodId' => $paymentMethodId,
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'channelId' => TestDefaults::CHANNEL,
                'orderDateTime' => '2019-04-01 08:36:43.267',
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'deliveries' => [
                    [
                        'id' => $deliveryId,
                        'shippingMethodId' => $shippingMethodId,
                        'shippingDateEarliest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                        'shippingDateLatest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                        'shippingOrderAddress' => [
                            'salutationId' => $salutationId,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'country' => [
                                'name' => 'kasachstan',
                                'id' => $countryId,
                            ],
                        ],
                        'trackingCodes' => [
                            'CODE-1',
                            'CODE-2',
                        ],
                        'positions' => [
                            [
                                'orderLineItemId' => $orderLineItemId,
                            ],
                        ],
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $orderLineItemId,
                        'identifier' => 'test',
                        'quantity' => 1,
                        'type' => 'test',
                        'label' => 'test',
                        'priority' => 100,
                        'good' => true,
                    ],
                ],
                'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
                'orderUserId' => $orderUserId,
                'orderUser' => [
                    'id' => $orderUserId,
                    'email' => 'test@example.com',
                    'firstName' => 'Noe',
                    'lastName' => 'Hill',
                    'salutationId' => $salutationId,
                    'title' => 'Doc',
                    'userNumber' => 'Test',
                    'orderVersionId' => Defaults::LIVE_VERSION,
                    'user' => [
                        'id' => $userId,
                        'email' => 'test@example.com',
                        'firstName' => 'Noe',
                        'lastName' => 'Hill',
                        'salutationId' => $salutationId,
                        'title' => 'Doc',
                        'userNumber' => 'Test',
                        'guest' => true,
                        'group' => ['name' => 'testse2323'],
                        'channelId' => TestDefaults::CHANNEL,
                        'defaultBillingAddressId' => $addressId,
                        'defaultShippingAddressId' => $addressId,
                        'addresses' => [
                            [
                                'id' => $addressId,
                                'salutationId' => $salutationId,
                                'firstName' => 'Floy',
                                'lastName' => 'Glover',
                                'zipcode' => '59438-0403',
                                'city' => 'Stellaberg',
                                'street' => 'street',
                                'countryStateId' => $countryStateId,
                                'country' => [
                                    'name' => 'kasachstan',
                                    'id' => $countryId,
                                    'states' => [
                                        [
                                            'id' => $countryStateId,
                                            'name' => 'oklahoma',
                                            'shortCode' => 'OH',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'billingAddressId' => $addressId,
                'addresses' => [
                    [
                        'salutationId' => $salutationId,
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => 'street',
                        'countryId' => $countryId,
                        'id' => $addressId,
                    ],
                ],
            ],
        ];

        return $order;
    }
}
