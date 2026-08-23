<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(typeProperty: 'type', mapping: [
    'system' => SystemSource::class,
    'admin-api' => AdminApiSource::class,
    'channel' => ChannelApiSource::class,
    'admin-channel-api' => AdminChannelApiSource::class,
])]
interface ContextSource
{
}
