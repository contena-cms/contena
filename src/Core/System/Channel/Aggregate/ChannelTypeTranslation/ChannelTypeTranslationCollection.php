<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ChannelTypeTranslationEntity>
 */
class ChannelTypeTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getChannelTypeIds(): array
    {
        return $this->fmap(static fn (ChannelTypeTranslationEntity $channelTypeTranslation) => $channelTypeTranslation->getChannelTypeId());
    }

    public function filterByChannelId(string $id): self
    {
        return $this->filter(static fn (ChannelTypeTranslationEntity $channelTypeTranslation) => $channelTypeTranslation->getChannelTypeId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (ChannelTypeTranslationEntity $channelTranslation) => $channelTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (ChannelTypeTranslationEntity $channelTranslation) => $channelTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'channel_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelTypeTranslationEntity::class;
    }
}
