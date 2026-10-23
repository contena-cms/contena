<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Capability;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
readonly class ChannelTypeCapabilityRegistry
{
    /**
     * @param iterable<AbstractChannelTypeCapabilities> $capabilities
     */
    public function __construct(private iterable $capabilities)
    {
    }

    public function isTransactional(string $channelTypeId): bool
    {
        return \in_array($channelTypeId, $this->getTransactionalTypeIds(), true);
    }

    /**
     * @return list<string>
     */
    public function getTransactionalTypeIds(): array
    {
        $transactionalTypeIds = [];

        foreach (DefaultChannelType::cases() as $defaultType) {
            if ($defaultType->isTransactional()) {
                $transactionalTypeIds[] = $defaultType->value;
            }
        }

        foreach ($this->capabilities as $typeCapabilities) {
            if ($typeCapabilities->isTransactional()) {
                $transactionalTypeIds[] = $typeCapabilities->getChannelTypeId();
            }
        }

        return array_values(array_unique($transactionalTypeIds));
    }
}
