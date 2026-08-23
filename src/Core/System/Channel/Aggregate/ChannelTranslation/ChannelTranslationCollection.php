<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ChannelTranslationEntity>
 */
class ChannelTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getChannelIds(): array
    {
        return $this->fmap(static fn (ChannelTranslationEntity $channelTranslation) => $channelTranslation->getChannelId());
    }

    public function filterByChannelId(string $id): self
    {
        return $this->filter(static fn (ChannelTranslationEntity $channelTranslation) => $channelTranslation->getChannelId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (ChannelTranslationEntity $channelTranslation) => $channelTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (ChannelTranslationEntity $channelTranslation) => $channelTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'channel_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelTranslationEntity::class;
    }
}
