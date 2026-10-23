<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(
    typeProperty: 'type',
    mapping: [
        'system' => SystemSource::class,
        'sales-channel' => ChannelApiSource::class,
        'admin-api' => AdminApiSource::class,
        'shop-api' => ShopApiSource::class,
        'admin-sales-channel-api' => AdminChannelApiSource::class,
    ]
)]
interface ContextSource
{
}
