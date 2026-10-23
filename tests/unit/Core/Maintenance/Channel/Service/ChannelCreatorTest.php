<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ChannelCreator::class)]
class ChannelCreatorTest extends TestCase
{
    public function testCreatesChannelWithProvidedValuesAndMappings(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class);
        $creator = new ChannelCreator(
            static::createStub(DefinitionInstanceRegistry::class),
            $channelRepository,
            StaticEntityRepository::of(PaymentMethodCollection::class),
            StaticEntityRepository::of(ShippingMethodCollection::class),
            StaticEntityRepository::of(CountryCollection::class),
            StaticEntityRepository::of(CategoryCollection::class),
        );

        $id = Uuid::randomHex();
        $typeId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $countryId = Uuid::randomHex();
        $userGroupId = Uuid::randomHex();
        $navigationCategoryId = Uuid::randomHex();
        $accessKey = $creator->createChannel(
            id: $id,
            name: 'Test channel',
            typeId: $typeId,
            languageId: $languageId,
            currencyId: $currencyId,
            paymentMethodId: $paymentMethodId,
            shippingMethodId: $shippingMethodId,
            countryId: $countryId,
            userGroupId: $userGroupId,
            navigationCategoryId: $navigationCategoryId,
            currencies: [$currencyId, $currencyId],
            languages: [],
            shippingMethods: [$shippingMethodId],
            paymentMethods: [],
            countries: [$countryId],
            overwrites: ['accessKey' => 'provided-access-key'],
        );

        static::assertSame('provided-access-key', $accessKey);
        static::assertCount(1, $channelRepository->creates);
        static::assertSame([
            'id' => $id,
            'name' => 'Test channel',
            'typeId' => $typeId,
            'accessKey' => 'provided-access-key',
            'languageId' => $languageId,
            'currencyId' => $currencyId,
            'paymentMethodId' => $paymentMethodId,
            'shippingMethodId' => $shippingMethodId,
            'countryId' => $countryId,
            'userGroupId' => $userGroupId,
            'navigationCategoryId' => $navigationCategoryId,
            'currencies' => [['id' => $currencyId]],
            'languages' => [['id' => $languageId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'countries' => [['id' => $countryId]],
        ], $channelRepository->creates[0][0]);
    }

    public function testUsesAllEntityIdsWhenMappingsAreNotProvided(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class);
        $repositories = [
            'currency' => StaticEntityRepository::of(ChannelCollection::class, [['currency-id']]),
            'language' => StaticEntityRepository::of(ChannelCollection::class, [['language-id']]),
            'shipping_method' => StaticEntityRepository::of(ChannelCollection::class, [['shipping-method-id']]),
            'payment_method' => StaticEntityRepository::of(ChannelCollection::class, [['payment-method-id']]),
            'country' => StaticEntityRepository::of(ChannelCollection::class, [['country-id']]),
            UserGroupDefinition::ENTITY_NAME => StaticEntityRepository::of(ChannelCollection::class, [['user-group-id']]),
        ];

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getRepository')->willReturnCallback(
            static fn (string $entity): StaticEntityRepository => $repositories[$entity]
        );

        $creator = new ChannelCreator(
            $definitionRegistry,
            $channelRepository,
            StaticEntityRepository::of(PaymentMethodCollection::class, [['payment-default']]),
            StaticEntityRepository::of(ShippingMethodCollection::class, [['shipping-default']]),
            StaticEntityRepository::of(CountryCollection::class, [['country-default']]),
            StaticEntityRepository::of(CategoryCollection::class, [['root-category']]),
        );

        $creator->createChannel(
            id: 'sales-channel-id',
            name: 'All IDs channel',
            typeId: 'type-id',
            paymentMethodId: 'payment-default',
            shippingMethodId: 'shipping-default',
            countryId: 'country-default',
            navigationCategoryId: 'root-category',
        );

        static::assertSame([['id' => 'currency-id']], $channelRepository->creates[0][0]['currencies']);
        static::assertSame([['id' => 'language-id']], $channelRepository->creates[0][0]['languages']);
        static::assertSame('user-group-id', $channelRepository->creates[0][0]['userGroupId']);
    }
}
