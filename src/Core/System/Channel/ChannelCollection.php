<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeCollection;

/**
 * @extends EntityCollection<ChannelEntity>
 *
 * @codeCoverageIgnore
 */
class ChannelCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (ChannelEntity $channel) => $channel->getLanguageId());
    }

    public function filterByLanguageId(string $id): ChannelCollection
    {
        return $this->filter(static fn (ChannelEntity $channel) => $channel->getLanguageId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getCurrencyIds(): array
    {
        return $this->fmap(static fn (ChannelEntity $channel) => $channel->getCurrencyId());
    }

    public function filterByCurrencyId(string $id): ChannelCollection
    {
        return $this->filter(static fn (ChannelEntity $channel) => $channel->getCurrencyId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getPaymentMethodIds(): array
    {
        return $this->fmap(static fn (ChannelEntity $channel) => $channel->getPaymentMethodId());
    }

    public function filterByPaymentMethodId(string $id): ChannelCollection
    {
        return $this->filter(static fn (ChannelEntity $channel) => $channel->getPaymentMethodId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getShippingMethodIds(): array
    {
        return $this->fmap(static fn (ChannelEntity $channel) => $channel->getShippingMethodId());
    }

    public function filterByShippingMethodId(string $id): ChannelCollection
    {
        return $this->filter(static fn (ChannelEntity $channel) => $channel->getShippingMethodId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getCountryIds(): array
    {
        return $this->fmap(static fn (ChannelEntity $channel) => $channel->getCountryId());
    }

    public function filterByCountryId(string $id): ChannelCollection
    {
        return $this->filter(static fn (ChannelEntity $channel) => $channel->getCountryId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getTypeIds(): array
    {
        return $this->fmap(static fn (ChannelEntity $channel) => $channel->getTypeId());
    }

    public function filterByTypeId(string $id): ChannelCollection
    {
        return $this->filter(static fn (ChannelEntity $channel) => $channel->getTypeId() === $id);
    }

    public function getLanguages(): LanguageCollection
    {
        return new LanguageCollection(
            $this->fmap(static fn (ChannelEntity $channel) => $channel->getLanguage())
        );
    }

    public function getCurrencies(): CurrencyCollection
    {
        return new CurrencyCollection(
            $this->fmap(static fn (ChannelEntity $channel) => $channel->getCurrency())
        );
    }

    public function getTypes(): ChannelTypeCollection
    {
        return new ChannelTypeCollection(
            $this->fmap(static fn (ChannelEntity $channel) => $channel->getType())
        );
    }

    public function getApiAlias(): string
    {
        return 'channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelEntity::class;
    }
}
